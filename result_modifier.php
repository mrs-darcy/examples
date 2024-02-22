<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */

/** @var CBitrixComponent $component */

use Bitrix\Highloadblock as HL;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Context;
use Bitrix\Main\Entity\Query\Join;
use Bitrix\Main\Loader;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserTable;
use Bitrix\Sale\PropertyValueCollection;
use Bitrix\Main\Localization\Loc;

Loader::includeModule("highloadblock");

$dbRes = PropertyValueCollection::getList([
    'select' => ['*'],
    'filter' => [
        '=ORDER_ID' => $arResult['ID'],
    ]
]);

while ($item = $dbRes->fetch()) {

    if ($item['CODE'] == 'DOCS') {
        foreach ($item['VALUE'] as &$file) {
            $file = CFile::GetByID($file)->Fetch();
        }
        unset($file);
    }

    if ($item['CODE'] == 'DOC_KP') {
        $file = $item['VALUE'];
        $item['VALUE'] = CFile::GetByID($file)->Fetch();
    }

    if ($item['CODE'] == 'DOC_INVOICE_FOR_PAYMENT') {
        $file = $item['VALUE'];
        $item['VALUE'] = CFile::GetByID($file)->Fetch();
    }

    if ($item['CODE'] == 'LEFT_TO_PAY' && (!empty($item['VALUE']) || (int)$item['VALUE'] === 0)) {
        $item['FORMATTED'] = CCurrencyLang::CurrencyFormat($item['VALUE'], $arResult['CURRENCY']);
    }

    if ($item['CODE'] == 'INCOMING_DOCS') {
        foreach ($item['VALUE'] as &$file) {
            $file = CFile::GetByID($file)->Fetch();
        }
        unset($file);
    }

    $arResult['PROPERTY'][$item['CODE']] = $item;
}

if ($arResult['PROPERTY']['DOC_KP']['VALUE']) {
    if ($arResult['PROPERTY']['DOCS']['VALUE'] != null) {
        $arResult['PROPERTY']['DOCS']['VALUE'] = array_merge(
            $arResult['PROPERTY']['DOCS']['VALUE'],
            array($arResult['PROPERTY']['DOC_KP']['VALUE'])
        );
    } else {
        $arResult['PROPERTY']['DOCS']['VALUE'] = array($arResult['PROPERTY']['DOC_KP']['VALUE']);
    }
}

if ($arResult['PROPERTY']['DOC_INVOICE_FOR_PAYMENT']['VALUE']) {
    if ($arResult['PROPERTY']['DOCS']['VALUE'] != null) {
        $arResult['PROPERTY']['DOCS']['VALUE'] = array_merge(
            $arResult['PROPERTY']['DOCS']['VALUE'],
            array($arResult['PROPERTY']['DOC_INVOICE_FOR_PAYMENT']['VALUE'])
        );
    } else {
        $arResult['PROPERTY']['DOCS']['VALUE'] = array($arResult['PROPERTY']['DOC_INVOICE_FOR_PAYMENT']['VALUE']);
    }
}

if ($arResult['PROPERTY']['INCOMING_DOCS']['VALUE']) {
    if ($arResult['PROPERTY']['DOCS']['VALUE'] != null) {
        $arResult['PROPERTY']['DOCS']['VALUE'] = array_merge(
            $arResult['PROPERTY']['DOCS']['VALUE'],
            $arResult['PROPERTY']['INCOMING_DOCS']['VALUE']
        );
    } else {
        $arResult['PROPERTY']['DOCS']['VALUE'] = $arResult['PROPERTY']['INCOMING_DOCS']['VALUE'];
    }
}

$arrMapAddress = [];
$arResult['PICKUP_ADDRESS'] = '';
if($arResult['PROPERTY']['DELIVERY_COORDINATES']['VALUE']){
    $deliveryCoordinatesData = json_decode($arResult['PROPERTY']['DELIVERY_COORDINATES']['VALUE'], true);
}
if($deliveryCoordinatesData && $deliveryCoordinatesData['КоординатыМашин']){
    foreach ($deliveryCoordinatesData['КоординатыМашин'] as $coordinates) {
        $address = $coordinates['Адрес'] ? "<p><b>Адрес:</b> {$coordinates['Адрес']}</p>" : '';
        $address .= $coordinates['КПП'] ? "<p><b>КПП:</b> {$coordinates['КПП']}</p>" : '';
        if($coordinates['Широта'] && $coordinates['Долгота']){
            $arrMapAddress[] = [
                'coords' => [
                    $coordinates['Широта'],
                    $coordinates['Долгота']
                ],
                'options' => $address ? [
                    'balloonContentBody' => $address,
                ] : []
            ];
        }

        $arResult['PICKUP_ADDRESS'] .= $arResult['DELIVERY']['NAME'] == 'Самовывоз' ? $address : '';
    }
}

if ($arResult['PROPERTY']['address']['VALUE']) {
    foreach ($arResult['PROPERTY']['address']['VALUE'] as $address) {
        $arrMapAddress[] = ['address' => trim($address)];
    }
}

$arResult['DELIVERY_MAP_ADDRESS'] = $arrMapAddress ? htmlspecialchars(\Bitrix\Main\Web\Json::encode([
    'objects' => $arrMapAddress
])) : [];


$arResult['BASKET_ITEM_NAMES'] = [];
foreach ($arResult['BASKET'] as $basketItemId => $basketItem) {
    $arResult['BASKET'][$basketItemId]['IS_PANEL'] = (str_contains($basketItem['NAME'], 'Сэндвич') && str_contains($basketItem['NAME'], 'панель'));
    $prepareNameParts = [];
    $preparePropMatch = [];
    if (empty($basketItem['PROPS'])) {
        continue;
    }
    if ($basketItem['SKU_PROPERTY_ID']) {
        foreach ($basketItem['PROPS'] as $prop) {
            if ($prop['NAME'] == $prop['CODE']) {
                continue;
            }

            if (in_array($prop['CODE'], $boolAdditionalOptions)) {
                $prepareNameParts[] = $prop['NAME'];
                continue;
            }

            if (str_contains($prop['NAME'], 'требование')) {
                $prepareNameParts['REQUIREMENTS'][] = $prop['VALUE'];
                continue;
            }

            if ($prop['NAME'] != $prop['VALUE'] && !in_array($prop['CODE'], ['Quantity', 'QuantityPce'])) {
                if(strstr($prop['NAME'], ';')) {
                    $prepareNameParts['CHILD'][trim(trim(strstr($prop['NAME'], ';'), ';'))][] = [$prop['VALUE'] => [trim(strstr($prop['NAME'], ';', true), ';')]];
                    $preparePropMatch[] = trim(strstr($prop['NAME'], ';', true), ';');
                } else {
                    $prepareNameParts[] = trim($prop['NAME']) . ': ' . $prop['VALUE'];
                }
            }
        }
    }
    $propWidth = array();
    $propLength = array();
    foreach ($basketItem['PROPS'] as $prop) {
        if (in_array($prop['CODE'], ['Quantity', 'QuantityPce'])) {
            $arResult['BASKET'][$basketItemId]['PROPS'][$prop['CODE']] = $prop['VALUE'];
        }
        if($prop['CODE']=="Длина, мм")
        {
            $propLength = $prop;
        }
        if($prop['CODE']=="Ширина, мм")
        {
            $propWidth = $prop;
        }
    }

    $propQuantity = $arResult['BASKET'][$basketItemId]['PROPS']["Quantity"];
    $propQuantityPce = $arResult['BASKET'][$basketItemId]['PROPS']['QuantityPce'];

    if ($arResult['BASKET'][$basketItemId]['IS_PANEL'] == true && is_numeric($basketItem["PRICE"])) {

        if(!empty($propLength["VALUE"]) && empty($propQuantity) && empty($propQuantityPce)){
            $arResult['BASKET'][$basketItemId]["SQUARE_PRICE"]=round($basketItem["PRICE"]/($propLength["VALUE"]*$propWidth["VALUE"]/10**6),2);
            $arResult['BASKET'][$basketItemId]["SQUARE_QUANTITY"]=round(($propLength["VALUE"]*$propWidth["VALUE"]/10**6),3);
        }
    }

    $nameParts = array_diff($prepareNameParts, $preparePropMatch);
    $counter = 0;

    $arNamePart = $nameParts;
    foreach ($arNamePart as $key => $value) {
        if (!is_int($key)) {
            unset($arNamePart[$key]);
        }
    }
    foreach ($arNamePart as $key => $value) {
        $partMain .= $value;
        if($counter != count($arNamePart) - 1) {
            $partMain .= ', ';
        }
        $counter++;
    }
    unset($counter);
    
    if ($nameParts['CHILD']) {
        foreach ($nameParts['CHILD'] as $nkey => $nvalue) {
            foreach ($nvalue as $key => $value) {       
                $key = key($value);
                $value = $value[key($value)][0];

                if ($nkey) {
                    $arStr[$value][] =  $nkey . ': ' . $key;
                } else {
                    $arStr[$value][] =  $key;
                }
            }
        }
        foreach ($arStr as $value) {
            $partChild .= (key($arStr) ? key($arStr) . ' (' : '') . implode(', ', $value) . (key($arStr) ? ')' : '');
            if($counter != count($arStr) - 1) {
                $partChild .= ', ';
            }
            next($arStr);
            $counter++;
        }
        unset($counter);
    }

    if ($nameParts['REQUIREMENTS']) {
        $partRequirements = Loc::getMessage('PH_REQUIREMENTS') . ' (' . implode(', ', $nameParts['REQUIREMENTS']) . ')';
    }

    $additionalName = ($partMain ?: '') . ($partMain && $partChild ? ', ' : '') . ($partChild ?: '') . (($partMain || $partChild) && $partRequirements ? ', ' : '') . ($partRequirements ?: '');
    $arResult['BASKET_ITEM_NAMES'][$basketItemId] = empty($additionalName) ? $basketItem['NAME'] : "{$basketItem['NAME']} ($additionalName)";
    unset($arNamePart, $partMain, $arStr, $partChild, $partRequirements);
}