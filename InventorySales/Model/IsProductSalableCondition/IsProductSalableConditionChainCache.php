<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\IsProductSalableCondition;

use Magento\InventorySalesApi\Api\IsProductSalableInterface;

/**
 * Check if product has source items with the in stock status
 */
class IsProductSalableConditionChainCache implements IsProductSalableInterface
{
    /**
     * @var IsProductSalableConditionChain
     */
    private $isProductSalableConditionChain;

    /**
     * @var bool[][]
     */
    private $cache = [];

    /**
     * @param IsProductSalableConditionChain $IsProductSalableConditionChain
     */
    public function __construct(
        IsProductSalableConditionChain $IsProductSalableConditionChain
    ) {
        $this->isProductSalableConditionChain = $IsProductSalableConditionChain;
    }

    /**
     * @inheritdoc
     */
    public function execute(string $sku, int $stockId): bool
    {
        if (!isset($this->cache[$stockId][$sku])) {
            $this->cache[$stockId][$sku] = $this->isProductSalableConditionChain->execute($sku, $stockId);
        }

        return $this->cache[$stockId][$sku];
    }
}
