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

/**
 * Abstract base class for rendering linked product lists.
 */
abstract class AbstractLinkProducts extends AbstractProduct implements IdentityInterface
{
    /**
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

    // ... (abstrakte Methoden und getProduct() bleiben unverändert)

    /**
     * Prepares the linked product collection with fine-grained filtering.
     * @return $this
     */
    protected function _prepareData(): static
    {
        if ($this->_itemCollection !== null) {
            return $this;
        }

        $current = $this->getProduct();
        if (!$current || !$current->getId()) {
            $this->_itemCollection = $this->productCollectionFactory->create();
            return $this;
        }

        try {
            // 1. Fetch only the linked product IDs.
            $link = $this->configureLinkModel($this->linkModel);
            $linkCollection = $link->getLinkCollection()->setProduct($current);
            $linkedProductIds = $linkCollection->getColumnValues('linked_product_id');

            if (empty($linkedProductIds)) {
                $this->_itemCollection = $this->productCollectionFactory->create();
                return $this;
            }

            $showDisabled = (bool)$this->getData('show_disabled_products');

            // 2. Create a fresh product collection.
            $collection = $this->productCollectionFactory->create();
            $collection->addFieldToFilter('entity_id', ['in' => $linkedProductIds]);

            // 3. Conditionally load attributes.
            if ($showDisabled) {
                // For disabled products, load attributes manually to prevent them from being filtered out.
                $attributes = $this->getData('attributes_to_select');
                if (empty($attributes)) {
                    $attributes = ['name', 'sku', 'small_image', 'thumbnail', 'price', 'special_price', 'status', 'visibility'];
                }
                $collection->addAttributeToSelect($attributes);
            } else {
                // For enabled products, use the standard method to get all list attributes and prices.
                $this->_addProductAttributesAndPrices($collection);
            }

            // 4. Apply filters based on layout configuration.
            if (!$showDisabled) {
                $collection->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED);
            }

            if (!$this->getData('show_all_products') && !$showDisabled) {
                $collection->addIsSaleableFilter();
            }

            if (!$this->getData('show_invisible_products')) {
                $collection->setVisibility($this->catalogProductVisibility->getVisibleInCatalogIds());
            }

            // 5. Preserve the original link order.
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $linkedProductIds) . ')')
            );

            $this->_itemCollection = $collection;
        } catch (\Throwable $e) {
            $this->_logger->critical($e);
            $this->_itemCollection = $this->productCollectionFactory->create();
        }

        return $this;
    }

    /**
     * Get the loaded product item collection.
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