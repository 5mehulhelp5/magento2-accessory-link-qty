<?php
declare(strict_types=1);

/**
 * Data Patch: register custom product link type "partlists" (ID 60)
 * and its attributes (position:int, qty:decimal).
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Setup\Patch\Data;

use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AddPartlistsProductLink implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * Link type ID and code for the "partlists" relation.
     */
    private const LINK_TYPE_ID   = LinkModel::LINK_TYPE_PARTLISTS;
    private const LINK_TYPE_CODE = LinkModel::TYPE_NAME;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();

        try {
            // 1) Register the link type in 'catalog_product_link_type'
            $connection->insertOnDuplicate(
                $this->moduleDataSetup->getTable('catalog_product_link_type'),
                [
                    'link_type_id' => self::LINK_TYPE_ID,
                    'code'         => self::LINK_TYPE_CODE,
                ],
                ['code'] // Update 'code' if 'link_type_id' already exists
            );

            // 2) Register the 'position' attribute in 'catalog_product_link_attribute'
            $connection->insertOnDuplicate(
                $this->moduleDataSetup->getTable('catalog_product_link_attribute'),
                [
                    'link_type_id'                => self::LINK_TYPE_ID,
                    'product_link_attribute_code' => 'position',
                    'data_type'                   => 'int',
                ],
                ['data_type']
            );

            // 3) Register the 'qty' attribute in 'catalog_product_link_attribute'
            $connection->insertOnDuplicate(
                $this->moduleDataSetup->getTable('catalog_product_link_attribute'),
                [
                    'link_type_id'                => self::LINK_TYPE_ID,
                    'product_link_attribute_code' => 'qty',
                    'data_type'                   => 'decimal',
                ],
                ['data_type']
            );
        } finally {
            $this->moduleDataSetup->endSetup();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function revert(): void
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();

        try {
            // Remove the link attributes ('position', 'qty')
            $connection->delete(
                $this->moduleDataSetup->getTable('catalog_product_link_attribute'),
                [
                    'link_type_id = ?'                   => self::LINK_TYPE_ID,
                    'product_link_attribute_code IN (?)' => ['position', 'qty'],
                ]
            );

            // Remove the link type itself
            $connection->delete(
                $this->moduleDataSetup->getTable('catalog_product_link_type'),
                ['link_type_id = ?' => self::LINK_TYPE_ID]
            );
        } finally {
            $this->moduleDataSetup->endSetup();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}