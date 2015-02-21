<?php

/**
 * Schedule resource model
 *
 * @category    Aydus
 * @package     Aydus_Productlinks
 * @author      Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Model_Resource_Schedule extends Mage_Core_Model_Resource_Db_Abstract
{
	
	protected function _construct()
	{
		$this->_init('aydus_productlinks/schedule', 'schedule_id');
		$this->_isPkAutoIncrement = false;
	}
	
}

