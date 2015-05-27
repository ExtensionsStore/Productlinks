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
    protected $_read;
    protected $_write;
    protected $_logTable;
    
    protected $_position = 0;
    protected $_numLinkedProducts; 
    
    protected function _construct()
    {
        $this->_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $this->_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $prefix = Mage::getConfig()->getTablePrefix();
        $this->_logTable = $prefix."aydus_productlinks_log";
		$this->_numLinkedProducts =  Mage::getStoreConfig('aydus_productlinks/configuration/num_linked_products');
    }
    
	/**
	 * Update product links
	 * 
	 * @param string $linkType
	 * @param string $dataType
	 * @return array
	 */
	public function assign($linkType)
	{			
		$timeStart = microtime(true);
	    $products = $this->_getProductsToLink();
				
		$productsCount = $products->getSize();
		$productsProcessed = 0;
		
		if ($productsCount > 0){
			
			foreach ($products as $product){
				
				$productId = $product->getId();
				$productLinksData = array();
				$this->_position = 0;
				
				//existing links
				$productLinksData = $this->getExistingLinks($linkType, $product, $productLinksData);
								
				//new links
				$productLinksData = $this->getNewLinks($linkType, $product, $productLinksData);
				
				//save links
				$productLinksData = $this->saveLinks($linkType, $product, $productLinksData);
				
				//log as processed
                $this->_logProductLink($linkType, $productId, 0, 0);
                $productsProcessed++;
                if (php_sapi_name() == 'cli'){
                    echo "$productsProcessed. product id: $productId, linked products: ".count($productLinksData)."\n";
                }                
			}
			
		}

		$productsLinked = $this->_read->fetchOne("SELECT COUNT(DISTINCT product_id) FROM $this->_logTable WHERE link_type = '$linkType' AND product_link_id > 0");
		$linkedProducts = $this->_read->fetchOne("SELECT COUNT(*) FROM $this->_logTable WHERE link_type = '$linkType' AND product_link_id > 0");
		$missed = $productsCount - $productsProcessed;
		$timeEnd = microtime(true);
		$totalTime = $timeEnd - $timeStart;
		
		$result = "Job has completed. Products: $productsCount, processed: $productsProcessed, missed: $missed; products linked: $productsLinked, linked products: $linkedProducts, total time: $totalTime";
		if (php_sapi_name() == 'cli'){
			echo "$result\n";
		}
		Mage::log($result, null, 'aydus_productlinks.log');
		
		return $result;
	}
	
	/**
	 * 
	 * @param string $linkType
	 * @param Mage_Catalog_Model_Product $product
	 * @param array $productLinksData
	 * @return array
	 */
	public function getExistingLinks($linkType, $product, $productLinksData)
	{
		$productId = $product->getId();
		
	    switch ($linkType){
	        case 'u' :
	            $productLinks = $product->getUpSellProducts();
	            break;
	        case 'r' :
	            $productLinks = $product->getRelatedProducts();
	            break;
	        case 'c' :
	            $productLinks = $product->getCrossSellProducts();
	            break;
	    }
	    	    
	    if (is_array($productLinks) && count($productLinks)>0){
	    
	        foreach ($productLinks as $productLink){
	    
	            $productLinkId = $productLink->getId();
	    
	            $sql = "SELECT id FROM $this->_logTable WHERE link_type = '$linkType' AND product_id = '$productId' AND product_link_id = '$productLinkId'";
	            $id = (int)$this->_read->fetchOne($sql);
	             
	            //don't include the ones we already linked
	            if (!$id){
	                 
	                $position = $productLink->getPosition();
	                $position = ($position) ? $position : ++$this->_position;
	                $productLinksData[$productLinkId] = array( 'position' => $position );
	                	
	            }
	    
	        }
	    
	        //get last position
	        if (count($productLinksData)>0){
	    
	            foreach ($productLinksData as $productLinkData){
	    
	                if ($productLinkData['position'] > $this->_position){
	                    $this->_position = $productLinkData['position'];
	                }
	    
	            }
	             
	        }
	    
	    }	  

	    return $productLinksData;
	}
	
	/**
	 *
	 * @param string $linkType
	 * @param Mage_Catalog_Model_Product $product
	 * @param array $productLinksData
	 * @return array
	 */
	public function getNewLinks($linkType, $product, $productLinksData)
	{
		$productId = $product->getId();
		
	    $this->_write->query("DELETE FROM $this->_logTable WHERE link_type ='$linkType' AND product_id = '$productId'  ");
	    $productLinks = $this->getProductLinks($linkType, $productId);
	    
	    if ($productLinks && $productLinks->getSize()){
	        	
	        if ($productLinks->getSize() <= $this->_numLinkedProducts + 1){
	            	
	            $productLinks->setPageSize(100);
	    
	        } else {
	            	
	            $productLinks->setPageSize($this->_numLinkedProducts);
	        }
	        	
	        foreach ($productLinks as $productLink){
	    
	            $productLinkId = $productLink->getProductLinkId();
	    
	            if ($productId == $productLinkId || in_array($productLinkId, array_keys($productLinksData))){
	                continue;
	            }
	    
	            $this->_position++;
	            $productLinksData[$productLinkId] = array( 'position' => $this->_position );
	    
	            $this->_logProductLink($linkType, $productId, $productLinkId, $this->_position);
	    
	        }
	    
	    }	    
	    
	    return $productLinksData;
	}
	
	/**
	 *
	 * @param string $linkType
	 * @param Mage_Catalog_Model_Product $product
	 * @param array $productLinksData
	 * @return Mage_Catalog_Model_Resource_Product_Link_Product_Collection
	 */	
	public function saveLinks($linkType, $product, $productLinksData)
	{
	    $linksCollection = null;
		$productId = $product->getId();
		
	    if (count($productLinksData) > 0){
	    
	        $productLink = Mage::getModel('catalog/product_link');
	        	
	        try {
	            	
	            if ($linkType == 'u'){
	    
	                $product->setUpSellLinkData($productLinksData);
	                $productLink->useUpSellLinks();
	                	
	            } else if ($linkType == 'r') {
	    
	                $product->setRelatedLinkData($productLinksData);
	                $productLink->useRelatedLinks();
	                	
	            } else if ($linkType == 'c') {
	    
	                $product->setCrossSellLinkData($productLinksData);
	                $productLink->useCrossSellLinks();
	            }
	            	
	            $productLink->saveProductRelations($product);
	            $linksCollection = Mage::getModel('catalog/product_link')->getCollection();
	            $linksCollection->addFieldToFilter('product_id', $productId);
	            	
	        } catch(Exception $e){
	            	
	            Mage::log($e->getMessage(),null, 'aydus_productlinks.log');
	            Mage::log($productId."-".implode(',',array_keys($productLinksData)),null, 'aydus_productlinks.log');
	            if (php_sapi_name() == 'cli'){
	                echo $e->getMessage()."\n";
	            }
	        }
	    }	   

	    return $linksCollection;
	}
	
	/**
	 * 
	 * @param string $linkType
	 * @param int $productId
	 * @param int $productLinkId
	 * @param int $position
	 */
	protected function _logProductLink($linkType, $productId, $productLinkId=0, $position=0)
	{
	    $now = date('Y-m-d H:i:s');
	    
        $sql = "INSERT INTO $this->_logTable
            (link_type, product_id, product_link_id, position, date_linked)
	        VALUES('$linkType', '$productId', '$productLinkId', '$position', '$now')";

	    try {
	        $this->_write->query($sql);
	    
	    } catch(Exception $e){
	        Mage::log($e->getMessage(),null, 'aydus_productlinks.log');
	        Mage::log($sql,null, 'aydus_productlinks.log');
	    }	    
	}
		
	/**
	 * Get product collection, limited by not already processed (and logged) and not a child product
	 *
	 * @return Mage_Catalog_Model_Resource_Product_Collection
	 */
	protected function _getProductsToLink()
	{
	    $curDate = date('Y-m-d');
		$collection = $this->getProductsCollection();
		$select = $collection->getSelect();
		$prefix = Mage::getConfig()->getTablePrefix();
		$select->where('`e`.`entity_id` NOT IN (SELECT DISTINCT(`l`.`product_id`) FROM `'.$prefix.'aydus_productlinks_log` AS l WHERE date_linked >= "'.$curDate.'")');
		$select->where('`e`.`entity_id` NOT IN (SELECT `sl`.`product_id` FROM `'.$prefix.'catalog_product_super_link` AS sl)');
		$select->where('`e`.`entity_id` NOT IN (SELECT `r`.`child_id` FROM `'.$prefix.'catalog_product_relation` AS r)');
		$productsLimit = Mage::getStoreConfig('aydus_productlinks/configuration/products_limit');
		$collection->setPageSize($productsLimit);
		
		$selectStr = (string)$collection->getSelect();
		
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
	 * Get product links by configured type
	 * 
	 * @param int $linkType
	 * @param int $productId
	 * @return Mage_Catalog_Model_Resource_Product_Collection
	 */
	public function getProductLinks($linkType, $productId)
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
						array("active_product_id" => 'entity_id')
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
	
}