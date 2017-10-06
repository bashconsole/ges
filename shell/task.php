<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Mage
 * @package     Mage_Shell
 * @copyright  Copyright (c) 2006-2017 X.commerce, Inc. and affiliates (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once 'abstract.php';

//Set memory limit
ini_set("memory_limit","1024M");

/**
 * Magento Log Shell Script
 *
 * @category    Mage
 * @package     Mage_Shell
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_Shell_Task extends Mage_Shell_Abstract
{
    /**
     * File name
     *
     * @var string
     */
    protected $_filename;


    /**
     * Get products id from file
     *
     * @return array
     */
    protected function _getProductIDs()
    {
        try {
            $content = file_get_contents($this->_filename);
            if(!$content) {
                Mage::throwException(Mage::helper('importexport')->__('File read error'));
            }
            $arr = explode(',', $content);
        } catch (Mage_Core_Exception $e) {
            echo $e->getMessage() . "\n";
        } catch (Exception $e) {
            echo $e . "\n";
        }
                
        return $arr;
    }
    
    protected function _loadProducts(array $entities) {
        $productCollection = Mage::getModel('catalog/product')->setStoreId(Mage::app()->getStore()->getId())->getCollection()
            ->addAttributeToSelect('*') 
            ->addFieldToFilter('entity_id', array('in'=> $entities));
        $productCollection->load();

        return $productCollection;
    }
    
    protected function _getCategoryIds(array $products_ids)
    {
    
//     SELECT category_id, product_id FROM `catalog_category_product` WHERE (product_id in (874, 875, 877)) order by product_id
    
        $resource = Mage::getSingleton('core/resource');
	$readConnection = $resource->getConnection('core_read');
        
        $select = $readConnection->select()
            ->from($resource->getTableName('catalog/category_product'), 'category_id')
            ->where('product_id in (?)', implode(',', $products_ids));
            
            echo $select->__tostring();

        return $readConnection->fetchAll($select);
    }

    /**
     * Run script
     *
     */
    public function run()
    {
        if ($this->getArg('file')) {
            $this->_filename = $this->getArg('file');
            
            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
            
            $status = [
                    'enabled' => [],
                    'disabled' => []
            ];
            
            $images = [];
            $categories = [];
            
            
            $ids = $this->_getProductIDs();
            $products = $this->_loadProducts($ids);
            
            
            
            foreach($products as $product) {
                    if($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                        $status['enabled'][] = $product->getId();
                    }elseif($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
                        $status['disabled'][] = $product->getId();
                    }

                    $mediaApi = Mage::getModel('catalog/product_attribute_media_api');                    
                    $images[$product->getId()] = sizeof($mediaApi->items($product->getId()));
                    $categories[$product->getId()] = implode(',', $product->getCategoryIds());
                    
            }
            
            
            
            
            
            print_r(
                array(
                    'status' => array(
                        'enabled' => implode(',', $status['enabled']),
                        'disabled' => implode(',', $status['disabled'])
                    )
                )
            );

            print_r(
                    array(
                        'images' => $images
                    )
            );      
            
            print_r(
                    array(
                        'categories' => $categories
                    )
            ); 
            
        } else {
            echo $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f task.php -- [options]
        php shell/task.php --file /path/to/the/file.txt

  --file <filename>    The file is a simple text file that contains a list of comma separated product ids.
  help                 This help

USAGE;
    }
}

$shell = new Mage_Shell_Task();
$shell->run();
