<?php
/**
 * Celebros Qwiser - Magento Extension
 *
 * @category    Celebros
 * @package     Celebros_Salesperson
 * @author		Omniscience Co. - Dan Aharon-Shalom (email: dan@omniscience.co.il)
 *
 */
class Celebros_salesperson_Block_Layer_View extends Mage_Core_Block_Template
{
	protected function getQwiserSearchResults(){


		$sp = Mage::helper('salesperson');
		$api = $sp->getSalespersonApi();
		if(!$api->results)
		{
			$url = $_SERVER['REQUEST_URI'];
			if(strpos($url, '-i-') !== false) return false;
			$q = trim(str_replace(array('/dev/', '-', '.html', '/'), ' ', preg_replace('/\-[a-z]\-[0-9]*.*/', '', $url)));
			$page = Mage::getSingleton('cms/page');
			$desc = $page->_data['meta_description']; 
			$Cname = Mage::getSingleton('catalog/layer')->getCurrentCategory()->name;
			//var_dump($Cname); 
			//var_dump($q); exit();


			if(strpos($desc,'|')>0)
				$q = substr($desc,strpos($desc,'|'));
			$q = trim(str_replace(array('|', 'index.php', '.html', '/'), ' ', $q));
			if(!$q)
				$q = 'category'; 
			$q = str_replace('view all ', '', $q);
			//echo '<!--Query: ' . $q . '-->';
			$query = Mage::getModel('catalogsearch/query')
						->loadByQuery($q);

			$query->save();
			$order = 'id'; //$sp->getDefaultSortByField();
			$asc = false;
			$num = true;

			if ($q=='new arrivals' || $q=='clearance sale' || $q=='oos')
			{
			$results = $api->SearchAdvance($q,'','','','','1',$order,$num,$asc);
			}
			else
			{
			$results = $api->SearchAdvance($Cname,'','','','','1',$order,$num,$asc);
			}

			if ($salespersonSearch->results) {var_dump($Cname); exit();
				$sp->prepare($Cname, $results->GetRelevantProductsCount());
			}
			$s = false; //Mage::getSingleton('salesperson/session');
			if($s)
				$s->setSearchHandle($results->GetSearchHandle());
			//$this->_getSession()->setSearchHandle($results->GetSearchHandle());
			// Save the ssid in the current session for anlx in the product page
			//$this->_getSession()->setSearchSessionId($results->SearchInformation->SessionId);	
		
		}
    	if($api->results)
    		return $api->results;
    }
    /**

    /**
     * Prepare child blocks
     *
     * @return Celebros_Salesperson_Block_Layer_View
     */
    protected function _prepareLayout()
    {
		//$this->getQwiserSearchResults();
		//print_r(Mage::helper('salesperson')->getSalespersonApi()->results);
		$stateBlock = $this->getLayout()->createBlock('salesperson/layer_state')
            ->setLayer($this->getLayer());
        $this->setChild('layer_state', $stateBlock);
            
        return parent::_prepareLayout();
    }

    /**
     * Get layer object
     *
     * @return Celebros_Salesperson_Model_Layer
     */
    public function getLayer()
    {
        return Mage::getSingleton('salesperson/layer');
    }

	//public function toHtml() { return '<h1>Layout Block View</h1>'; }

    /**
     * Get layered navigation state html
     *
     * @return string
     */
    public function getStateHtml()
    {
        return $this->getChildHtml('layer_state');
    }
    
    /**
     * Get all layer filters
     *
     * @return array
     */
    public function getFilters()
    {
		if(!$this->getQwiserSearchResults()||!$this->getQwiserSearchResults()->Questions)
			return false;
				$questions = $this->getQwiserSearchResults()->Questions->GetAllQuestions();
		
        $filters = array();
        if ($questions){
	        foreach($questions as $question){
	           $filters[] = $question;
	        }
        }

        return $filters;
    }

	public function getFilterSelections()
	{
		return $this->getQwiserSearchResults()->SearchPath;
	}
    
    public function isMultiSelect() {
    	return Mage::getStoreConfigFlag('salesperson/display_settings/enable_non_lead_answers_multiselect');
    } 
    
    public function isAnsweredAnswer($questionId, $answerId) {
    	$bIsAnsweredAnswer = false;
			$state = Mage::getSingleton('salesperson/layer')->getState();
			$answeredQuestions =  $state->getFilters();

      foreach($answeredQuestions as $question){
          if($question["questionId"] != $questionId) continue;
          $answeredAnsweres = $question['answers']->Items;
          foreach($answeredAnsweres as $answer){
          	if($answer->Id == $answerId) return true; 
          	else continue;
          }
      }
      
      return $bIsAnsweredAnswer;
    }     

    public function getStateRemoveUrl($answerId) {
    	$stateBlock = $this->getLayout()->createBlock('salesperson/layer_state');
    	return $stateBlock->getStateRemoveUrl($answerId);
    } 
    
    public function answerQuestionUrl($answerId){
    	$urlParams = array();
		$urlParams['_direct'] = 'salesperson/result/change';
        $urlParams['_current']  = true;
        $urlParams['_escape']   = true;
        $urlParams['_use_rewrite']   = true;
        $urlParams['_query']    = array(
        	'searchHandle' => $this->getQwiserSearchResults()->GetSearchHandle(),
        	'salespersonaction' => 'answerQuestion',
        	'answerId' => $answerId,
        );
        $url =  Mage::getUrl('*/*/change', $urlParams);
        if (preg_match("/p==*\d/", $url)){
       	 $url = preg_replace("/p==*\d/",'p=1', $url);
        }
        else {
        	$url .= "&p=1";
        }
        return $url;
    }
    
	public function getFilterText($filter,$type){
		if ($type == "nonlead" && Mage::Helper('salesperson')->getNonLeadQuestionsPosition() != 'top'){
	    		return $filter->SideText;
		}
		elseif ($type == "lead"){
			return $filter->SideText;
		}
		return $filter->Text;
    }
    
	public function getMaxLeadAnswers(){
    	return Mage::getStoreConfig('salesperson/display_settings/max_lead_answers');
    }
    
    public function getMaxNonLeadAnswers($q=false){
		if($q && $q->SideText == 'Category') return 255;
    	if (Mage::Helper('salesperson')->getNonLeadQuestionsPosition() == 'left' || Mage::Helper('salesperson')->getNonLeadQuestionsPosition() == 'right'){
    		return Mage::getStoreConfig('salesperson/display_settings/max_non_lead_answers_side_nav');
    	}
    	return Mage::getStoreConfig('salesperson/display_settings/max_non_lead_answers');
    }
    
	public function getMaxNonLeadQuestions(){
    	return Mage::getStoreConfig('salesperson/display_settings/max_non_lead_questions');
    }
    
	public function showProductCountInLeadAnswers(){
    	return Mage::getStoreConfigFlag('salesperson/display_settings/show_product_count_in_lead_answers');
    }
    
    public function showProductCountInNonLeadAnswers(){
    	return Mage::getStoreConfigFlag('salesperson/display_settings/show_product_count_in_non_lead_answers');
    }
    
    

    /**
     * Check availability display layer block
     *
     * @return bool
     */
    public function canShowNoneLeadSideBlock()
    {
        return Mage::Helper('salesperson')->getNonLeadQuestionsPosition() == 'left' || Mage::Helper('salesperson')->getNonLeadQuestionsPosition() == 'right';
    }
    
    public function canShowLeadQuestion(){
    	return Mage::getStoreConfigFlag('salesperson/display_settings/display_lead');
    }

    public function forceLeadQuestion($questionId){
    	$urlParams = array();
        $urlParams['_current']  = true;
        $urlParams['_escape']   = true;
        $urlParams['_use_rewrite']   = true;
        $urlParams['_query']    = array(
        	'searchHandle' => $this->getQwiserSearchResults()->GetSearchHandle(),
        	'salespersonaction' => 'forceQuestion',
        	'questionId' => $questionId,
        );
        return Mage::getUrl('*/*/change', $urlParams);
    }

	public function clearAllSelections()
	{
		$urlParams = array(
			'_query' => array(
				'searchHandle' => $this->getQwiserSearchResults()->GetSearchHandle(),
				'salespersonaction' => 'removeAllAnswers'
			));
        $urlParams['_current']  = true;
        $urlParams['_escape']   = true;
        $urlParams['_use_rewrite']   = true;
		return Mage::getUrl('*/*/change', $urlParams);
	}

	public function clearSelection($answerId)
	{
		$urlParams = array(
			'_query' => array(
				'searchHandle' => $this->getQwiserSearchResults()->GetSearchHandle(),
				'salespersonaction' => 'removeAnswer',
				'answerId' => $answerId
			));
        $urlParams['_current']  = true;
        $urlParams['_escape']   = true;
        $urlParams['_use_rewrite']   = true;
		return Mage::getUrl('*/*/change', $urlParams);
	}

    public function stateHasFilters(){
    	return count($this->getLayer()->getState()->getFilters()) > 0;
    }
    
    public function getCustomPriceAnswerUrl(){
    	$urlParams = array();
        $urlParams['_current']  = true;
        $urlParams['_escape']   = false;
        $urlParams['_use_rewrite']   = true;
        $urlParams['_query']    = array(
        	'searchHandle' => $this->getQwiserSearchResults()->GetSearchHandle(),
        	'salespersonaction' => 'answerQuestion',
        );
        $url =  Mage::getUrl('*/*/change', $urlParams);
        if(strpos($url, "answerId=")){
        	$replace_string = substr($url,strpos($url, "answerId="),strpos($url, '&',strpos($url, "answerId=")) - strpos($url, "answerId="));
        	$url = str_replace($replace_string, '', $url);
        }
        if (preg_match("/p=*\d/", $url)){
       	 $url = preg_replace("/p=*\d/",'p=1', $url);
        }
        else {
        	$url .= "&p=1";
        }
        return $url;
    }
    
    public function getDisplayImageInLeadQuestion(){
    	return Mage::getStoreConfigFlag('salesperson/display_settings/display_image_lead_question');
    }
}