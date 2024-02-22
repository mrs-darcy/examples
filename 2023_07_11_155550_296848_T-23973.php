<?php

use Arrilot\BitrixMigrations\BaseMigrations\BitrixMigration;
use Arrilot\BitrixMigrations\Exceptions\MigrationException;
use Bitrix\Catalog\Model\Product; 
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\VatTable;
use Bitrix\Main\Loader;

class T2397320230711155550296848 extends BitrixMigration
{
    /**
     * Run the migration.
     *
     * @return mixed
     * @throws MigrationException
     */
    public function up()
    {
        if (!Loader::includeModule('catalog')) {
            throw new MigrationException('Не удалось подключить модуль catalog.');
        }

        $vat = $this->getVatId();
        if (empty($vat)) {
            throw new MigrationException('Не удалось получить ставку НДС.');
        }

        $filter = ['=VAT_ID' => '', '=VAT_INCLUDED' => 'N'];
        $productIds = $this->getProductIds($filter);

        $params = ['VAT_ID' => $vat, 'VAT_INCLUDED' => 'Y'];
        foreach ($productIds as $productId) {
            $result = Product::update($productId, $params);
            if (!$result->isSuccess()) {
                throw new MigrationException("Не удалось обновить параметры товара: " . implode('<br/>', $result->getErrorMessages()));
            }
        }
        
    }

    /**
     * Reverse the migration.
     *
     * @return mixed
     * @throws MigrationException
     */
    public function down()
    {
        if (!Loader::includeModule('catalog')) {
            throw new MigrationException('Не удалось подключить модуль catalog');
        }

        $vat = $this->getVatId();
        if (empty($vat)) {
            throw new MigrationException('Не удалось получить ставку НДС.');
        }

        $filter = ['=VAT_ID' => $vat, '=VAT_INCLUDED' => 'Y'];
        $productIds = $this->getProductIds($filter);

        $params = ['VAT_ID' => '', 'VAT_INCLUDED' => 'N'];
        foreach ($productIds as $productId) {
            $result = Product::update($productId, $params);
            if (!$result->isSuccess()) {
                throw new MigrationException("Не удалось обновить параметры товара: " . implode('<br/>', $result->getErrorMessages()));
            }
        }

    }


    /**
     * @return int|null
     */
    private function getVatId(): ?int
    {
        return VatTable::getRow([
            'filter' => ['=RATE' => '20.00', '=ACTIVE' => 'Y'],
            'order' => ['ID' => 'ASC'],
            'select' => ['ID']
        ])['ID'];
    }

    /**
     * @return array
     */
    private function getProductIds(array $filter): array
    {
        return array_column(ProductTable::getList([
            'select' => ['ID'],
            'filter' => $filter
        ])->fetchAll(), 'ID');
    }

}
