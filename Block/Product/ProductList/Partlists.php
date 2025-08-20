<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Block\Product\ProductList;

use InSession\AccessoryLinkQty\Model\Partlists as PartlistsModel;
use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Zend_Db_Expr;

/**
 * Block for displaying 'Partlists' linked products.
 * This block handles the logic for fetching and preparing the collection of linked products
 * based on various display settings configured in the layout XML.
 */
class Partlists extends AbstractProduct implements IdentityInterface
{
    /**
     * The collection of partlist items.
     *
     * @var ProductCollection|null
     */
    protected ?ProductCollection $_itemCollection = null;

    /**
     * @param Context $context
     * @param ProductCollectionFactory $productCollectionFactory
     * @param PartlistsModel $partlistsModel
     * @param LinkModel $linkModel
     * @param ProductVisibility $catalogProductVisibility
     * @param UrlHelper $urlHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly PartlistsModel $partlistsModel,
        private readonly LinkModel $linkModel,
        private readonly ProductVisibility $catalogProductVisibility,
        private readonly UrlHelper $urlHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Prepares the linked product collection with fine-grained filtering.
     * This method fetches the IDs of linked "partlist" products and then loads a
     * product collection, applying filters based on layout XML arguments like
     * 'show_disabled_products', 'show_invisible_products', etc.
     *
     * @return $this
     */
    protected function _prepareData(): static
    {
        // Return if items are already prepared to prevent redundant processing.
        if ($this->_itemCollection !== null) {
            return $this;
        }

        $product = $this->getProduct();
        /* @var $product \Magento\Catalog\Model\Product */

        // If there's no main product, initialize an empty collection and return.
        if (!$product || !$product->getId()) {
            $this->_itemCollection = $this->productCollectionFactory->create();
            return $this;
        }

        // Fetch only the linked product IDs first for efficiency.
        $link = $this->linkModel->usePartlistsLinks();
        $linkCollection = $link->getLinkCollection()
            ->setProduct($product)
            ->addLinkTypeIdFilter(LinkModel::LINK_TYPE_PARTLISTS)
            ->addProductIdFilter();

        $linkedIds = $linkCollection->getColumnValues('linked_product_id');

        // If no linked products are found, initialize an empty collection.
        if (empty($linkedIds)) {
            $this->_itemCollection = $this->productCollectionFactory->create();
            return $this;
        }

        // Create a fresh product collection based on the fetched IDs.
        $showDisabled = (bool) $this->getData('show_disabled_products');
        $collection = $this->productCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $linkedIds]);

        // Conditionally load attributes based on whether disabled products should be shown.
        if ($showDisabled) {
            $attrs = $this->getData('attributes_to_select') ?: ['name', 'sku', 'small_image', 'thumbnail', 'price', 'special_price', 'status', 'visibility'];
            $collection->addAttributeToSelect($attrs);
        } else {
            $this->_addProductAttributesAndPrices($collection);
        }

        // Apply standard filters if not showing disabled products.
        if (!$showDisabled) {
            $collection->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED);
        }

        if (!$this->getData('show_all_products') && !$showDisabled) {
            $collection->addIsSaleableFilter();
        }

        if (!$this->getData('show_invisible_products')) {
            $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());
        }

        // Preserve the original link order from the admin panel.
        $collection->getSelect()->order(new Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $linkedIds) . ')'));

        // As in core blocks, set this flag to prevent category context from interfering.
        foreach ($collection as $item) {
            $item->setDoNotUseCategoryId(true);
        }

        $this->_itemCollection = $collection;
        return $this;
    }

    /**
     * Prepare items collection before rendering HTML.
     * This ensures that the data is ready for the template.
     *
     * @return $this
     */
    protected function _beforeToHtml(): static
    {
        $this->_prepareData();
        return parent::_beforeToHtml();
    }

    /**
     * Retrieve the collection of linked items.
     *
     * @return \Magento\Catalog\Model\Product[]
     */
    public function getItems(): array
    {
        return $this->_itemCollection ? iterator_to_array($this->_itemCollection) : [];
    }

    /**
     * Check if any item in the list can be added to the cart.
     *
     * @return bool
     */
    public function canItemsAddToCart(): bool
    {
        foreach ($this->getItems() as $item) {
            /* @var $item \Magento\Catalog\Model\Product */
            if (!$item->isComposite() && $item->isSaleable() && !$item->getRequiredOptions()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get identities for caching purposes.
     *
     * @return array
     */
    public function getIdentities(): array
    {
        $identities = [];
        foreach ($this->getItems() as $item) {
            $identities[] = $item->getIdentities();
        }
        return $identities ? array_merge([], ...$identities) : [];
    }

    /**
     * Get a map of linked product IDs to their quantities.
     * This is useful for templates that need to display quantity information.
     *
     * @return array<int, float>
     */
    public function getQtyMap(): array
    {
        $map = [];
        $product = $this->getProduct();
        if (!$product || !$product->getId()) {
            return $map;
        }

        // Fetch link collection with attributes to get the quantity for each linked product.
        $links = $this->partlistsModel->getPartlistsLinkCollection($product);
        foreach ($links as $link) {
            /* @var $link \Magento\Catalog\Model\Product\Link */
            $map[(int)$link->getLinkedProductId()] = max(0.0, (float)$link->getQty());
        }

        return $map;
    }
}
