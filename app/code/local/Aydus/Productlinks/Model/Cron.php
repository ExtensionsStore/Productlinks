<?php

/**
 * Schedule
 *
 * @category    Aydus
 * @package     Aydus_Productlinks
 * @author      Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Model_Cron 
{
	/**
	 * Schedule link generation jobs based on frequency and time
	 * Jobs will be added to cron_schedule table
	 * 
	 * @param Mage_Cron_Model_Schedule $schedule
	 * @return string
	 */
	public function scheduleJobs($schedule)
	{
		$scheduler = Mage::helper('aydus_productlinks/scheduler');
		$frequency = Mage::getStoreConfig('aydus_productlinks/configuration/frequency');
		$monthDay = Mage::getStoreConfig('aydus_productlinks/configuration/month_day');
		$weekDay = Mage::getStoreConfig('aydus_productlinks/configuration/week_day');
		$startTime = Mage::getStoreConfig('aydus_productlinks/configuration/start_time');
		
		$scheduled = array();
		
		if ($dataType = Mage::getStoreConfig('aydus_productlinks/configuration/related')){
			
			$jobCode = 'aydus_productlinks_related';
			$linkType = 'r';
			$scheduled['Related jobs'] = $scheduler->generateSchedules($linkType, $jobCode, $frequency, $monthDay, $weekDay, $startTime);
		}	
		if ($dataType = Mage::getStoreConfig('aydus_productlinks/configuration/upsell')){
				
			$jobCode = 'aydus_productlinks_upsell';
			$linkType = 'u';
			$scheduled['Upsell jobs'] = $scheduler->generateSchedules($linkType, $jobCode, $frequency, $monthDay, $weekDay, $startTime);
		}		
		if ($dataType = Mage::getStoreConfig('aydus_productlinks/configuration/cross_sell')){
				
			$jobCode = 'aydus_productlinks_cross_sell';
			$linkType = 'c';
			$scheduled['Cross Sell jobs'] = $scheduler->generateSchedules($linkType, $jobCode, $frequency, $monthDay, $weekDay, $startTime);
		}			
		
		if (is_array($scheduled) && count($scheduled)>0){
			$schedules = array();
			foreach ($scheduled as $linkTypeLabel => $datetime){
				$schedules[] = $linkTypeLabel. ' at ' .$datetime;
			}
			
			$return = 'Jobs scheduled: '.implode(', ',$schedules);
			
		} else {
			$return = 'Nothing to schedule';
		}
		
		return $return;
	}
	
	/**
	 * Cron job for related product links generation
	 * @param Mage_Cron_Model_Schedule $schedule
	 */
	public function runRelated($schedule)
	{
		return $this->_runCron('r');
	}
	
	/**
	 * Cron job for related product links generation
	 * @param Mage_Cron_Model_Schedule $schedule
	 */
	public function runUpsell($schedule)
	{
		return $this->_runCron('u');
	}
	
	/**
	 * Cron job for related product links generation
	 * @param Mage_Cron_Model_Schedule $schedule
	 */
	public function runCrossSell($schedule)
	{
		return $this->_runCron('c');
	}
	
	/**
	 * Run the $linkType job
	 * @param string $linkType
	 */
	protected function _runCron($linkType)
	{
		$productLinks = Mage::getModel('aydus_productlinks/productlinks');
		
		return $productLinks->assign($linkType);
	}
}