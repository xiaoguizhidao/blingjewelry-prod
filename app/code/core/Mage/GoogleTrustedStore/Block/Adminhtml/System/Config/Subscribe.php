<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition License
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magentocommerce.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_GoogleTrustedStore
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://www.magentocommerce.com/license/enterprise-edition
 */

/**
 * Custom renderer for subscriptin to group
 *
 */
class Mage_GoogleTrustedStore_Block_Adminhtml_System_Config_Subscribe
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $subscribeLabel = $this->__('Subscribe');
        return <<<HTML
<input id="trustedstore_subscription_for_updates_value" type="text" value="" name="groups[trustedstore][fields][subscription_for_updates][value]" class="input-text validate-email" style="width:190px">
<button type="submit" id="trustedstore_subscription_for_updates_submit" class="disabled" disabled="disabled">$subscribeLabel</button>
<script type="text/javascript">
    document.observe('dom:loaded', function () {
        var trstdSubmit = $('trustedstore_subscription_for_updates_submit');
        var trstdValue = $('trustedstore_subscription_for_updates_value');
        Event.observe('trustedstore_subscription_for_updates_value', 'input', function (e) {
            if (trstdValue.getValue()) {
                enableElement(trstdSubmit);
            } else {
                disableElement(trstdSubmit);
            }
        });
    });
</script>
HTML;
    }
}