<?php

/**
 * System configuration form button
 *
 * @category    Aydus
 * @package     Aydus_Productlinks
 * @author      Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Block_Adminhtml_System_Config_Form_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
	{
		$this->setElement($element);
		$url = $this->getUrl('adminhtml/system_productlinks/runNow');

		$html = $this->getLayout()->createBlock('adminhtml/widget_button')
			->setType('button')
			->setClass('scalable')
			->setLabel('Run Now')
			->setOnClick("setLocation('$url')")
			->toHtml();

		return $html;
	}
}
