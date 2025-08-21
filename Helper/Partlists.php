<?php
declare(strict_types=1);

namespace InSession\AccessoryLinkQty\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;

class Partlists extends AbstractHelper
{
    public function __construct(
        private readonly PriceHelper $priceHelper,
        private readonly TaxHelper   $taxHelper
    ) {}

    /**
     * Formats a float value as a currency string (e.g., 1.234,56 €).
     * @param float $value
     * @return string
     */
    public function money(float $value): string
    {
        return $this->priceHelper->currency($value, true, false);
    }

    /**
     * Formats a quantity with up to 3 decimal places, removing trailing zeros (German format).
     * @param float $qty
     * @return string
     */
    public function qty(float $qty): string
    {
        $s = number_format($qty, 3, ',', '');
        $s = rtrim($s, '0');
        $s = rtrim($s, ',');
        return $s !== '' ? $s : '0';
    }

    /**
     * Gets the final numerical price amount for display, respecting the current tax display settings.
     * This method correctly uses the Magento price engine to account for customer groups, catalog rules, etc.
     *
     * @param Product $product
     * @return float
     */
    public function unitAmountForDisplay(Product $product): float
    {
        $priceModel = $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE);
        $amount     = $priceModel->getAmount();

        // Check the store's tax display configuration using the correct method names.
        $shouldDisplayIncludingTax = $this->taxHelper->displayPriceIncludingTax();
        
        // we should use the including-tax value for our calculations.
        if ($shouldDisplayIncludingTax || $this->taxHelper->displayBothPrices()) {
            return (float) $amount->getValue();
        }

        return (float) $amount->getBaseAmount();
    }
}
