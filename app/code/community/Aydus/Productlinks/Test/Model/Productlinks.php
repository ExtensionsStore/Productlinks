<?php

/**
 *
 * @category   Aydus
 * @package    Aydus_Productlinks
 * @author     Aydus <davidt@aydus.com>
 */

class Aydus_Productlinks_Test_Model_Productlinks extends EcomDev_PHPUnit_Test_Case_Config
{    
    /**
     * @test
     * @loadFixture
     */
    public function assignTest()
    {
        echo "\nAydus_Productlinks model test started.";
        
        $model = Mage::getModel('aydus_productlinks/productlinks');
        
        //test config
        $this->assertConfigNodeValue('default/aydus_productlinks/configuration/num_order_days',30);
        $this->assertConfigNodeValue('default/aydus_productlinks/configuration/num_linked_products',8);
        $this->assertConfigNodeValue('default/aydus_productlinks/configuration/products_limit',5000);
        
        //test get products
        $products = $model->getProductsCollection();
        $size = $products->getSize();
        $this->assertEquals(12, $size);
        
        $product = $products->getFirstItem();
        $this->assertEquals(1, $product->getId());
        
        //test 3 existing links
        $linkType = 'r';
	    $productLinksData = array();
				
		$productLinksData = $model->getExistingLinks($linkType, $product, $productLinksData);
        $this->assertEquals(3, count($productLinksData));
        
        //test 8 product links
        $linksCollection = $model->getProductLinks($linkType, $product->getId());
        $size = $linksCollection->getSize(); //1 main + 8 new = 9
        $this->assertEquals(9, $size);
        
        //test new links
        $productLinksData = $model->getNewLinks($linkType, $product, $productLinksData);
        $totalLinks = count($productLinksData); //3 existing + 8 new = 11
        $this->assertEquals(11, $totalLinks);
        
        //test saving the links
        $linksCollection = $model->saveLinks($linkType, $product, $productLinksData);
        $size = $linksCollection->getSize();
        $this->assertEquals(11, $size);
        
        echo "\nAydus_Productlinks model test completed.";

    }
        

   
}
