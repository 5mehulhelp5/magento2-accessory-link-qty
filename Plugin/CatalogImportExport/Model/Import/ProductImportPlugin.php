<?php
declare(strict_types=1);

/**
 * Catalog Import: map custom "partlists" link type to its numeric ID.
 *
 * Adds the `_partlists_` CSV link key so that imports can create/update
 * links of the custom link type (ID 60).
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Plugin\CatalogImportExport\Model\Import;

use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Magento\CatalogImportExport\Model\Import\Product as ProductImportExport;

class ProductImportPlugin
{
    /**
     * Append the `_partlists_` CSV key to the link type ID mapping.
     *
     * This allows the importer to recognize and process "partlists" links
     * from a CSV file column named `_partlists_`.
     *
     * @param ProductImportExport $subject The original class being intercepted.
     * @param array|mixed $result Existing mapping of CSV keys to link type IDs.
     * @return array The augmented mapping including `_partlists_`.
     */
    public function afterGetLinkNameToId(ProductImportExport $subject, $result): array
    {
        // Be defensive in case older Magento versions return a non-array value.
        if (!is_array($result)) {
            $result = [];
        }

        // Map the dynamic CSV key to our custom link type ID.
        $csvKey = '_' . LinkModel::TYPE_NAME . '_';
        $result[$csvKey] = LinkModel::LINK_TYPE_PARTLISTS;

        return $result;
    }
}