<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForSkuInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterfaceFactory;
use Magento\InventorySales\Model\IsProductSalableCondition\ManageStockCondition;

/**
 * @inheritdoc
 */
class AreProductsSalable implements AreProductsSalableInterface
{
    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    private $getSourcesAssignedToStockOrderedByPriority;

    /**
     * @var IsSourceItemManagementAllowedForSkuInterface
     */
    private $isSourceItemManagementAllowedForSku;

    /**
     * @var ManageStockCondition
     */
    private $manageStockCondition;

    /**
     * @var IsProductSalableResultInterfaceFactory
     */
    private $isProductSalableResultFactory;

    /**
     * @param SourceItemRepositoryInterface $sourceItemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority
     * @param IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku
     * @param ManageStockCondition $manageStockCondition
     * @param IsProductSalableResultInterfaceFactory $isProductSalableResultFactory
     */
    public function __construct(
        SourceItemRepositoryInterface $sourceItemRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority,
        IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku,
        ManageStockCondition $manageStockCondition,
        IsProductSalableResultInterfaceFactory $isProductSalableResultFactory
    ) {
        $this->sourceItemRepository = $sourceItemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->getSourcesAssignedToStockOrderedByPriority = $getSourcesAssignedToStockOrderedByPriority;
        $this->isSourceItemManagementAllowedForSku = $isSourceItemManagementAllowedForSku;
        $this->manageStockCondition = $manageStockCondition;
        $this->isProductSalableResultFactory = $isProductSalableResultFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, int $stockId): array
    {
        $result = [];
        $filtered = [];
        foreach ($skus as $key => $item) {
            // TODO Must be removed once MSI-2131 is complete.
            if ($this->manageStockCondition->execute($item, $stockId)) {
                $result[$item] = $this->isProductSalableResultFactory->create(
                    [
                        'sku' => $item,
                        'stockId' => $stockId,
                        'isSalable' => true,
                    ]
                );
                $filtered[] = $key;
                continue;
            }

            if (!$this->isSourceItemManagementAllowedForSku->execute($item)) {
                $result[$item] = $this->isProductSalableResultFactory->create(
                    [
                        'sku' => $item,
                        'stockId' => $stockId,
                        'isSalable' => true,
                    ]
                );
                $filtered[] = $key;
            }
        }
        $skus = array_diff($skus, $filtered);

        if (!empty($skus)) {
            $sourceCodes = $this->getSourceCodesAssignedToStock($stockId);
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(SourceItemInterface::SKU, $skus, 'in')
                ->addFilter(SourceItemInterface::SOURCE_CODE, $sourceCodes, 'in')
                ->addFilter(SourceItemInterface::STATUS, SourceItemInterface::STATUS_IN_STOCK)
                ->create();
            $sourceItems = $this->sourceItemRepository->getList($searchCriteria)->getItems();

            $filtered = [];
            foreach ($sourceItems as $sourceItem) {
                $result[$sourceItem->getSku()] = $this->isProductSalableResultFactory->create(
                    [
                        'sku' => $sourceItem->getSku(),
                        'stockId' => $stockId,
                        'isSalable' => $sourceItem->getStatus() === 1,
                    ]
                );
                $filtered[] = $sourceItem->getSku();
            }
            $skus = array_diff($skus, $filtered);
            foreach ($skus as $item) {
                $result[$item] = $this->isProductSalableResultFactory->create(
                    [
                        'sku' => $item,
                        'stockId' => $stockId,
                        'isSalable' => false,
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * Provides source codes for certain stock
     *
     * @param int $stockId
     *
     * @return array
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getSourceCodesAssignedToStock(int $stockId): array
    {
        $sources = $this->getSourcesAssignedToStockOrderedByPriority->execute($stockId);
        $sourceCodes = [];
        foreach ($sources as $source) {
            if ($source->isEnabled()) {
                $sourceCodes[] = $source->getSourceCode();
            }
        }

        return $sourceCodes;
    }
}
