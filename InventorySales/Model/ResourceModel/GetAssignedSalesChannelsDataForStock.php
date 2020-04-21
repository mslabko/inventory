<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Provides linked sales channels by given stock id
 */
class GetAssignedSalesChannelsDataForStock
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var array
     */
    private $stockCache = [];

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Given a stock id, return array of sales channels assigned to it
     *
     * @param int $stockId
     * @return array
     */
    public function execute(int $stockId): array
    {
        if (isset($this->stockCache[$stockId])) {
            return $this->stockCache[$stockId];
        }
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('inventory_stock_sales_channel');

        $select = $connection->select()
            ->from($tableName)
            ->where('stock_id = ?', $stockId);

        $stockData = $connection->fetchAll($select);
        $this->stockCache[$stockId] = $stockData;
        return $stockData;
    }
}
