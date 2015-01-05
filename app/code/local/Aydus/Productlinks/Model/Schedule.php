<?php

/**
 * Schedule model
 *
 * @category    Aydus
 * @package     Aydus_Core
 * @author		Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Model_Schedule extends Mage_Core_Model_Abstract
{
		
	/**
	 * Initialize resource model
	 */
	protected function _construct()
	{
        parent::_construct();
        
		$this->_init('aydus_productlinks/schedule');
	}	
	
	
}