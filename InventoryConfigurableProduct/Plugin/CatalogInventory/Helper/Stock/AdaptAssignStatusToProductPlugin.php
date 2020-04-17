<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Plugin\CatalogInventory\Helper\Stock;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Helper\Stock;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;

/**
 * Process configurable product stock status considering configurable options salable status.
 */
class AdaptAssignStatusToProductPlugin
{
    /**
     * @var Configurable
     */
    private $configurable;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StockResolverInterface
     */
    private $stockResolver;

    /**
     * @var IsProductSalableInterface
     */
    private $isProductSalable;

    /**
     * @param Configurable $configurable
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param IsProductSalableInterface $isProductSalable
     */
    public function __construct(
        Configurable $configurable,
        StoreManagerInterface $storeManager,
        StockResolverInterface $stockResolver,
        IsProductSalableInterface $isProductSalable
    ) {
        $this->configurable = $configurable;
        $this->storeManager = $storeManager;
        $this->stockResolver = $stockResolver;
        $this->isProductSalable = $isProductSalable;
    }

    /**
     * Process configurable product stock status, considering configurable options.
     *
     * @param Stock $subject
     * @param Product $product
     * @param int|null $status
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeAssignStatusToProduct(
        Stock $subject,
        Product $product,
        $status = null
    ): array {
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $website = $this->storeManager->getWebsite();
            $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $website->getCode());
            $options = $this->configurable->getConfigurableOptions($product);
            $status = 0;
            $skus = [[]];
            foreach ($options as $attribute) {
                $skus[] = array_column($attribute, 'sku');
            }
            $skus = array_merge(...$skus);
            foreach ($skus as $sku) {
                $isSalable = $this->isProductSalable->execute($sku, $stock->getStockId());
                if ($isSalable) {
                    $status = 1;
                    break;
                }
            }
        }

        return [$product, $status];
    }
}
