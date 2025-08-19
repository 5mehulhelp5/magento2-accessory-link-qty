<?php
declare(strict_types=1);

/**
 * Product Form Modifier: Partlists
 *
 * Adds a "partlists" grid to the Related/Up-Sell/Cross-Sell section and
 * wires the custom qty field into posted link rows.
 *
 * @category   InSession
 * @package    InSession_AccessoryLinkQty
 */

namespace InSession\AccessoryLinkQty\Ui\DataProvider\Product\Form\Modifier;

use InSession\AccessoryLinkQty\Model\Partlists as PartlistsModel;
use InSession\AccessoryLinkQty\Model\Product\Link as LinkModel;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Related;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Api\ProductLinkRepositoryInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Form;

class Partlists extends Related
{
    /** Data scope key used in posted links (UI payload). */
    public const DATA_SCOPE_PARTLISTS = LinkModel::TYPE_NAME; // 'partlists'

    /** Previous group to anchor the sort order against. */
    private static string $previousGroup = 'search-engine-optimization';

    /** Sort order for the group within the form. */
    private static int $sortOrder = 90;

    /** Domain model to fetch partlists link collections (with joined attributes). */
    private PartlistsModel $partlistsModel;

    /**
     * Keep the same constructor signature as core Related and inject our model.
     */
    public function __construct(
        LocatorInterface $locator,
        UrlInterface $urlBuilder,
        ProductLinkRepositoryInterface $productLinkRepository,
        ProductRepositoryInterface $productRepository,
        ImageHelper $imageHelper,
        Status $status,
        AttributeSetRepositoryInterface $attributeSetRepository,
        PartlistsModel $partlistsModel,
        string $scopeName = '',
        string $scopePrefix = ''
    ) {
        $this->partlistsModel = $partlistsModel;

        parent::__construct(
            $locator,
            $urlBuilder,
            $productLinkRepository,
            $productRepository,
            $imageHelper,
            $status,
            $attributeSetRepository,
            $scopeName,
            $scopePrefix
        );
    }

    /** @inheritdoc */
    public function modifyMeta(array $meta): array
    {
        $meta = array_replace_recursive(
            $meta,
            [
                static::GROUP_RELATED => [
                    'children'  => [
                        $this->scopePrefix . static::DATA_SCOPE_PARTLISTS => $this->getPartlistsFieldset(),
                    ],
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label'          => __('Related Products, Up-Sells, Cross-Sells and Partlists'),
                                'collapsible'    => true,
                                'componentType'  => Form\Fieldset::NAME,
                                'dataScope'      => static::DATA_SCOPE,
                                'sortOrder'      => $this->getNextGroupSortOrder(
                                    $meta,
                                    self::$previousGroup,
                                    self::$sortOrder
                                ),
                            ],
                        ],
                    ],
                ],
            ]
        );

        return $meta;
    }

    /**
     * Build the grid configuration and add the qty column (number input).
     *
     * @param string $scope
     * @return array<string,mixed>
     */
    protected function getGrid($scope): array
    {
        /** @var array<string,mixed> $grid */
        $grid = parent::getGrid($scope);

        // Add qty column (number input) to the dynamic rows
        $grid['children']['record']['children']['qty'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label'         => __('Qty'),
                        'componentType' => Form\Field::NAME,
                        'formElement'   => Form\Element\Input::NAME,
                        'dataScope'     => 'qty',
                        'dataType'      => Form\Element\DataType\Number::NAME,
                        'sortOrder'     => 65,
                        'validation'    => [
                            'validate-number' => true, 
                            'validate-zero-or-greater' => true
                        ],
                        'step'          => '0.0001',
                        'labelVisible'  => false,
                        'numberFormat' => [
                            'pattern' => '0.0',
                            'decimalSymbol' => '.',
                            'groupSymbol' => '',
                        ],
                        'imports'       => [
                            'disabled' => '${ $.provider }:${ $.parentScope }.${ $.index }.isReadonly',
                        ],
                        'default'       => 1,
                    ],
                ],
            ],
        ];

        return $grid;
    }

    /**
     * {@inheritdoc}
     *
     * Enriches prepared link rows (id/sku/...) with qty from link collection.
     *
     * @param array<int|string,mixed> $data
     * @return array<int|string,mixed>
     */
    public function modifyData(array $data): array
    {
        $data = parent::modifyData($data);

        /** @var Product $product */
        $product   = $this->locator->getProduct();
        $productId = (int) $product->getId();

        if ($productId === 0 || empty($data[$productId]['links'][self::DATA_SCOPE_PARTLISTS])) {
            return $data;
        }

        // Fetch all partlists links including attributes (qty is joined in the collection)
        $linkCollection = $this->partlistsModel->getPartlistsLinkCollection($product);

        /** @var array<int,float> $qtyByLinkedId map: linked_product_id => qty */
        $qtyByLinkedId = [];
        foreach ($linkCollection as $link) {
            $qtyByLinkedId[(int) $link->getLinkedProductId()] = (float) $link->getQty();
        }

        // Map qty into already-prepared rows (id/sku/...)
        foreach ($data[$productId]['links'][self::DATA_SCOPE_PARTLISTS] as &$row) {
            /** @var array<string,mixed> $row */
            $lid = (int) ($row['id'] ?? 0);
            if ($lid && array_key_exists($lid, $qtyByLinkedId)) {
                $row['qty'] = $qtyByLinkedId[$lid];
            }
        }
        unset($row); // break reference

        return $data;
    }

    /**
     * Prepare fieldset config for the Partlists section.
     *
     * @return array<string,mixed>
     */
    protected function getPartlistsFieldset(): array
    {
        $content = __(
            'Custom type products are shown to customers in addition to the item the customer is looking at.'
        );

        return [
            'children'  => [
                'button_set' => $this->getButtonSet(
                    $content,
                    __('Add Partlists Products'),
                    $this->scopePrefix . static::DATA_SCOPE_PARTLISTS
                ),
                'modal'      => $this->getGenericModal(
                    __('Add Partlists Products'),
                    $this->scopePrefix . static::DATA_SCOPE_PARTLISTS
                ),
                static::DATA_SCOPE_PARTLISTS => $this->getGrid($this->scopePrefix . static::DATA_SCOPE_PARTLISTS),
            ],
            'arguments' => [
                'data' => [
                    'config' => [
                        'additionalClasses' => 'admin__fieldset-section',
                        'label'             => __('Partlists Products'),
                        'collapsible'       => false,
                        'componentType'     => Form\Fieldset::NAME,
                        'dataScope'         => '',
                        'sortOrder'         => 90,
                    ],
                ],
            ],
        ];
    }

    /**
     * Only our custom scope should be injected into the Related section by this modifier.
     *
     * @return string[]
     */
    protected function getDataScopes(): array
    {
        return [
            static::DATA_SCOPE_PARTLISTS, // 'partlists'
        ];
    }
}
