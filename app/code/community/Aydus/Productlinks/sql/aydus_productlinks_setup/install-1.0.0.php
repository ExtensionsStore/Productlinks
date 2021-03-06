<?php

/**
 * Log tables for Productlinks module
 *
 * @category    Aydus
 * @package     Aydus_Productlinks
 * @author      Aydus <davidt@aydus.com>
 */

$installer = $this;
$installer->startSetup();

$productLinksLogTable = "CREATE TABLE IF NOT EXISTS {$this->getTable('aydus_productlinks_log')} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `link_type` enum('r', 'u', 'c'),
  `product_id` int(11) unsigned NOT NULL DEFAULT '0',
  `product_link_id` int(11) unsigned NOT NULL DEFAULT '0',
  `position` int(11) unsigned NOT NULL DEFAULT '0',
  `date_linked` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `linked_product_type` (`link_type`, `product_id`,`product_link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

$installer->run($productLinksLogTable);

$installer->run("CREATE TABLE IF NOT EXISTS {$this->getTable('aydus_productlinks_schedule')} (
`schedule_id` int(11) NOT NULL,
`link_type` enum('r', 'u', 'c'),
PRIMARY KEY ( `schedule_id` )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");


$installer->endSetup();