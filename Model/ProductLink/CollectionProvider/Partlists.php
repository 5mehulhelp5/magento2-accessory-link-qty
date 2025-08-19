<?php
declare(strict_types=1);

/**
 * Partlists Linked Products Collection Provider
 *
 * Provides linked products for the custom "partlists" link type.
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Model\ProductLink\CollectionProvider;

use InSession\AccessoryLinkQty\Model\Partlists as PartlistsModel;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductLink\CollectionProviderInterface;

/**
 * @implements CollectionProviderInterface
 */
class Partlists implements CollectionProviderInterface
{
    /**
     * Domain service/model that resolves partlists for a given product.
     *
     * @var PartlistsModel
     */
    private PartlistsModel $partlistsModel;

    /**
     * @param PartlistsModel $partlistsModel Partlists model used to fetch linked products
     */
    public function __construct(PartlistsModel $partlistsModel)
    {
        $this->partlistsModel = $partlistsModel;
    }

    /**
     * Return linked products for the given product (partlists link type).
     *
     * @param Product $product The product for which to load linked items
     * @return Product[]       Array of linked products
     */
    public function getLinkedProducts(Product $product): array
    {
        /** @var Product[] $items */
        $items = (array) $this->partlistsModel->getPartlistsProducts($product); // BC: method name kept
        return $items;
    }
}
