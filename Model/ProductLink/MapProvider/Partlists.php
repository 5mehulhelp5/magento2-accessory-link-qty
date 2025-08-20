<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Model\ProductLink\MapProvider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Link;
use Magento\Catalog\Model\ProductLink\MapProviderInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Model\ResourceModel\Product\Link\Product\CollectionFactory as LinkedCollectionFactory;
use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;

/**
 * Provides a map of "partlists" linked products for a given set of root products.
 * This is used in contexts where linked products need to be loaded efficiently in bulk.
 */
class Partlists implements MapProviderInterface
{
    /**
     * The unique code for the 'partlists' link type.
     */
    private const TYPE_NAME = LinkModel::TYPE_NAME;

    /**
     * @param Link $linkModel The generic product link model.
     * @param MetadataPool $metadataPool The metadata pool for entity information.
     * @param LinkedCollectionFactory $linkedCollectionFactory Factory for creating linked product collections.
     */
    public function __construct(
        private readonly Link $linkModel,
        private readonly MetadataPool $metadataPool,
        private readonly LinkedCollectionFactory $linkedCollectionFactory
    ) {
    }

    /**
     * Checks if this provider can handle the given link type.
     *
     * @param string $linkType The link type to check.
     * @return bool True if the link type is 'partlists', false otherwise.
     */
    public function canProcessLinkType(string $linkType): bool
    {
        return $linkType === self::TYPE_NAME;
    }

    /**
     * Fetches the map of linked products for the "partlists" type.
     * The map is structured as [root_product_sku => ['partlists' => [linked_product_1, linked_product_2]]].
     *
     * @param Product[] $products An array of root products to fetch linked items for.
     * @param array $linkTypes A map of link types to process.
     * @return array The resulting map of linked products.
     * @throws \Exception
     */
    public function fetchMap(array $products, array $linkTypes): array
    {
        // Return early if there are no products or the 'partlists' type is not requested.
        if (!$products || !isset($linkTypes[self::TYPE_NAME])) {
            return [];
        }

        // Get the link field (e.g., 'row_id') from the product entity's metadata.
        $productLinkField = $this->metadataPool
            ->getMetadata(ProductInterface::class)
            ->getLinkField();

        /** @var Product[] $rootProducts */
        $rootProducts = [];
        foreach ($products as $product) {
            /* @var $product Product */
            $id = $product->getData($productLinkField);
            if ($id) {
                $rootProducts[$id] = $product;
            }
        }

        if (empty($rootProducts)) {
            return [];
        }
        
        // This will hold the final map: [sku => [link_type => [products]]].
        $map = [];

        /** @var \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection $collection */
        $collection = $this->linkedCollectionFactory->create([
            'productIds' => array_keys($rootProducts)
        ]);

        // Configure the collection to fetch 'partlists' links.
        $this->linkModel->setLinkTypeId(LinkModel::LINK_TYPE_PARTLISTS); // ID 60
        $collection->setLinkModel($this->linkModel);
        $collection->setIsStrongMode();

        foreach ($collection->getItems() as $linkedProduct) {
            /* @var $linkedProduct Product */
            $linkedToId = (int) $linkedProduct->getData('_linked_to_product_id');

            // Ensure the parent product exists in our initial set.
            if (!isset($rootProducts[$linkedToId])) {
                continue;
            }

            // Map the linked product to its parent's SKU.
            $sku = $rootProducts[$linkedToId]->getSku();
            $map[$sku][self::TYPE_NAME][] = $linkedProduct;
        }

        return $map;
    }
}
