<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Block\Product\View;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Zend_Db_Expr;

/**
 * Abstract base class for rendering linked product lists.
 * @api
 */
abstract class AbstractLinkProducts extends AbstractProduct implements IdentityInterface
{
    /**
     * The collection of linked product items.
     *
     * @var ProductCollection|null
     */
    protected ?ProductCollection $_itemCollection = null;

    /**
     * @param Context $context
     * @param ProductVisibility $catalogProductVisibility
     * @param LinkModel $linkModel
     * @param UrlHelper $urlHelper
     * @param ProductCollectionFactory $productCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly ProductVisibility $catalogProductVisibility,
        private readonly LinkModel $linkModel,
        private readonly UrlHelper $urlHelper,
        private readonly ProductCollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Abstract method to configure the link model for a specific link type.
     *
     * @param LinkModel $model
     * @return LinkModel
     */
    abstract protected function configureLinkModel(LinkModel $model): LinkModel;

    /**
     * Prepares the linked product collection with an optimized, dual-path approach.
     *
     * - For the standard case (enabled products only), it uses the highly optimized core method
     * `_addProductAttributesAndPrices` for maximum performance and accuracy.
     * - For the special case (including disabled products), it uses a fallback method with flags
     * to prevent Magento's core from filtering out the disabled items.
     *
     * @return $this
     */
    protected function _prepareData(): static
    {
        // Return early if the collection has already been prepared
        if ($this->_itemCollection !== null) {
            return $this;
        }

        $current = $this->getProduct();
        if (!$current || !$current->getId()) {
            // Return an empty collection if there is no current product
            $this->_itemCollection = $this->productCollectionFactory->create();
            return $this;
        }

        try {
            $link = $this->configureLinkModel($this->linkModel);
            $linkCollection = $link->getLinkCollection()->setProduct($current);

            // Gather all linked product IDs in an array
            $linkedProductIds = array_values(array_filter(
                array_map('intval', $linkCollection->getColumnValues('linked_product_id')),
                static fn($id) => $id > 0
            ));

            if (!$linkedProductIds) {
                // If there are no linked products, return an empty collection
                $this->_itemCollection = $this->productCollectionFactory->create();
                return $this;
            }

            $showDisabled = (bool)$this->getData('show_disabled_products');

            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
            $collection = $this->productCollectionFactory->create();

            // Load all linked products in a single query (avoiding N+1 problem)
            $collection->addFieldToFilter('entity_id', ['in' => $linkedProductIds]);

            // Only select the attributes required for display and price calculation
            $defaultAttributes = ['name', 'sku', 'small_image', 'price', 'special_price', 'tax_class_id', 'status', 'visibility'];
            if ($showDisabled) {
                $collection->addAttributeToSelect($defaultAttributes);
            } else {
                // Add all attributes and price information needed for frontend rendering
                $this->_addProductAttributesAndPrices($collection);
                $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

                if (!$this->getData('show_all_products')) {
                    $collection->addIsSaleableFilter();
                }
                if (!$this->getData('show_invisible_products')) {
                    $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());
                }
            }

            // Prevent category context from affecting price rendering
            foreach ($collection as $item) {
                $item->setDoNotUseCategoryId(true);
            }

            // Preserve the order of linked products as defined in the partlist
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $linkedProductIds) . ')')
            );

            // Set the loaded and sorted collection for use elsewhere
            $this->_itemCollection = $collection;
        } catch (\Throwable $e) {
            // Log the error and return an empty collection as fallback
            $this->_logger->critical($e);
            $this->_itemCollection = $this->productCollectionFactory->create();
        }

        return $this;
    }

    /**
     * Get the loaded product item collection.
     *
     * @return ProductCollection
     */
    public function getItems(): ProductCollection
    {
        if ($this->_itemCollection === null) {
            $this->_prepareData();
        }
        return $this->_itemCollection;
    }

    /**
     * Get identities for caching purposes.
     *
     * @return array
     */
    public function getIdentities(): array
    {
        $ids = [];
        foreach ($this->getItems() as $item) {
            $ids[] = $item->getIdentities();
        }
        return $ids ? array_merge([], ...$ids) : [];
    }
    
    /**
     * Get the POST parameters for adding a product to the cart.
     *
     * @param Product $product
     * @return array
     */
    public function getAddToCartPostParams(Product $product): array
    {
        $url = $this->getAddToCartUrl($product, ['_escape' => false]);
        return [
            'action' => $url,
            'data' => [
                'product' => (int)$product->getEntityId(),
                ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
            ],
        ];
    }
}
