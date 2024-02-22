<?php

use Arrilot\BitrixMigrations\BaseMigrations\BitrixMigration;
use Arrilot\BitrixMigrations\Exceptions\MigrationException;

class T2424820230727093334557444 extends BitrixMigration
{
    private static array $arOrder = [
        'NEW_DATA' => [
            'FORM_NAME' => 'Перезвоните мне',
            'FIELD_TITLE' => 'Номер телефона',
        ],
        'OLD_DATA' => [
            'FORM_NAME' => 'Позвонить мне',
            'FIELD_TITLE' => 'Введите номер телефона',
        ]
    ];

    /**
     * Run the migration.
     *
     * @return mixed
     * @throws \Exception
     */
    public function up()
    {
        if(!CModule::IncludeModule('form'))
        {
            throw new MigrationException('Не удалось подключить модуль form.');
        }

        $arFilterForm = ['NAME' => self::$arOrder['OLD_DATA']['FORM_NAME']];
        $nameForm = self::$arOrder['NEW_DATA']['FORM_NAME'];

        $formId = $this->setFormName($arFilterForm, $nameForm);
        if (!$formId) {
            throw new MigrationException('Не удалось обновить форму. ID: ' . $formId);
        }
        
        $arFilterField = ['TITLE' => self::$arOrder['OLD_DATA']['FIELD_TITLE']];
        $titleField = self::$arOrder['NEW_DATA']['FIELD_TITLE'];
        
        $field = $this->setFieldTitle($arFilterField, $formId, $titleField);
        if (!$field) {
            throw new MigrationException('Не удалось обновить поле формы.');
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
        if(!CModule::IncludeModule('form'))
        {
            throw new MigrationException('Не удалось подключить модуль form.');
        }

        $arFilterForm = ['NAME' => self::$arOrder['NEW_DATA']['FORM_NAME']];
        $nameForm = self::$arOrder['OLD_DATA']['FORM_NAME']; 

        $formId = $this->setFormName($arFilterForm, $nameForm);
        if (!$formId) {
            throw new MigrationException('Не удалось обновить форму. ID: ' . $formId);
        }
        
        $arFilterField = ['TITLE' => self::$arOrder['NEW_DATA']['FIELD_TITLE']];
        $titleField = self::$arOrder['OLD_DATA']['FIELD_TITLE'];
        
        $field = $this->setFieldTitle($arFilterField, $formId, $titleField);
        if (!$field) {
            throw new MigrationException('Не удалось обновить поле формы.');
        }
    }

    /**
     * @return int
     */
    private function setFormName(array $arFilterForm, string $nameForm): int
    {
        $rsForms = CForm::GetList($by='s_id', $order='desc', $arFilterForm);
        while ($arForm = $rsForms->Fetch())
        {
            $formId = $arForm['ID'];
            $formSID = $arForm['SID'];
        }

        $fields = [
            'NAME' => $nameForm,
            'SID' => $formSID,
        ];
        
        return CForm::Set($fields, $formId, 'N');
    }

    /**
     * @return int
     */
    private function setFieldTitle(array $arFilterField, int $formId, string $titleField): int
    {
        $rsFields = CFormField::GetList($formId, 'N', $by='s_id', $order='desc', $arFilterField);
        while ($arField = $rsFields->Fetch())
        {
            $fieldId = $arField['ID'];
            $fieldSID = $arField['SID'];
        }

        $fields = [
            'TITLE' => $titleField,
            'FORM_ID' => $formId,
            'SID' => $fieldSID,
        ];

        return CFormField::Set($fields, $fieldId, 'N', 'Y');
    }

}
