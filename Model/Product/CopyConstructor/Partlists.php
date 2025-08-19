<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Model\Product\CopyConstructor;

use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use InSession\AccessoryLinkQty\Model\Partlists as PartlistsModel;
use Magento\Catalog\Api\Data\ProductLinkExtensionFactory;
use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\CopyConstructorInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Partlists implements CopyConstructorInterface
{
    public function __construct(
        private readonly PartlistsModel $partlistsModel,
        private readonly ProductLinkInterfaceFactory $productLinkFactory,
        private readonly ProductLinkExtensionFactory $productLinkExtensionFactory,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Copy "partlists" links (including qty & position) from an original product to its duplicate.
     *
     * @param Product $product The original product.
     * @param Product $duplicate The new, duplicated product.
     * @return void
     * @throws NoSuchEntityException
     */
    public function build(Product $product, Product $duplicate): void
    {
        // Get links that might already exist on the duplicate (usually none).
        $existingLinks = $duplicate->getProductLinks() ?: [];

        // Fetch all partlists links including attributes (qty is joined via joinAttributes()).
        $linkCollection = $this->partlistsModel->getPartlistsLinkCollection($product);

        $newLinks = [];
        foreach ($linkCollection as $linkRow) {
            $linkedProductId = (int)$linkRow->getLinkedProductId();
            if ($linkedProductId <= 0) {
                continue;
            }

            try {
                $linkedProduct = $this->productRepository->getById($linkedProductId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                // Linked product no longer exists -> skip
                continue;
            }
            
            // Get SKU and type of the linked product.
            $linkedProduct = $this->productRepository->getById($linkedProductId);

            /** @var ProductLinkInterface $newLink */
            $newLink = $this->productLinkFactory->create();
            $newLink->setSku((string)$duplicate->getSku())
                ->setLinkType(LinkModel::TYPE_NAME)
                ->setLinkedProductSku((string)$linkedProduct->getSku())
                ->setLinkedProductType((string)$linkedProduct->getTypeId())
                ->setPosition((int)$linkRow->getPosition());

            // Set qty as an extension attribute.
            $extensionAttributes = $newLink->getExtensionAttributes() ?: $this->productLinkExtensionFactory->create();
            $extensionAttributes->setQty((float)$linkRow->getQty());
            $newLink->setExtensionAttributes($extensionAttributes);

            $newLinks[] = $newLink;
        }

        // Attach the newly created links to the duplicate product.
        if ($newLinks) {
            $duplicate->setProductLinks(array_merge($existingLinks, $newLinks));
        }
    }
}