<?php
declare(strict_types=1);

/**
 * Partlists Related Products Data Provider
 *
 * Supplies the custom "partlists" link type to the generic related-products UI.
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Ui\DataProvider\Product\Related;

use Magento\Catalog\Ui\DataProvider\Product\Related\AbstractDataProvider;
use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;

class PartlistsDataProvider extends AbstractDataProvider
{
    /**
     * {@inheritdoc}
     *
     * @return string The custom link type code handled by this provider
     */
    protected function getLinkType(): string
    {
        return LinkModel::TYPE_NAME;
    }
}
