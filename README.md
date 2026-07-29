# ODT Generator

PHP-библиотека для генерации ODT-документов из HTML.

## Описание

Эта библиотека позволяет конвертировать HTML-разметку в формат ODT (OpenDocument Text), который может быть открыт в таких редакторах, как LibreOffice Writer, Apache OpenOffice Writer и других.

Библиотека следует принципам SOLID, использует внедрение зависимостей и предоставляет интерфейсы для легкой расширяемости и тестирования.

## Требования

- PHP >= 7.0
- ext-zip (для работы с архивами)
- ext-dom (для работы с XML)
- ext-intl (для тестирования)

## Установка

### Через Composer

```bash
composer require belkod/odt-generator
```

### Вручную

Склонируйте репозиторий и подключите файлы вручную:

```bash
git clone https://github.com/belkod/odt-generator.git
```

## Использование

### Базовый пример

```php
<?php

require_once 'vendor/autoload.php';

use BelKoD\OdtGenerator\OdtGenerator;

$html = '<h1>Заголовок</h1><p>Это пример текста.</p>';
$outputFile = 'document.odt';

$generator = new OdtGenerator($html, $outputFile);
$generator->generate();

// Сохранение файла
file_put_contents('my-document.odt', $generator->getOutputFile());
```

### Расширенное использование с кастомным архиватором

```php
<?php

require_once 'vendor/autoload.php';

use BelKoD\OdtGenerator\OdtGenerator;
use BelKoD\OdtGenerator\OdtArchiver;

// Создание генератора с кастомным архиватором
$archiver = new OdtArchiver();
$generator = new OdtGenerator($html, $outputFile, $archiver);

// Генерация документа
$generator->generate();

// Получение бинарных данных ODT
$odtData = $generator->getOutputFile();
```

### Работа с таблицами и изображениями

```php
<?php

$html = '
<table>
    <tr><td>Ячейка 1</td><td>Ячейка 2</td></tr>
    <tr><td>Ячейка 3</td><td>Ячейка 4</td></tr>
</table>
<img src="image.jpg" alt="Пример изображения">
';

$generator = new OdtGenerator($html, 'document.odt');
$generator->generate();
file_put_contents('document-with-table.odt', $generator->getOutputFile());
```

## Архитектура

Библиотека состоит из следующих основных компонентов:

- **OdtGenerator** — основной класс, управляющий процессом генерации
- **OdtArchiver** — отвечает за создание ZIP-архива ODT
- **StyleGenerator** — генерирует стили документа на основе CSS
- **StyleHelper** — утилиты для преобразования стилей
- **TagHandlerFactory** — фабрика обработчиков HTML-тегов
- **HtmlTags/** — обработчики отдельных HTML-тегов (p, h1-h6, table, img и др.)

Все основные классы реализуют интерфейсы для возможности мокирования и расширения:

- `OdtGeneratorInterface`
- `StyleGeneratorInterface`
- `OdtArchiverInterface`

## Структура проекта

```
├── src/
│   ├── Exception/               # Кастомные исключения
│   │   ├── IOException.php
│   │   ├── OdtGeneratorException.php
│   │   └── ValidationException.php
│   ├── HtmlTags/                # Обработчики отдельных HTML-тегов
│   │   ├── BrHandler.php
│   │   ├── ContainerTagHandler.php
│   │   ├── HeadingHandler.php
│   │   ├── IgnoredTagHandler.php
│   │   ├── ImgHandler.php
│   │   ├── ListHandler.php
│   │   ├── NpHandler.php
│   │   ├── PHandler.php
│   │   ├── PageFooterHandler.php
│   │   ├── PageHeaderHandler.php
│   │   ├── SpanHandler.php
│   │   ├── TableHandler.php
│   │   ├── TagHandler.php
│   │   ├── TagHandlerInterface.php
│   │   ├── TbodyHandler.php
│   │   ├── TdHandler.php
│   │   ├── ThHandler.php
│   │   ├── TheadHandler.php
│   │   └── TrHandler.php
│   ├── Utils/                   # Утилиты
│   │   └── Misc.php
│   ├── OdtGenerator.php         # Основной класс генератора
│   ├── OdtGeneratorInterface.php
│   ├── OdtArchiver.php          # Класс для работы с ZIP-архивами
│   ├── OdtArchiverInterface.php
│   ├── StyleGenerator.php       # Генератор стилей
│   ├── StyleGeneratorInterface.php
│   ├── StyleHelper.php          # Утилиты для преобразования стилей
│   └── TagHandlerFactory.php    # Фабрика обработчиков тегов
├── tests/                       # Тесты
│   ├── OdtGeneratorTest.php
│   ├── OdtArchiverTest.php
│   └── TableWidthTest.php
├── composer.json                # Конфигурация Composer
└── LICENSE                      # Лицензия GPL-3.0-or-later
```

## Запуск тестов

```bash
# Установка зависимостей
composer install

# Запуск всех тестов
composer test

# Или напрямую через PHPUnit
vendor/bin/phpunit
```

## Особенности

- Поддержка кириллицы и UTF-8
- Преобразование CSS-стилей в формат ODF
- Работа с таблицами, изображениями и текстовым форматированием
- Возможность кастомизации через внедрение зависимостей
- Полное покрытие тестами

## Лицензия

GPL-3.0-or-later

## Автор

**Belokopytov Konstantin**
- Email: dr.belkod@yandex.ru
- Website: https://belkod.ru

## Вклад в проект

Приветствуются pull request'ы и сообщения об ошибках через GitHub Issues.
