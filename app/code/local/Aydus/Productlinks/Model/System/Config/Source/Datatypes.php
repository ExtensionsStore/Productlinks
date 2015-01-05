<?php

/**
 * Data types for system config (also bought, also viewed)
 *
 * @category    Aydus
 * @package     Aydus_Productlinks
 * @author      Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Model_System_Config_Source_Datatypes  
{
	const ALSO_BOUGHT = 'b';
	const ALSO_VIEWED = 'v';
	
	protected $_options;
	
	public function toOptionArray($isMultiselect=false)
	{
		if (!$this->_options) {
			$this->_options = array(
				array('value' => self::ALSO_BOUGHT, 'label'=>Mage::helper('aydus_productlinks')->__('Also Bought (Sales Data)')),
				array('value' => self::ALSO_VIEWED, 'label'=>Mage::helper('aydus_productlinks')->__('Also Viewed (Visitor Data)')),
			);
		}
	
		$options = $this->_options;
		if(!$isMultiselect){
			array_unshift($options, array('value'=>'', 'label'=> Mage::helper('adminhtml')->__('--Please Select--')));
		}
	
		return $options;
	}

}