<?php
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Event;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserTable;
use Bitrix\Main\UserPhoneAuthTable;
use Bitrix\Main\Loader;
use NDA\Main\Service\Sms;

/**
 * @param $event
 * @param $lid
 * @param $arFields
 * @return void
 * @throws ArgumentException
 * @throws ObjectPropertyException
 * @throws SystemException
 */
function OnBeforeEventAdd(&$event, &$lid, &$arFields): void
{
    if ($event !== 'FORM_FILLING_SIMPLE_FORM_1') {
        return;
    }

    $arUser = Bitrix\Main\UserTable::getRow(
        [
            'filter' => [
                '=ID' => $arFields['RS_USER_ID'],
            ],
            'select' => ['UF_COMPANY'],
        ]
    );

    $arFields['COMPANY_ID'] = $arUser['UF_COMPANY'] ?? '-';
}

/**
 * @param $event
 * @return void
 * @throws ArgumentException
 * @throws ObjectPropertyException
 * @throws SystemException
 */
function OnSaleStatusOrderChange(Event $event): void
{
	$order = $event->getParameter("ENTITY");
    $changedValues = $order->getFields()->getChangedValues();

    if ($changedValues['STATUS_ID'] == 'F') {
        $arUser = Bitrix\Main\UserTable::getRow(
            [
                'filter' => [
                    '=ID' => $order->getUserId(),
                ],
                'select' => ['EMAIL', 'PERSONAL_PHONE', 'UF_NOTICE_ORDER_SMS', 'UF_NOTICE_ORDER_EMAIL'],
            ]
        );

        if ($arUser['UF_NOTICE_ORDER_EMAIL']) {
            Bitrix\Main\Mail\Event::send(array(
                'EVENT_NAME' => 'SALE_STATUS_CHANGED_AF',
                'LID' => 's1',
                'C_FIELDS' => [
                    'EMAIL_TO' => $arUser['EMAIL'],
                    'ORDER_ID' => $order->getId()
                ]),
            );
        }

        if ($arUser['UF_NOTICE_ORDER_SMS']) {

            $authUserPhone = UserPhoneAuthTable::getRow(
                [
                    'filter' => ['USER_ID' => $order->getUserId()],
                    'select' => ['PHONE_NUMBER'],
                ]
            );
            $phone = $arUser['PERSONAL_PHONE'] ?: $authUserPhone['PHONE_NUMBER'];
            if (Loader::includeModule("NDA.main") && $phone) {
                (new Sms())->sendMessage(
                    $phone,
                    'SALE_STATUS_CHANGED_AF',
                    [
                        'USER_PHONE' => $arUser['EMAIL'],
                        'ORDER_ID' => $order->getId()
                    ]
                );
            }
        }
    }
}
