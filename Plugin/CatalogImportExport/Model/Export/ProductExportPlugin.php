<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Plugin\CatalogImportExport\Model\Export;

use Magento\CatalogImportExport\Model\Export\Product as ProductExport;
use Magento\Catalog\Model\Product;
use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;

class ProductExportPlugin
{
    /**
     * Fügt die Spalte "_partlists_" in den Export ein.
     *
     * @param ProductExport $subject
     * @param array $result
     * @param Product $product
     * @return array
     */
    public function afterGetExportData(ProductExport $subject, $result, Product $product): array
    {
        if (!$product->getId()) {
            return $result;
        }

        $links = $product->getProductLinks();
        $values = [];

        foreach ($links as $link) {
            if ((int)$link->getLinkTypeId() === LinkModel::LINK_TYPE_PARTLISTS) {
                $sku = $link->getLinkedProductSku();
                $qty = $link->getExtensionAttributes()?->getQty() ?? 1.0;
                $pos = $link->getExtensionAttributes()?->getPosition() ?? 0;
                $values[] = sprintf('%s|%s|%s', $sku, $qty, $pos);
            }
        }

        if ($values) {
            $result['_partlists_'] = implode(',', $values);
        }

        return $result;
    }
}
