<?php

/**
 * Assign product links (upsell, related, cross-sell) 
 *
 * @category    Aydus
 * @package     Aydus_Productlinks
 * @author      Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Model_Productlinks extends Mage_Core_Model_Abstract 
{
		
	/**
	 * Update product links
	 * 
	 * @param string $linkType
	 * @param string $dataType
	 * @return array
	 */
	public function assign($linkType)
	{
		$read = Mage::getSingleton('core/resource')->getConnection('core_read');
		$write = Mage::getSingleton('core/resource')->getConnection('core_write');
		$prefix = Mage::getConfig()->getTablePrefix();
		$logTable = $prefix."aydus_productlinks_log";
		//clear out the previous day's entries
		$curDate = date('Y-m-d');
		$write->query("DELETE FROM $logTable WHERE link_type = '$linkType' AND date_updated < '$curDate'");
		$count = $read->fetchOne("SELECT COUNT(*) FROM $logTable");
		if ($count == 0){
			$write->query("TRUNCATE TABLE $logTable");
		}
				
		$products = $this->_getProductsToLink();
		
		$this->_setIndexerMode("manual");
		
		$productsCount = $products->getSize();
		$productsProcessed = 0;
		
		if ($productsCount > 0){
			
			$time_start = microtime(true);
			$numLinkedProducts =  Mage::getStoreConfig('productlinks/configuration/num_linked_products');
			
			foreach ($products as $product){
				
				$productId = $product->getId();
				$product->load($productId);
				$productLinksData = array();
				$productLinkIds = array();
				$now = date('Y-m-d H:i:s');
				
				switch ($linkType){
					case 'u' :
						$productLinkIds = $product->getUpSellProductIds();
						break;
					case 'r' :
						$productLinkIds = $product->getRelatedProductIds();
						break;
					case 'c' :
						$productLinkIds = $product->getCrossSellProductIds();
						break;
				}
								
				$productLinks = $this->_getProductLinks($linkType, $productId);
				
				//assign links
				if ($productLinks && $productLinks->getSize()){
											
					if ($productLinks->getSize() <= $numLinkedProducts + 1){
						$productLinks->setPageSize(100);
					}else {
						$productLinks->setPageSize($numLinkedProducts);
					}
					
					$selectStr = (string)$productLinks->getSelect();
					
					$position = 0;

					foreach ($productLinks as $productLink){
						
						$productLinkId = $productLink->getProductLinkId();

						if ($productId == $productLinkId){
							continue;
						}
														
						$position++;
						$productLinksData[$productLinkId] = array( 'position' => $position );
							
                        //log the linked product
                        $sql = "INSERT INTO $logTable
                            (link_type, product_id, product_link_id, date_created, date_updated, position)
                            VALUES('$linkType', '$productId', '$productLinkId', '$now', '$now', '$position')";
                        
						try {
                        	$write->query($sql);
                        	 
                        } catch(Exception $e){
                        	Mage::log($e->getMessage(),null, 'aydus_productlinks.log');
                        }
					}
				}
				
				//overwrite current links
				if (count($productLinkIds) > 0 || count($productLinksData) > 0){
					
					try {
						if ($linkType == 'u'){
							$product->setUpSellLinkData($productLinksData);
						} else if ($linkType == 'r') {
							$product->setRelatedLinkData($productLinksData);
						} else if ($linkType == 'c') {
							$product->setCrossSellLinkData($productLinksData);
						}
							
						$product->save();
							
					} catch(Exception $e){
					
						Mage::log($e->getMessage(),null, 'aydus_productlinks.log');
						Mage::log($product->getId()."-".implode(',',array_keys($productLinksData)),null, 'aydus_productlinks.log');
						if (php_sapi_name() == 'cli'){
							echo $e->getMessage()."\n";
						}
					}
				} 
				
				//log as processed
				$sql = "INSERT INTO $logTable
				(link_type, product_id, product_link_id, date_created, date_updated, position)
				VALUES('$linkType', '$productId', 0, '$now', '$now', 0)";
					
				try {
					$write->query($sql);
				
				} catch(Exception $e){
					Mage::log($e->getMessage(),null, 'aydus_productlinks.log');
				}
						
				$time_end = microtime(true);
				$time = $time_end - $time_start;
				$productsProcessed++;
				if (php_sapi_name() == 'cli'){
					echo "$productsProcessed-$productsCount-$time\n";
				}
			}
		}

		$this->_setIndexerMode();
		$productsLinked = $read->fetchOne("SELECT COUNT(DISTINCT product_id) FROM $logTable WHERE link_type = '$linkType' AND product_link_id > 0");
		$linkedProducts = $read->fetchOne("SELECT COUNT(*) FROM $logTable WHERE link_type = '$linkType' AND product_link_id > 0");
		
		$missed = $productsCount - $productsProcessed;
		
		$result = "Job has completed. Products: $productsCount, processed: $productsProcessed, missed: $missed; products linked: $productsLinked, linked products: $linkedProducts";
		if (php_sapi_name() == 'cli'){
			echo "$result\n";
		}
		Mage::log($result, null, 'aydus_productlinks.log');
		
		return $result;
	}
	
	/**
	 * Get product collection, limited by not already processed (and logged) and not a child product
	 *
	 * @return Mage_Catalog_Model_Resource_Product_Collection
	 */
	protected function _getProductsToLink()
	{
		$collection = $this->getProductsCollection();
		$select = $collection->getSelect();
		$prefix = Mage::getConfig()->getTablePrefix();
		$select->where('`e`.`entity_id` NOT IN (SELECT DISTINCT(`l`.`product_id`) FROM `'.$prefix.'aydus_productlinks_log` AS l)');
		$select->where('`e`.`entity_id` NOT IN (SELECT `sl`.`product_id` FROM `'.$prefix.'catalog_product_super_link` AS sl)');
		$select->where('`e`.`entity_id` NOT IN (SELECT `r`.`child_id` FROM `'.$prefix.'catalog_product_relation` AS r)');
		$productsLimit = Mage::getStoreConfig('aydus_productlinks/configuration/products_limit');
		$collection->setPageSize($productsLimit);
		
		return $collection;
	}

	/**
	 * Get product collection, limited by status, visibility
	 *
	 * @return Mage_Catalog_Model_Resource_Product_Collection
	 */	
	public function getProductsCollection()
	{
		$collection = Mage::getResourceModel('catalog/product_collection');
		$collection->addAttributeToFilter('status',1);
		$collection->addAttributeToFilter('visibility',4);
		$collection->setOrder('entity_id', 'ASC');
		
		return $collection;
	}
	
	/**
	 * 
	 * @param int $linkType
	 * @param int $productId
	 * @return Mage_Catalog_Model_Resource_Product_Collection
	 */
	protected function _getProductLinks($linkType, $productId)
	{
		$collection = null;
		$numOrderDays = Mage::getStoreConfig('aydus_productlinks/configuration/num_order_days');
		$sinceDate = date("Y-m-d", time() - $numOrderDays * 86400);
		switch ($linkType){
			case 'r' :
				$linkTypeName = 'related';
				break;
			case 'u' :
				$linkTypeName = 'upsell';
				break;
			case 'c' :
				$linkTypeName = 'cross_sell';
				break;
		}
		$dataType = Mage::getStoreConfig("aydus_productlinks/configuration/$linkTypeName");
		
		switch ($dataType){
			
			//Also bought
			case 'b' :
				$collection = Mage::getResourceModel('sales/order_item_collection');
				$select = $collection->getSelect();
				//get only the columns we need
				$select->reset(Zend_Db_Select::COLUMNS)->columns(array('main_table.order_id', 'main_table.product_id'));
				//join with self on order_id to get also bought items of this product
				$select->join(array(
						"join_table"=>Mage::getSingleton('core/resource')->getTableName('sales/order_item')),
						"`main_table`.order_id = `join_table`.order_id",
						array("product_link_id" => "product_id")
				);
				//join with product table to ensure product is still available
				$select->join(array(
						"product_table"=>Mage::getSingleton('core/resource')->getTableName('catalog/product')),
						"`join_table`.product_id = `product_table`.entity_id",
						array("sku")
				);
				$collection->addAttributeToFilter('`main_table`.created_at',array("from"=>$sinceDate));
				$collection->addAttributeToFilter('`main_table`.product_id', $productId);
				//$selectStr = (string)$select;
												
				break;
				
			//Also viewed
			case 'v' :
				$collection = Mage::getModel('reports/product_index_viewed')->getCollection();
				$select = $collection->getSelect();
				$select->join(array(
						"index_table" => 'report_viewed_product_index'),
						"`index_table`.product_id = `e`.entity_id AND `index_table`.visitor_id IS NOT NULL AND `index_table`.`added_at` >= '$sinceDate'",
						array("*")
				);
				//join with self on visitor_id to get also viewed items of this product
				$select->join(array(
						"join_table" => 'report_viewed_product_index'),
						"`index_table`.visitor_id = `join_table`.visitor_id AND `join_table`.visitor_id IS NOT NULL AND `join_table`.`added_at` >= '$sinceDate'",
						array("product_link_id" => "product_id", 'num_times_viewed' => "(SELECT COUNT(*) FROM report_viewed_product_index WHERE product_id = product_link_id AND added_at >= '$sinceDate' AND visitor_id IS NOT NULL)")
				);
				$select->group('product_link_id');
				$select->order('num_times_viewed DESC');
				$collection->addAttributeToFilter('entity_id', $productId);
				//$selectStr = (string)$select;
				
				break;
				
			//Wishlist
			case 'w' :
				//@todo ?
				break;
		}
		
		return $collection;
	}
	
	protected function _setIndexerMode($selectMode = NULL) 
	{
		$validModes = array("manual", "real_time");
		
		if ($selectMode !== NULL && !in_array($selectMode, $validModes)){
			throw new Exception("Invalid mode $selectMode. Valid indexer modes are ". implode(",",$validModes));
		}
		
		$mode = ($selectMode === NULL || $selectMode === "real_time") ? Mage_Index_Model_Process::MODE_REAL_TIME : Mage_Index_Model_Process::MODE_MANUAL;
		
		$pCollection = Mage::getSingleton('index/indexer')->getProcessesCollection();
		foreach ($pCollection as $process) {
			$process->setMode($mode)->save();
			if ($mode == Mage_Index_Model_Process::MODE_REAL_TIME){
				$process->reindexAll();
			}
		}
		
		if ($mode == Mage_Index_Model_Process::MODE_REAL_TIME){
			$type = "block_html";
			$tags = Mage::app()->getCacheInstance()->cleanType($type);
			Mage::dispatchEvent('adminhtml_cache_refresh_type', array('type' => $type));
		}
	}
}