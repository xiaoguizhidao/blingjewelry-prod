<?php
/*
 * Aten Software Product Data Exporter for Magento
 * 
 * Copyright �2012. Aten Software LLC. All Rights Reserved.
 * Author: Shailesh Humbad
 * Website: http://www.atensoftware.com/p187.php
 *
 * This file is part of Aten Software Product Data Exporter for Magento.
 *
 * Aten Software Product Data Exporter for Magento is free software: 
 * you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Aten Software Product Data Exporter for Magento
 * is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * See http://www.gnu.org/licenses/ for a copy of the GNU General Public License.
 *
 * */
// Uncomment to enable debugging
// ini_set('display_errors', '1');
// ini_set('error_reporting', E_ALL);
// Increase memory limit
// ini_set('memory_limit', '1024M');
// Include the Magento application
define('MAGENTOROOT', realpath(dirname(__FILE__)));
require_once(MAGENTOROOT.'/app/Mage.php');
// Execute the class by constructing it
$exporter = new AtenExporterForMagento();
// Class to hold all functionality for the exporter
class AtenExporterForMagento
{
	// Set the password to export data here
	const PASSWORD = 'lsizbzfzokw9';
	// Version of this script
	const VERSION = '2012-03-24';
	// Helper variables
	private $_tablePrefix;
	private $_storeId;
	private $_websiteId;
	private $_mediaBaseUrl;
	private $_webBaseUrl;
	private $_dbi;
	// Initialize the Mage application
	function __construct()
	{
		// Increase maximum execution time to 4 hours
		ini_set('max_execution_time', 14400);

		// Set working directory to magento root folder
		chdir(MAGENTOROOT);
	
		// Make files written by the profile world-writable/readable
		umask(0);

		// Initialize the admin application
		Mage::app('admin');

		// Get the table prefix
		$tableName = Mage::getSingleton('core/resource')->getTableName('core_website');
		$this->_tablePrefix = substr($tableName, 0, strpos($tableName, 'core_website'));

		// Get database connection to Magento (PDO MySQL object)
		$this->_dbi = Mage::getSingleton('core/resource') ->getConnection('core_read');	

		// Run the main application
		$this->_runMain();
	}
	
	// Apply prefix to table names in the query
	private function _applyTablePrefix($query)
	{
		return str_replace('PFX_', $this->_tablePrefix, $query);
	}
	
	// Run the main application and call the appropriate function
	//   depending on the command.
	private function _runMain()
	{
		// Get the command line parameters if running in CLI-mode
		if($this->_isCLI() == true)
		{
			if($_SERVER['argc'] == 2)
			{
				// Get parameters from the command line
				//  and add them to the REQUEST array
				parse_str($_SERVER['argv'][1], $_REQUEST);
				
			}
		}
		// Get parameters from the REQUEST array
		$Command = isset($_REQUEST['Command']) ? $_REQUEST['Command'] : '';
		$this->_storeId = isset($_REQUEST['Store']) ? $_REQUEST['Store'] : '';
		$Password = isset($_REQUEST['Password']) ? $_REQUEST['Password'] : '';

		// If the command is export, then run the native export
		if($Command == 'Export')
		{
			// Check password
			//$this->_checkPassword($Password);
			
			// Validate store and get information
			$this->_getStoreInformation();

			// Run extraction
			$this->_extractFromMySQL();
			
			// End script
			return;
		}	

		// If the command is not export, display the form
		$this->DisplayForm();
	}

	// Extract natively directly from the database
	private function _extractFromMySQL()
	{
		// Start sending file 
		if($this->_isCLI() == false)
		{
			// Set up a file name
			$FileName = sprintf('%d_%d.csv', $this->_websiteId, $this->_storeId);
	
			$this->_startFileSend($FileName);
		}
		
		// Increase maximium length for group_concat (for additional image URLs field)
		$query = "SET SESSION group_concat_max_len = 1000000;";
		$this->_dbi->query($query);

		// By default, set media gallery attribute id to 703
		//  Look it up later
		$MEDIA_GALLERY_ATTRIBUTE_ID = 703;


		// Get the entity type for products
		$query = "SELECT entity_type_id FROM PFX_eav_entity_type WHERE entity_type_code = 'catalog_product'";
		$query = $this->_applyTablePrefix($query);
		$PRODUCT_ENTITY_TYPE_ID = $this->_dbi->fetchOne($query);
		

		// Get attribute codes and types
		$query = "SELECT attribute_id, attribute_code, backend_type, frontend_input
			FROM PFX_eav_attribute
			WHERE entity_type_id = $PRODUCT_ENTITY_TYPE_ID
			";
		$query = $this->_applyTablePrefix($query);
		$attributes = $this->_dbi->FetchAssoc($query);
		$attributeCodes = array();
		$blankProduct = array();
		$blankProduct['sku'] = '';
		foreach($attributes as $row)
		{
			// Save attribute ID for media gallery
			if($row['attribute_code'] == 'media_gallery')
			{
				$MEDIA_GALLERY_ATTRIBUTE_ID = $row['attribute_id'];
			}
		
			switch($row['backend_type'])
			{
				case 'datetime':
				case 'decimal':
				case 'int':
				case 'text':
				case 'varchar':
					$attributeCodes[$row['attribute_id']] = $row['attribute_code'];
					$blankProduct[$row['attribute_code']] = '';
				break;
			case 'static':
				// ignore columns in entity table
				// print("Skipping static attribute: ".$row['attribute_code']."\n");
				break;
			default:
				// print("Unsupported backend_type: ".$row['backend_type']."\n");
				break;
			}
			
			// If the type is multiple choice, cache the option values
			//   in a lookup array for performance (avoids several joins/aggregations)
			if($row['frontend_input'] == 'select')
			{
				// Get the option_id => value from the attribute options
				$query = "
					SELECT
						 CASE WHEN SUM(aov.store_id) = 0 THEN MAX(aov.option_id) ELSE 
							MAX(CASE WHEN aov.store_id = ".$this->_storeId." THEN aov.option_id ELSE NULL END)
						 END AS 'option_id'
						,CASE WHEN SUM(aov.store_id) = 0 THEN MAX(aov.value) ELSE 
							MAX(CASE WHEN aov.store_id = ".$this->_storeId." THEN aov.value ELSE NULL END)
						 END AS 'value'
					FROM PFX_eav_attribute_option AS ao
					INNER JOIN PFX_eav_attribute_option_value AS aov
						ON ao.option_id = aov.option_id
					WHERE aov.store_id IN (".$this->_storeId.", 0)
						AND ao.attribute_id = ".$row['attribute_id']."
					GROUP BY aov.option_id
				";
				$query = $this->_applyTablePrefix($query);
				$result = $this->_dbi->fetchPairs($query);
				
				// If found, then save the lookup table in the attributeOptions array
				if(is_array($result))
				{
					$attributeOptions[$row['attribute_id']] = $result;
				}
				else
				{
					// Otherwise, leave a blank array
					$attributeOptions[$row['attribute_id']] = array();
				}
				$result = null;
			}
		}
		$blankProduct['aten_product_url'] = '';
		$blankProduct['aten_image_url'] = '';
		$blankProduct['aten_additional_image_url'] = '';
		$blankProduct['aten_additional_image_value_id'] = '';
		$blankProduct['json_categories'] = '';
		$blankProduct['qty'] = 0;
		$blankProduct['stock_status'] = '';
		$blankProduct['aten_color_attribute_id'] = '';
		$blankProduct['parent_id'] = '';
		$blankProduct['entity_id'] = '';

		// Build queries for each attribute type
		$backendTypes = array(
			'datetime',
			'decimal',
			'int',
			'text',
			'varchar',
		);
		$queries = array();
		foreach($backendTypes as $backendType)
		{
			// Get store value if there is one, otherwise, global value
			$queries[] = "
		SELECT CASE WHEN SUM(ev.store_id) = 0 THEN MAX(ev.value) ELSE 
			MAX(CASE WHEN ev.store_id = ".$this->_storeId." THEN ev.value ELSE NULL END)
			END AS 'value', ev.attribute_id
		FROM PFX_catalog_product_entity
		INNER JOIN PFX_catalog_product_entity_$backendType AS ev
			ON PFX_catalog_product_entity.entity_id = ev.entity_id
		WHERE ev.store_id IN (".$this->_storeId.", 0)
		AND ev.entity_type_id = $PRODUCT_ENTITY_TYPE_ID
		AND ev.entity_id = @ENTITY_ID
		GROUP BY ev.attribute_id, ev.entity_id
		";
		}
		$query = implode(" UNION ALL ", $queries);
		$MasterProductQuery = $query;

		// Get all entity_ids for all products in the selected store
		//  into an array - require SKU to be defined
		$query = "
			SELECT cpe.entity_id, cpe.sku, cpe.type_id
			FROM PFX_catalog_product_entity AS cpe
			INNER JOIN PFX_catalog_product_website as cpw
				ON cpw.product_id = cpe.entity_id
			WHERE cpw.website_id = ".$this->_websiteId."
				AND IFNULL(cpe.sku, '') != ''
		";
		$query = $this->_applyTablePrefix($query);
		// Set fetch mode to numeric to save memory
		$this->_dbi->setFetchMode(ZEND_DB::FETCH_NUM);
		$EntityIds = $this->_dbi->fetchAll($query);

		// Print header row
		$headerFields = array();
		$headerFields[] = 'sku';
		foreach($attributeCodes as $fieldName)
		{
			$headerFields[] = str_replace('"', '""', $fieldName);
		}
		$headerFields[] = 'aten_product_url';
		$headerFields[] = 'aten_image_url';
		$headerFields[] = 'aten_additional_image_url';
		$headerFields[] = 'aten_additional_image_value_id';
		$headerFields[] = 'json_categories';
		$headerFields[] = 'qty';
		$headerFields[] = 'stock_status';
		$headerFields[] = 'aten_color_attribute_id';
		$headerFields[] = 'parent_id';
		$headerFields[] = 'entity_id';
		print '"'.implode('","', $headerFields).'"'."\n";
		// Loop through each product and output the data
		foreach($EntityIds as $entity)
		{
			// Fill the master query with the entity ID
			// $entity[0] = entity_id
			// $entity[1] = sku
			$query = str_replace('@ENTITY_ID', $entity[0], $MasterProductQuery);
			$query = $this->_applyTablePrefix($query);
			$result = $this->_dbi->query($query);
			// Create a new product record
			$product = $blankProduct;
			// Initialize basic product data
			$product['entity_id'] = $entity[0];
			$product['sku'] = $entity[1];
			$product['type'] = $entity[2];
			// Loop through each field in the row and get the value
			while(true)
			{
				// Get next column
				// $column[0] = value
				// $column[1] = attribute_id
				$column = $result->fetch(Zend_Db::FETCH_NUM);
				// Break if no more rows
				if(empty($column))
				{
					break;
				}
				// Skip attributes that don't exist in eav_attribute
				if(!isset($attributeCodes[$column[1]]))
				{
					continue;
				}
				// Save color attribute ID (for CJM automatic color swatches extension)
				//  NOTE: do this prior to translating option_id to option_value below
				if($attributeCodes[$column[1]] == 'color')
				{
					$product['aten_color_attribute_id'] = $column[0];
				}
				// Translate the option option_id to a value.
				if(isset($attributeOptions[$column[1]]) == true)
				{
					if(isset($attributeOptions[$column[1]][$column[0]]) == true)
					{
						// If a option_id is found, translate it
						$column[0] = $attributeOptions[$column[1]][$column[0]];
					}
					elseif($column[0] == '0')
					{
						// Also erase value that are set to zero
						$column[0] = '';
					}
				}
				// Escape double-quotes and add to product array
				$product[$attributeCodes[$column[1]]] = str_replace('"', '""', $column[0]);
			}
			$result = null;
			// Skip product that are disabled or have no status
			if(empty($product['status']) || $product['status'] == Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
			{
				continue;
			}
			// Get category information
			$query = "
				SELECT fs.entity_id, fs.path, fs.name
				FROM PFX_catalog_category_product_index AS pi
					INNER JOIN PFX_catalog_category_flat_store_".$this->_storeId." AS fs
						ON pi.category_id = fs.entity_id
				WHERE pi.product_id = ".$entity[0]."
			";
			$query = $this->_applyTablePrefix($query);
			$this->_dbi->setFetchMode(ZEND_DB::FETCH_NUM);
			$categoriesTable = $this->_dbi->fetchAll($query);
			// Save entire table in JSON format
			$product['json_categories'] = json_encode($categoriesTable);
			// Escape double-quotes
			$product['json_categories'] = str_replace('"', '""', $product['json_categories']);
			
			// Get stock quantity
			// NOTE: stock_id = 1 is the 'Default' stock
			$query = "
				SELECT qty, stock_status
				FROM PFX_cataloginventory_stock_status
				WHERE product_id=".$entity[0]."
					AND website_id=".$this->_websiteId."
					AND stock_id = 1";
			$query = $this->_applyTablePrefix($query);
			$stockInfoResult = $this->_dbi->query($query);
			$stockInfo = $stockInfoResult->fetch();
			if(empty($stockInfo) == true)
			{
				$product['qty'] = '0';
				$product['stock_status'] = '';
			}
			else
			{
				$product['qty'] = $stockInfo[0];
				$product['stock_status'] = $stockInfo[1];
			}
			$stockInfoResult = null;
			// Get additional image URLs
			$galleryImagePrefix = $this->_dbi->quote($this->_mediaBaseUrl.'catalog/product');
			$query = "
				SELECT
					 GROUP_CONCAT(gallery.value_id SEPARATOR ',') AS value_id
					,GROUP_CONCAT(CONCAT(".$galleryImagePrefix.", gallery.value) SEPARATOR ',') AS value
				FROM PFX_catalog_product_entity_media_gallery AS gallery
					INNER JOIN PFX_catalog_product_entity_media_gallery_value AS gallery_value
						ON gallery.value_id = gallery_value.value_id
				WHERE   gallery_value.store_id IN (".$this->_storeId.", 0)
					AND gallery_value.disabled = 0
					AND gallery.entity_id=".$entity[0]."
					AND gallery.attribute_id = ".$MEDIA_GALLERY_ATTRIBUTE_ID."
				ORDER BY gallery_value.position ASC";
			$query = $this->_applyTablePrefix($query);
			$this->_dbi->setFetchMode(ZEND_DB::FETCH_NUM);
			$galleryValues = $this->_dbi->fetchAll($query);
			if(empty($galleryValues) != true)
			{
				// Save value IDs for CJM automatic color swatches extension support
				$product['aten_additional_image_value_id'] = $galleryValues[0][0];
				$product['aten_additional_image_url'] = $galleryValues[0][1];
			}
			// Get parent ID
			$query = "
				SELECT GROUP_CONCAT(parent_id SEPARATOR ',') AS parent_id
				FROM PFX_catalog_product_super_link AS super_link
				WHERE super_link.product_id=".intval($entity[0])."";
			$query = $this->_applyTablePrefix($query);
			$this->_dbi->setFetchMode(ZEND_DB::FETCH_NUM);
			$parentId = $this->_dbi->fetchAll($query);
			if(empty($parentId) != true)
			{
				// Save value IDs for CJM automatic color swatches extension support
				$product['parent_id'] = $parentId[0][0];
			}

			// Override price with catalog price rule, if found
			$query = "
				SELECT crpp.rule_price
				FROM PFX_catalogrule_product_price AS crpp
				WHERE crpp.rule_date = CURDATE()
					AND crpp.product_id = ".intval($entity[0])."
					AND crpp.customer_group_id = 1
					AND crpp.website_id = ".$this->_websiteId;
			$query = $this->_applyTablePrefix($query);
			$this->_dbi->setFetchMode(ZEND_DB::FETCH_NUM);
			$rule_price = $this->_dbi->fetchAll($query);
			if(empty($rule_price) != true)
			{
				// Override price with catalog rule price
				$product['price'] = $rule_price[0][0];
			}

			// Calculate image and product URLs
			if(empty($product['url_path']) == false)
			{
				$product['aten_product_url'] = $this->_webBaseUrl.$product['url_path'];
			}
			if(empty($product['image']) == false)
			{
				$product['aten_image_url'] = $this->_mediaBaseUrl.'catalog/product'.$product['image'];
			}
			// Print out the line in CSV format
			print '"'.implode('","', $product).'"'."\n";
		}
		
		// Finish sending file 
		if($this->_isCLI() == false)
		{
			$this->_endFileSend();
		}
	}
	// Send a output to the client browser as an inline attachment
	// Features: low-memory footprint, gzip compressed if supported
	private function _startFileSend($FileName)
	{
		// Supply last-modified date
		$gmdate_mod = gmdate('D, d M Y H:i:s', time()).' GMT';
		header("Last-Modified: $gmdate_mod");

		// Supply content headers
		header("Content-Type: text/plain; charset=UTF-8");
		header('Content-Disposition: inline; filename="'.$FileName.'"');
		// NOTE: Do not supply content-length header, because the file
		// may be sent gzip-compressed in which case the length would be wrong.
		
		// Add custom headers
		header("X-AtenSoftware-ShoppingCart: Magento");
		header("X-AtenSoftware-Version: ".self::VERSION);

		// Start gzip-chunked output buffering for faster downloads
		//   if zlib output compression is disabled
		if($this->_isZlibOutputCompressionEnabled() == false)
		{
			ob_start("ob_gzhandler", 8192);
		}
	}
	// Finish sending the file
	private function _endFileSend()
	{
		// Complete output buffering
		if($this->_isZlibOutputCompressionEnabled() == false)
		{
			ob_end_flush();
		}
	}
	// Returns true if zlib.output_compression is enabled, otherwise false
	private function _isZlibOutputCompressionEnabled()
	{
		$iniValue = ini_get('zlib.output_compression');
		return !( empty($iniValue) == true || $iniValue === 'Off' );
	}
	private function DisplayForm()
	{
		// Set character set to UTF-8
		header("Content-Type: text/html; charset=UTF-8");
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
	    <title>Aten Software Product Data Exporter for Magento</title>
	</head>
	<body>
	    <h2 style="text-align:center;"><a href="http://www.atensoftware.com/p187.php">Aten Software Product Data Exporter for Magento</a></h2>
	    
	    <div style="clear:both;"></div>
	
	    <form method="get" action="">
	    <table style="margin: 1em auto;" cellpadding="2">
	    <tr>
	    	<th style="background-color:#cccccc;">Select</th>
	    	<th style="background-color:#cccccc;">Website ID</th>
	    	<th style="background-color:#cccccc;">Website</th>
	    	<th style="background-color:#cccccc;">Store ID</th>
	    	<th style="background-color:#cccccc;">Store</th>
	    </tr>
	    <?php
	    
		// List all active website-stores
		$query = "SELECT
			 w.website_id
			,w.name as website_name
			,w.is_default
			,s.store_id
			,s.name as store_name
		FROM PFX_core_website AS w 
			INNER JOIN PFX_core_store AS s ON s.website_id = w.website_id
		WHERE s.is_active = 1 AND w.website_id > 0
		ORDER BY w.sort_order, w.name, s.sort_order, s.name";
		$query = $this->_applyTablePrefix($query);
		$result = $this->_dbi->query($query);
		$isChecked = false;
		while(true)
		{
			// Get next row
			$row = $result->fetch(Zend_Db::FETCH_ASSOC);
			// Break if no more rows
			if(empty($row))
			{
				break;
			}
			// Display the store-website details with a radio button
			print '<tr>';
			print '<td style="text-align:center;">';
			print '<input type="radio" name="Store" value="';
			print $row['store_id'].'"';
			// Check the first one
			if($isChecked == false)
			{
				print ' checked="checked" ';
				$isChecked = true;
			}
			print '/></td>';
			print '<td style="text-align:center;">'.htmlentities($row['website_id']).'</td>';
			print '<td>'.htmlentities($row['website_name']).'</td>';
			print '<td style="text-align:center;">'.htmlentities($row['store_id']).'</td>';
			print '<td>'.htmlentities($row['store_name']).'</td>';
			print '</tr>';
			print "\n";

		}
		$result = null;
	    ?>
	    </table>
	    
	    <table style="margin: 1em auto;" cellpadding="10">
	    <tr>
			<th style="white-space: nowrap;">Password</th>
			<td><input type="text" name="Password" /></td>
			<td>
				<input type="submit" value="Export the Product Data in CSV format" />
				<input type="hidden" name="Command" value="Export" />
			</td>
		</tr>
		</table>
	    
	    </form>
	    
	    <div style="font-size:smaller; text-align:center;">Copyright 2011 &middot; Aten Software LLC &middot; Version <?php echo self::VERSION; ?></div>
	</body>
	</html>
	<?php
	}
	// Die if the storeId is invalid
	private function _getStoreInformation()
	{
		// Check format of the ID
		if(0 == preg_match('|^\d+$|', $this->_storeId))
		{
			die('ERROR: The specified Store is not formatted correctly: '.$this->_storeId);
		}
		
		try
		{
			// Get the store object
			$store = Mage::app()->getStore($this->_storeId);
			// Load the store information
			$this->_websiteId = $store->getWebsiteId();
			$this->_webBaseUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
			$this->_mediaBaseUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
		}
		catch (Exception $e)
		{
			die('ERROR: Error getting store information for Store='.$this->_storeId.". The store probably does not exist. ".get_class($e)." ".$e->getMessage());
		}
	}
	// Die if password is invalid
	private function _checkPassword($Password)
	{
		// Check if a password is defined
		if(self::PASSWORD == '')
		{
		//	die('ERROR: A blank password is not allowed.  Edit this script and set a password.');
		}
		// Check the password
		if($Password != self::PASSWORD)
		{
		//	die('ERROR: The specified password is invalid.');
		}
	}
	// Returns true if running CLI mode
	private function _isCLI()
	{
		$sapi_type = php_sapi_name();
		return (substr($sapi_type, 0, 3) == 'cli');
	}
}
?>