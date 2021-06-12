# Вспомогательные классы для работы с модулями Битрикс

## Описание

Цель: минимизация дублирования кода при написании модулей.

## Возможности

#### Адаптер для настроек модуля

Простая обертка над *Bitrix\Main\Config\Option*

#### Готовая страница настроек модуля

Стандартная страница настроек модуля.

#### ModuleUtilsTrait

Общий для стандартного модуля функционал.

Файл `/install/index.php`:

```php
use Bitrix\Main\Localization\Loc;
use ProklUng\Module\Boilerplate\Module;
use ProklUng\Module\Boilerplate\ModuleUtilsTrait;

Loc::loadMessages(__FILE__);

class example_module extends CModule
{
    use ModuleUtilsTrait;

    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__.'/version.php';

        if (is_array($arModuleVersion)
            &&
            array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_FULL_NAME = 'module';
        $this->MODULE_VENDOR = 'example';
        $prefixLangCode = 'MODULE';

        $this->MODULE_NAME = Loc::getMessage($prefixLangCode.'_MODULE_NAME');
        $this->MODULE_ID = $this->MODULE_VENDOR.'.'.$this->MODULE_FULL_NAME;

        $this->MODULE_DESCRIPTION = Loc::getMessage($prefixLangCode.'_MODULE_DESCRIPTION');
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = Loc::getMessage($prefixLangCode.'_MODULE_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage($prefixLangCode.'MODULE_PARTNER_URI');

        $this->moduleManager = new Module(
            [
                'MODULE_ID' => $this->MODULE_ID,
                'VENDOR_ID' => $this->MODULE_VENDOR,
                'MODULE_VERSION' => $this->MODULE_VERSION,
                'MODULE_VERSION_DATE' => $this->MODULE_VERSION_DATE,
                'ADMIN_FORM_ID' => $this->MODULE_VENDOR.'_settings_form',
            ]
        );

        $this->moduleManager->addModuleInstance($this); // Регистрация экземпляра модуля.
        $this->options(); // Подготовка данных для генерации админки модуля
    }
  }  
```

Далее при необходимости можно переопределить стандартные методы модуля (типа `InstallEvents()`).

#### Module

Менеджер модулей, зарегистрированных в системе посредством этого boilerplate.

- ***showOptionsForm*** - вывод формы настроек модуля.
- ***getOptionsManager*** - экземпляр класса `Options\ModuleManager`. Настройки модуля.
- ***addModuleInstance($moduleObject)*** - Статика. Добавить экземпляр модуля. Объект, отнаследованный от `CModule`.
- ***getModuleInstance(string $moduleId)*** - Статика. Получить экземпляр класса модуля по ID.

#### Опции модуля

Добавляются методом `addOption` (и скопом - `addOptions`) класса `Options\ModuleManager`.

#### Меню опций модуля

В основном классе модуля должен быть отнаследован метод `getSchemaTabsAdmin`, описывающий массивом схему табов.

```php
    protected function getSchemaTabsAdmin() : array
    {
        // Все возможные параметры - https://dev.1c-bitrix.ru/api_help/main/general/admin.section/rubric_edit.php
        return ['tab1' => [
            'TAB' => 'Таб 1',
            'TITLE' => 'Таб 1',
        ],
            'tab2' => [
                'TAB' => 'Таб 2',
                'TITLE' => 'Таб 2',
            ]
        ];
    }
```

Также должен отнаследоваться метод `getSchemaOptionsAdmin`, возвращающий схему связки опций модуля с табами:

```php
    protected function getSchemaOptionsAdmin() : array
    {
        return [
            'Test_option_1' =>
                [
                    'label' => 'Тестовая опция 1',
                    'tab' => 'tab1', // На каком табе будет показываться input
                    'type' => 'text', // Тип input-а
                ],
            'Test_option_2' =>
                [
                    'label' => 'Тестовая опция 2',
                    'tab' => 'tab2', // На каком табе будет показываться input
                    'type' => 'text', // Тип input-а
                ],
        ];
    }
```
