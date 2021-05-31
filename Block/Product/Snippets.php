<?php

namespace Loewenstark\Schemaorg\Block\Product;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Context as ContextBlock;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Store\Model\StoreManagerInterface as StoreManager;
use Magento\Catalog\Helper\ImageFactory as CatalogImage;

class Snippets extends AbstractBlock {

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     *
     * @var \Magento\Catalog\Block\Product 
     */
    protected $product;

    /**
     *
     * @var \Magento\Catalog\Helper\Data 
     */
    protected $catalogHelper;

    /**
     *
     * @var \Magento\Store\Model\StoreManager
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $imageHelperFactory;

    protected $breadcrumb = null;

    /**
     * 
     * @param \Magento\Framework\View\Element\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
            ContextBlock $context,
            Registry $registry,
            CatalogHelper $catalogHelper,
            StoreManager $storeManager,
            CatalogImage $imageHelperFactory,
            array $data = array()
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->catalogHelper = $catalogHelper;
        $this->storeManager = $storeManager;
        $this->imageHelperFactory = $imageHelperFactory;
    }

    /**
     * 
     * @return string
     */
    protected function _toHtml() {
        if (!$this->getProduct() || !$this->getProduct()->getId()
                || $this->getProduct()->getStatus() == 2)
        {
            return '';
        }
        return $this->getBreadCrumbHtml()."\n".$this->getProductHtml();
    }

    /**
     * 
     * @return \Magento\Catalog\Block\Product
     */
    public function getProduct()
    {
        if (is_null($this->product)) {
            $this->product = $this->registry->registry('product');
        }
        return $this->product;
    }

    /**
     * 
     * @return string
     */
    public function getProductHtml()
    {
        $array = array (
            '@context' => 'http://schema.org/',
            '@type' => 'Product',
            'name' => $this->getProduct()->getName(),
            'image' => $this->imageHelperFactory->create()->init($this->getProduct(), 'product_base_image')->getUrl(),
            'description' => $this->getDescription($this->getProduct()->getDescription()),
            'sku' => $this->getProduct()->getSku(),
            'gtin8' => $this->getProduct()->getEan(),
        );
        if ($this->getProduct()->getManufacturer())
        {
            $array['brand'] = array (
                '@type' => 'Thing',
                'name' => $this->getProduct()->getAttributeText('manufacturer'),
            );
        }
        $array['offers'] = array (
            '@type' => 'Offer',
            'priceCurrency' => $this->getCurrencyCode(),
            'price' => number_format($this->getProduct()->getFinalPrice(), 2, '.', ''),
            'availability' => 'http://schema.org/InStock',
            'url' => $this->getProduct()->getProductUrl(),
        );
        if (!$this->getProduct()->isSaleable())
        {
            $array['offers']['availability'] = 'http://schema.org/SoldOut';
        }
        return '<script type="application/ld+json">'.$this->jsonEncode($array).'</script>';
    }

    protected function getDescription($string)
    {
        $string = html_entity_decode(strip_tags($string));
        $string = preg_replace('/\s+/', ' ',$string);
        $length = mb_strlen($string, 'UTF-8');
        $string = mb_substr(trim($string), 0, 140, 'UTF-8');
        if ($length > 140)
        {
            $string = $string.'...';
        }
        return trim($string);
    }
    
    /**
     * 
     * @return type
     */
    public function getBreadCrumbHtml()
    {
        
        $breadcrumb = array(
            '@context'        => 'http://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => array(),
        );
        $i = 0;
        foreach($this->getBreadcrumbArray() as $_item)
        {
            $i++;
            $breadcrumb['itemListElement'][] = array(
                '@type'    => 'ListItem',
                'position' => $i,
                'item' => array(
                    '@id'  => $_item['link'],
                    'name' => $_item['label'],
                )
            );
        }
        return '<script type="application/ld+json">' . $this->jsonEncode($breadcrumb).'</script>';
    }

    /**
     * 
     * @return type
     */
    protected function getBreadcrumbArray()
    {
        if(is_null($this->breadcrumb))
        {
            $breadcrumb = $this->catalogHelper->getBreadcrumbPath();
            array_pop($breadcrumb);
            if (count($breadcrumb) == 0)
            {
                $categoryCollection = clone $this->getProduct()
                        ->getCategoryCollection();
                $categoryCollection->clear();
                $categoryCollection->addAttributeToSort('level', $categoryCollection::SORT_ORDER_DESC)
                        ->addAttributeToFilter('path', array('like' => "1/" . $this->storeManager->getStore()->getRootCategoryId() . "/%"))
                        ->addIsActiveFilter()
                        ->joinUrlRewrite()
                        ->setPageSize(1)
                        ->setCurPage(1);
                $breadcrumbCategories = $categoryCollection->getFirstItem()
                        ->getParentCategories();
                $breadcrumb = [];
                foreach ($breadcrumbCategories as $category)
                {
                    $breadcrumb[] = array("label" => $category->getName(), "link" => $category->getUrl());
                }
            }
            $this->breadcrumb = $breadcrumb;
        }
        return $this->breadcrumb;
    }

    protected function jsonEncode($string)
    {
        $options = JSON_UNESCAPED_UNICODE;
        $json = json_encode($string, $options);
        $json = str_replace('\\/','/', $json);      // less data
        $json = str_replace("\\u00a0", ' ', $json); // json space to normal space
        $json = str_replace("\\ ", ' ', $json);     // remove multiple slashes
        $json = str_replace("\\", "\\\\", $json);   // remove multiple slashes
        return $json;
    }
    
    protected function getCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }
}