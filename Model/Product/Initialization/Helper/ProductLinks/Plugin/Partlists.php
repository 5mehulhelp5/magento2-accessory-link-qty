<?php
declare(strict_types=1);

/**
 * Partlists Product Links Initialization Plugin
 *
 * Adds support for a custom product link type "partlists" with quantity (qty) attribute.
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Model\Product\Initialization\Helper\ProductLinks\Plugin;

use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Magento\Catalog\Api\Data\ProductLinkExtensionFactory;
use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Initialization\Helper\ProductLinks as Subject;
use Magento\Framework\Exception\NoSuchEntityException;

class Partlists
{
    /**
     * @param ProductLinkInterfaceFactory $productLinkFactory
     * @param ProductRepositoryInterface $productRepository
     * @param ProductLinkExtensionFactory $productLinkExtensionFactory
     */
    public function __construct(
        private readonly ProductLinkInterfaceFactory $productLinkFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductLinkExtensionFactory $productLinkExtensionFactory
    ) {
    }

    /**
     * Builds ProductLinks including qty from the links['partlists'][] array.
     *
     * @param Subject $subject The original class being intercepted.
     * @param Product $product The product being initialized.
     * @param array<string, array<int, array<string, mixed>>> $links The links data from the request.
     * @return array{Product, array<string, array<int, array<string, mixed>>>}
     * @throws NoSuchEntityException
     */
    public function beforeInitializeLinks(
        Subject $subject,
        Product $product,
        array $links
    ): array {
        // Nothing to do if no partlists links were posted.
        if (!isset($links[LinkModel::TYPE_NAME]) || !is_array($links[LinkModel::TYPE_NAME])) {
            return [$product, $links];
        }

        $rawLinks = $links[LinkModel::TYPE_NAME];
        $newLinks = [];
        $existing = $product->getProductLinks();

        foreach ($rawLinks as $row) {
            if (empty($row['id'])) {
                continue;
            }
            $linkedProduct = $this->productRepository->getById((int)$row['id']);

            /** @var ProductLinkInterface $productLink */
            $productLink = $this->productLinkFactory->create();
            $productLink->setSku($product->getSku())
                ->setLinkType(LinkModel::TYPE_NAME)
                ->setLinkedProductSku($linkedProduct->getSku())
                ->setLinkedProductType($linkedProduct->getTypeId())
                ->setPosition((int)($row['position'] ?? 0));

            // Set qty as an extension attribute (similar to Grouped Products).
            $extensionAttributes = $productLink->getExtensionAttributes() ?: $this->productLinkExtensionFactory->create();
            $extensionAttributes->setQty((float)($row['qty'] ?? 0));
            $productLink->setExtensionAttributes($extensionAttributes);

            $newLinks[] = $productLink;
        }

        // Remove existing links of this type that are no longer in the post data.
        $existing = $this->removeMissing($existing, $newLinks);

        $product->setProductLinks(array_merge($existing, $newLinks));

        // Important: Return original $links array unchanged (signature of before*).
        return [$product, $links];
    }

    /**
     * Filters an array of existing product links, removing those that are not present in a new set.
     *
     * @param ProductLinkInterface[] $existingLinks
     * @param ProductLinkInterface[] $newLinks
     * @return ProductLinkInterface[]
     */
    private function removeMissing(array $existingLinks, array $newLinks): array
    {
        $result = [];
        $newSkus = array_map(static fn($link) => $link->getLinkedProductSku(), $newLinks);

        foreach ($existingLinks as $key => $link) {
            $result[$key] = $link;
            if ($link->getLinkType() === LinkModel::TYPE_NAME
                && !in_array($link->getLinkedProductSku(), $newSkus, true)
            ) {
                unset($result[$key]);
            }
        }
        return $result;
    }
}