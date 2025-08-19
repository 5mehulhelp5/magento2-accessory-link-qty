<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Model\GraphQl;

use InSession\AccessoryLinkQty\Model\Partlists as PartlistsModel;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class PartlistDataProvider
{
    /**
     * @var PartlistsModel
     */
    private readonly PartlistsModel $partlistsModel;

    /**
     * @var ProductRepositoryInterface
     */
    private readonly ProductRepositoryInterface $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;

    /**
     * @param PartlistsModel $partlistsModel
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PartlistsModel $partlistsModel,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->partlistsModel = $partlistsModel;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Get the items for a given product's part list.
     *
     * @param ProductModel $product The main product for which to fetch the part list items.
     * @param int|null $storeId The ID of the store context. Defaults to the current store if null.
     * @return array<int, array{
     * product: array{model: ProductInterface, type_id: string, sku: string},
     * qty: float,
     * position: int
     * }> The sorted list of part list items.
     * @throws NoSuchEntityException
     */
    public function getItems(ProductModel $product, ?int $storeId = null): array
    {
        // Get the collection of linked products (part lists) for the given product.
        $linkCollection = $this->partlistsModel->getPartlistsLinkCollection($product);

        /** @var int[] $orderedIds An array of linked product IDs, maintaining the original order. */
        $orderedIds = [];
        /** @var array<int, bool> $uniqueIds A map of unique linked product IDs for efficient lookup. */
        $uniqueIds  = [];
        /** @var array<int, float> $qtyById A map from product ID to its required quantity. */
        $qtyById    = [];
        /** @var array<int, int> $posById A map from product ID to its position in the list. */
        $posById    = [];

        // Iterate over the raw link data to extract and organize IDs, quantities, and positions.
        foreach ($linkCollection as $row) {
            $id = (int)$row->getLinkedProductId();
            if ($id <= 0) {
                continue;
            }
            $orderedIds[]   = $id;
            $uniqueIds[$id] = true;
            $qtyById[$id]   = max(0.0, (float)$row->getQty());
            $posById[$id]   = (int)$row->getPosition();
        }

        // If there are no linked products, return an empty array.
        if (!$orderedIds) {
            return [];
        }

        // If no store ID is provided, default to the current store's ID.
        if ($storeId === null) {
            $storeId = (int)$this->storeManager->getStore()->getId();
        }

        /** @var array<int, ProductInterface> $byId A map from product ID to the loaded product object. */
        $byId = [];
        foreach (array_keys($uniqueIds) as $id) {
            // Important: Load products in the correct store context and force a reload to ensure fresh data.
            $byId[$id] = $this->productRepository->getById($id, false, $storeId, true);
        }

        /** @var array $items The final array of item data to be returned. */
        $items = [];
        // Construct the final item array using the ordered IDs to maintain the original sequence.
        foreach ($orderedIds as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $p = $byId[$id];
            $items[] = [
                'product' => [
                    'model'   => $p,
                    'type_id' => (string)$p->getTypeId(),
                    'sku'     => (string)$p->getSku(),
                ],
                'qty'      => $qtyById[$id] ?? 0.0,
                'position' => $posById[$id] ?? 0,
            ];
        }

        // Sort the final items array based on the 'position' value.
        usort($items, fn($a, $b) => $a['position'] <=> $b['position']);
        
        return $items;
    }
}