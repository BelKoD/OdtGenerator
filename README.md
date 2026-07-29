# ODT Generator

PHP-библиотека для генерации ODT-документов из HTML.

## Описание

Эта библиотека позволяет конвертировать HTML-разметку в формат ODT (OpenDocument Text), который может быть открыт в таких редакторах, как LibreOffice Writer, Apache OpenOffice Writer и других.

## Требования

- PHP >= 7.0
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

```php
<?php

require_once 'vendor/autoload.php';

use BelKoD\OdtGenerator\OdtGenerator;

$html = '<h1>Заголовок</h1><p>Это пример текста.</p>';
$outputFile = 'document.odt';

$generator = new OdtGenerator($html, $outputFile);
$generator->generate();

// Сохранение файла
file_put_contents('my-document.odt', $generator->getOutput());
```

## Структура проекта

```
├── src/
│   ├── OdtGenerator.php       # Основной класс генератора
│   ├── StyleGenerator.php     # Генератор стилей
│   ├── StyleHelper.php        # Помощник для работы со стилями
│   ├── TagHandlerFactory.php  # Фабрика обработчиков HTML-тегов
│   └── HtmlTags/              # Обработчики отдельных HTML-тегов
├── tests/                     # Тесты
├── composer.json              # Конфигурация Composer
└── LICENSE                    # Лицензия GPL-3.0-or-later
```

## Запуск тестов

```bash
composer test
```

## Лицензия

GPL-3.0-or-later

## Автор

**Belokopytov Konstantin**
- Email: dr.belkod@yandex.ru
- Website: https://belkod.ru

## Вклад в проект

Приветствуются pull request'ы и сообщения об ошибках через GitHub Issues.
