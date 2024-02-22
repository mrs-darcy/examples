<?php

use Arrilot\BitrixMigrations\BaseMigrations\BitrixMigration;
use Arrilot\BitrixMigrations\Exceptions\MigrationException;
use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;

class T2424820230725150707132990 extends BitrixMigration
{

    private static array $arOrder = [
        '201b983c-a90b-11ed-8136-005056bbc7a6' => 100,
        'dbe2aaf8-d47c-11ed-8138-005056bbc7a6' => 200,
        '201b983f-a90b-11ed-8136-005056bbc7a6' => 300,
        '33fb3c66-a90b-11ed-8136-005056bbc7a6' => 400,
        '3b4f8ac7-a90b-11ed-8136-005056bbc7a6' => 500,
        '812c1c8f-a93c-11ed-8136-005056bbc7a6' => 600,
        '4a42c2c0-a90b-11ed-8136-005056bbc7a6' => 700
    ];

    /**
     * Run the migration.
     *
     * @return mixed
     * @throws \Exception
     */
    public function up()
    {
        if (!Loader::includeModule('iblock')) {
            throw new MigrationException('Не удалось подключить модуль iblock.');
        }
        
        $sectionSort = $this->getSectionsAr();
        $bs = new CIBlockSection;

        foreach ($sectionSort as $key => $value) {
            if (self::$arOrder[$key]) {
                $params = ['SORT' => self::$arOrder[$key]];
                $res = $bs->Update($value, $params, true, true, false);
                if (!$res) {
                    throw new MigrationException('Не удалось обновить параметры раздела.');
                }
            } 
        }
    }

    /**
     * Reverse the migration.
     *
     * @return mixed
     * @throws \Exception
     */
    public function down()
    {
        if (!Loader::includeModule('iblock')) {
            throw new MigrationException('Не удалось подключить модуль iblock.');
        }

        $sectionSort = $this->getSectionsAr();
        $bs = new CIBlockSection;

        foreach ($sectionSort as $key => $value) {
            if (self::$arOrder[$key]) {
                $params = ['SORT' => 500];
                $res = $bs->Update($value, $params);
                if (!$res) {
                    throw new MigrationException('Не удалось обновить параметры раздела.');
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getSectionsAr(): array
    {
        return array_column(SectionTable::getList([
            'select' => ['ID', 'XML_ID'],
            'filter' => ['=IBLOCK_ID' => CATALOG_IBLOCK_ID,  '=ACTIVE' => 'Y'],
            'order' => ['ID' => 'ASC'],
        ])->fetchAll(), 'ID', 'XML_ID');
    }
}