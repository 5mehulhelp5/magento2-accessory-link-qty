<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Block\Product\View;

use InSession\AccessoryLinkQty\Model\Partlists as PartlistsModel;
use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Url\Helper\Data as UrlHelper;

/**
 * Block for displaying 'Partlists' linked products on the product detail page.
 */
class Partlists extends AbstractLinkProducts
{
    /**
     * @param Context $context
     * @param ProductVisibility $catalogProductVisibility
     * @param LinkModel $linkModel
     * @param UrlHelper $urlHelper
     * @param ProductCollectionFactory $productCollectionFactory
     * @param PartlistsModel $partlistsModel
     * @param array $data
     */
    public function __construct(
        Context $context,
        ProductVisibility $catalogProductVisibility,
        LinkModel $linkModel,
        UrlHelper $urlHelper,
        ProductCollectionFactory $productCollectionFactory,
        private readonly PartlistsModel $partlistsModel,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $catalogProductVisibility,
            $linkModel,
            $urlHelper,
            $productCollectionFactory,
            $data
        );
    }

    /**
     * Configures the link model to use the 'partlists' link type.
     *
     * @param LinkModel $model
     * @return LinkModel
     */
    protected function configureLinkModel(LinkModel $model): LinkModel
    {
        return $model->usePartlistsLinks();
    }

    /**
     * Get the title for the block.
     * The title can be set via layout XML argument 'title'.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getData('title') ?: (string)__('Partlist');
    }

    /**
     * Get items enriched with qty and position, sorted by position.
     *
     * @return array<int,array{product:ProductInterface, qty:float, position:int}>
     */
    public function getItemsWithQty(): array
    {
        // ... (Der Rest deiner Methode bleibt unverändert, er ist bereits perfekt)
        $current = $this->getProduct();
        if (!$current || !$current->getId()) {
            return [];
        }

        $linkCollection = $this->partlistsModel->getPartlistsLinkCollection($current);
        if (!$linkCollection->getSize()) {
            return [];
        }

        $qtyById = [];
        $posById = [];
        $ids     = [];

        foreach ($linkCollection as $row) {
            $lid = (int)$row->getLinkedProductId();
            if ($lid <= 0) {
                continue;
            }
            $ids[]         = $lid;
            $qtyById[$lid] = max(0.0, (float)$row->getQty());
            $posById[$lid] = (int)$row->getPosition();
        }

        if (!$ids) {
            return [];
        }

        $items = [];
        foreach ($this->getItems() as $p) {
            /** @var ProductInterface $p */
            $id = (int)$p->getId();
            if (!in_array($id, $ids, true)) {
                continue;
            }
            $qty = $qtyById[$id] ?? 1.0;
            if ($qty <= 0) {
                $qty = 1.0;
            }

            $items[] = [
                'product'  => $p,
                'qty'      => $qty,
                'position' => $posById[$id] ?? 0,
            ];
        }

        usort($items, static fn($a, $b) => ($a['position'] <=> $b['position']));

        return $items;
    }
}