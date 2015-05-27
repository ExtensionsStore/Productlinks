<?php

/**
 * Product links cron controller
 *
 * @category    Aydus
 * @package     Aydus_Productlinks
 * @author      Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Adminhtml_System_ProductlinksController extends Mage_Adminhtml_Controller_Action
{
	
    /**
     * Run cron
     */
	public function runNowAction()
	{
	    $productLinks = Mage::getModel('aydus_productlinks/productlinks');
	    
	    $dataTypes = array('r'=>'related', 'u'=>'upsell', 'c'=>'cross_sell');
	    
	    foreach ($dataTypes as $linkType => $linkLabel){
	        
	        if ($dataType = Mage::getStoreConfig('aydus_productlinks/configuration/'.$linkLabel)){
	        
	            $message = $productLinks->assign($linkType);
	            
	            if ($message){
	                Mage::getSingleton('adminhtml/session')->addSuccess($message);
	                 
	            } else {
	                Mage::getSingleton('adminhtml/session')->addError("Could not generate links for $linkType");
	                 
	            }
	        
	        }	        
	    }
	    		 
		$this->_redirect('adminhtml/system_config/edit', array('section'=>'aydus_productlinks'));		
	}
	


}
