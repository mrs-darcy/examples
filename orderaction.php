<?php

namespace NDA\Main\Controller;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\LoaderException;
use Bitrix\Main\NotImplementedException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Sale\OrderTable;
use Bitrix\Sale\Order;
use Bitrix\Sale\Internals\OrderPropsTable;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Exception;
use NDA\Exchange\Entity\Internal\NDAQuerySendTable;
use NDA\Exchange\Logger;
use NDA\Exchange\Manager\OrderFileManagerSend;
use Bitrix\Main\Result;
use CFile;
use NDA\Exchange\Manager\RequestFileManagerSend;

class OrderAction extends Controller
{
    public function configureActions(): array
    {
        return [
            'setComment' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(
                        [
                            ActionFilter\HttpMethod::METHOD_GET,
                            ActionFilter\HttpMethod::METHOD_POST,
                        ]
                    ),
                    new ActionFilter\Csrf(),
                ],
                'postfilters' => []
            ],
            'uploadOrderDocuments' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(
                        [
                            ActionFilter\HttpMethod::METHOD_GET,
                            ActionFilter\HttpMethod::METHOD_POST,
                        ]
                    ),
                    new ActionFilter\Csrf(),
                ],
                'postfilters' => []
            ],
            'getInvoice' => [
                'prefilters' => [
                    new ActionFilter\Authentication(),
                    new ActionFilter\HttpMethod(
                        [
                            ActionFilter\HttpMethod::METHOD_GET,
                            ActionFilter\HttpMethod::METHOD_POST,
                        ]
                    ),
                    new ActionFilter\Csrf(),
                ],
                'postfilters' => []
            ],
        ];
    }

    /**
     * @param int $orderId
     * @param string $comment
     * @return bool
     * @throws ArgumentException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function setCommentAction(int $orderId, string $comment): bool
    {
        global $USER;
        if (
            $orderId > 0
            && Loader::includeModule('sale')
        ) {
            $order = OrderTable::getByPrimary($orderId)->fetch();

            if ($USER->GetID() == $order['CREATED_BY']) {
                OrderTable::update($order['ID'], [
                    'USER_DESCRIPTION' => $comment
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Действие, загружающее файлы в таблицу b_file, и сохраняющее их в определенном св-ве.
     * @param int $orderId
     * @param string $type
     * @return array
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws Exception
     */
    public function uploadOrderDocumentsAction(int $orderId, string $type): array
    {
        if (
            $orderId <= 0
            || !$type
            || !Loader::includeModule('sale')
        ) {
            return ['success' => false];
        }

        if (!$arRowOrder = OrderTable::getList([
            'select' => ['ID_1C'],
            'filter' => [
                '=ID' => $orderId,
            ]
        ])->fetch()) {
            return [
                'errors' => 'Failed to get order',
                'success' => false
            ];
        }

        $request = Application::getInstance()->getContext()->getRequest();

        $file = $request->getFileList()->toArray();
        if(empty($file)) {
            return [
                'errors' => 'Failed to get filelist from request',
                'success' => false
            ];
        }

        $fileKey = key($file);
        if($fileKey === null){
            return [
                'errors' => 'Failed to get file key from file array',
                'success' => false
            ];
        }
        
        $arFile = [
            'name' => $file[$fileKey]['name'],
            'size' => $file[$fileKey]['size'],
            'type' => $file[$fileKey]['type'],
            'tmp_name' => $file[$fileKey]['tmp_name'],
            'del' => 'N',
            'MODULE_ID' => 'NDA.main'
        ];
        $fid = (int)CFile::SaveFile($arFile, "documents");
        if($fid == 0){
            return [
                'errors' => 'Failed to get file id',
                'success' => false
            ];
        }
        $propCode = 'DOCS';

        $update = self::updateOrderProp($orderId, $fid, $propCode);
        if($update != true){
            return [
                'errors' => 'Failed to update property',
                'success' => false
            ];
        } 
        self::sendOrderFileToQueue($fid, $orderId, $arRowOrder['ID_1C'], $type);
        return ['success' => true];
    }

    /**
     * Обновляет значение св-ва по коду св-ва. Если значение уже существует, то оно комбинируется с $fid и записывается, иначе просто записывается.
     * @param int $orderId
     * @param int $fid
     * @param string $propCode
     * @return bool
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public function updateOrderProp(int $orderId, int $fid, string $propCode): bool
    {
        if ($arRowProp = OrderPropsValueTable::getList([
            'filter' => [
                'ORDER_ID' => $orderId,
                'CODE' => $propCode,
            ]
        ])->fetch()) {
            if (is_array($arRowProp['VALUE'])) {
                $arValue = array_merge($arRowProp['VALUE'], array($fid));
            } else {
                $arValue = array($fid);
            }
            $resultUpdate = OrderPropsValueTable::update($arRowProp['ID'], ["VALUE" => $arValue]);
            return $resultUpdate->isSuccess();
        }  
        return false;
    }

    /**
     * Принимает $orderId, делает запрос к RequestFileManagerSend
     * и манипуляции с св-вом заказа GET_INVOICE_FOR_PAYMENT, обновление заказа
     * @param int $orderId
     * @return array
     * @throws ArgumentException
     * @throws ArgumentNullException
     * @throws LoaderException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentOutOfRangeException
     * @throws NotImplementedException
     * @throws Exception
     */
    public function getInvoiceAction(int $orderId): array
    {
        if (
            $orderId <= 0
            || !Loader::includeModule('sale')
            || !Loader::includeModule('NDA.exchange')
            || !Loader::includeModule('NDA.main')
        ) {
            return ['success' => false];
        }

        $result = new Result();

        $order = Order::load($orderId);
        if (!$order) {
            return [
                'errors' => 'Failed to load order by id',
                'success' => false
            ];
        }

        $propertyCollection = $order->getPropertyCollection();

        $getInvoice = 'GET_INVOICE_FOR_PAYMENT';
        $property = $propertyCollection->getItemByOrderPropertyCode($getInvoice);
        if (!$property) {
            return [
                'errors' => 'Failed to get property by property code',
                'success' => false
            ];
        }

        $res = NDAQuerySendTable::addToQueue(
            RequestFileManagerSend::getEntityType(),
            $orderId
        );

        if (!$res->isSuccess()) {
            $result->addErrors($res->getErrors());
        } else {
            $property->setField('VALUE', 'Y');

            $res = $order->save();

            $result->addErrors($res->getErrors());
            $result->addErrors($res->getWarnings());
        }

        return [
            'errors' => $result->getErrorMessages(),
            'success' => $result->isSuccess()
        ];
    }

    /**
     * @param $fileId
     * @param $orderId
     * @param $orderId1C
     * @param string $type
     * @return void
     * @throws LoaderException
     */
    public static function sendOrderFileToQueue($fileId, $orderId, $orderId1C, string $type = ''): void
    {
        if (Loader::includeModule('NDA.exchange')) {
            $result = NDAQuerySendTable::addToQueue(
                OrderFileManagerSend::getEntityType(),
                json_encode([
                    'ORDER_ID' => $orderId,
                    'ORDER_ID_1C' => $orderId1C,
                    'FILE_ID' => $fileId,
                    'TYPE' => $type
                ], JSON_UNESCAPED_UNICODE),
                NDAQuerySendTable::ACTION_UPDATE
            );

            if (!$result->isSuccess()) {
                Logger::addLog(
                    'OrderAction',
                    $result->isSuccess(),
                    $result->getErrorMessages());
            }
        }
    }
}
