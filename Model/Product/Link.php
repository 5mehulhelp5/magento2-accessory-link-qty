<?php
declare(strict_types=1);

/**
 * Partlists Product Link model
 *
 * Provides a convenience API to work with the custom "partlists" link type.
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Model\Product;

use Magento\Catalog\Model\Product\Link as BaseLink;

class Link extends BaseLink
{
    /**
     * The unique ID for the 'partlists' link type.
     */
    public const LINK_TYPE_PARTLISTS = 60;
    
    /**
     * The unique code for the 'partlists' link type.
     */
    public const TYPE_NAME = 'partlists';

    /**
     * Set the link type to partlists.
     *
     * @return $this
     */
    public function usePartlistsLinks()
    {
        $this->setLinkTypeId(self::LINK_TYPE_PARTLISTS);
        return $this;
    }
}