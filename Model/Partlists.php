<?php
declare(strict_types=1);

/**
 * Partlists domain service
 *
 * Provides helpers to fetch linked products and link collections
 * for the custom "partlists" product link type.
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Model;

use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Link\Collection as LinkCollection;
use Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection as ProductLinkProductCollection;
use Magento\Framework\DataObject;

class Partlists extends DataObject
{
    /**
     * Product link instance (points to the custom "partlists" link type).
     *
     * @var LinkModel
     */
    private LinkModel $linkInstance;

    /**
     * @param LinkModel $productLink Link model used to resolve collections for the partlists type
     */
    public function __construct(LinkModel $productLink)
    {
        $this->linkInstance = $productLink;
        parent::__construct();
    }

    /**
     * Retrieve link instance configured for the "partlists" link type.
     *
     * @return LinkModel
     */
    public function getLinkInstance(): LinkModel
    {
        return $this->linkInstance;
    }

    /**
     * Retrieve array of partlists products for the given product.
     *
     * @param Product $currentProduct
     * @return Product[] Array of linked products
     */
    public function getPartlistsProducts(Product $currentProduct): array
    {
        if (!$this->hasData('partlists_products')) {
            /** @var Product[] $products */
            $products = [];
            $collection = $this->getPartlistsProductCollection($currentProduct);
            foreach ($collection as $product) {
                $products[] = $product;
            }
            // Keep legacy key for compatibility with existing callers
            $this->setData('partlists_products', $products);
        }
        /** @var Product[] $result */
        $result = (array) $this->getData('partlists_products');
        return $result;
    }

    /**
     * Retrieve partlists product identifiers (IDs) for the given product.
     *
     * @param Product $currentProduct
     * @return int[]
     */
    public function getPartlistsProductIds(Product $currentProduct): array
    {
        if (!$this->hasData('partlists_product_ids')) {
            /** @var int[] $ids */
            $ids = [];
            foreach ($this->getPartlistsProducts($currentProduct) as $product) {
                $ids[] = (int) $product->getId();
            }
            // Keep legacy key for compatibility
            $this->setData('partlists_product_ids', $ids);
        }
        /** @var int[] $result */
        $result = (array) $this->getData('partlists_product_ids');
        return $result;
    }

    /**
     * Retrieve product collection for the partlists link type.
     *
     * @param Product $currentProduct
     * @return ProductLinkProductCollection
     */
    public function getPartlistsProductCollection(Product $currentProduct): ProductLinkProductCollection
    {
        /** @var ProductLinkProductCollection $collection */
        $collection = $this->getLinkInstance()
            ->usePartlistsLinks()
            ->getProductCollection()
            ->setIsStrongMode();

        $collection->setProduct($currentProduct);
        return $collection;
    }

    /**
     * Retrieve link collection for the partlists link type.
     *
     * @param Product $currentProduct
     * @return LinkCollection
     */
    public function getPartlistsLinkCollection(Product $currentProduct): LinkCollection
    {
        /** @var LinkCollection $collection */
        $collection = $this->getLinkInstance()
            ->usePartlistsLinks()
            ->getLinkCollection();

        $collection->setProduct($currentProduct);
        $collection->addLinkTypeIdFilter();
        $collection->addProductIdFilter();
        $collection->joinAttributes();

        return $collection;
    }
}
