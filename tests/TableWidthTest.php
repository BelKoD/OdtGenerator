<?php

namespace BelKoD\OdtGenerator\Tests;

use BelKoD\OdtGenerator\OdtGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для проверки генерации ODT документов с таблицами
 */
class TableWidthTest extends TestCase
{
    private $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDir = sys_get_temp_dir() . '/odt_tests_' . uniqid();
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Очищаем временные файлы
        if (is_dir($this->outputDir)) {
            $this->recursiveDelete($this->outputDir);
        }
        parent::tearDown();
    }

    /**
     * Рекурсивное удаление директории
     */
    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Извлечение content.xml из ODT файла
     */
    private function extractContentXml(string $odtPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($odtPath) !== true) {
            throw new \Exception("Не удалось открыть ODT файл: {$odtPath}");
        }
        
        $contentXml = $zip->getFromName('content.xml');
        $zip->close();
        
        if ($contentXml === false) {
            throw new \Exception("Не удалось извлечь content.xml из ODT файла");
        }
        
        return $contentXml;
    }

    /**
     * Проверка наличия ширины колонок в XML
     */
    private function assertColumnWidthExists(string $contentXml, string $expectedWidth, string $message = '')
    {
        $this->assertStringContainsString(
            'table:column-width="' . $expectedWidth . '"',
            $contentXml,
            $message ?: "Ожидалась ширина колонки '{$expectedWidth}' в content.xml"
        );
    }

    /**
     * Проверка отсутствия ширины колонок в XML
     */
    private function assertColumnWidthNotExists(string $contentXml, string $unexpectedWidth, string $message = '')
    {
        $this->assertStringNotContainsString(
            'table:column-width="' . $unexpectedWidth . '"',
            $contentXml,
            $message ?: "Не ожидалась ширина колонки '{$unexpectedWidth}' в content.xml"
        );
    }

    /**
     * Тест 1: Простая таблица с width атрибутом
     */
    public function testTableWithWidthAttribute()
    {
        $html = '<table>
            <tr>
                <td width="5cm">Колонка 1</td>
                <td width="10cm">Колонка 2</td>
                <td width="7cm">Колонка 3</td>
            </tr>
            <tr>
                <td>Ячейка 1-1</td>
                <td>Ячейка 1-2</td>
                <td>Ячейка 1-3</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_width_attr.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем, что ширина колонок присутствует в XML
        $this->assertColumnWidthExists($contentXml, '5.000000cm', 'Ширина первой колонки должна быть 5cm');
        $this->assertColumnWidthExists($contentXml, '10.000000cm', 'Ширина второй колонки должна быть 10cm');
        $this->assertColumnWidthExists($contentXml, '7.000000cm', 'Ширина третьей колонки должна быть 7cm');
    }

    /**
     * Тест 2: Таблица со style width
     */
    public function testTableWithStyleWidth()
    {
        $html = '<table>
            <tr>
                <td style="width:6cm;">Колонка 1</td>
                <td style="width:12cm;">Колонка 2</td>
                <td style="width:8cm;">Колонка 3</td>
            </tr>
            <tr>
                <td>Ячейка 1-1</td>
                <td>Ячейка 1-2</td>
                <td>Ячейка 1-3</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_style_width.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем, что ширина колонок присутствует в XML
        $this->assertColumnWidthExists($contentXml, '6.000000cm', 'Ширина первой колонки должна быть 6cm');
        $this->assertColumnWidthExists($contentXml, '12.000000cm', 'Ширина второй колонки должна быть 12cm');
        $this->assertColumnWidthExists($contentXml, '8.000000cm', 'Ширина третьей колонки должна быть 8cm');
    }

    /**
     * Тест 3: Смешанные способы задания ширины
     */
    public function testTableWithMixedWidthStyles()
    {
        $html = '<table>
            <tr>
                <td width="5cm">Атрибут width</td>
                <td style="width:10cm;">CSS style</td>
                <td width="7cm">Снова атрибут</td>
            </tr>
            <tr>
                <td>Данные 1</td>
                <td>Данные 2</td>
                <td>Данные 3</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_mixed_width.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        $this->assertColumnWidthExists($contentXml, '5.000000cm');
        $this->assertColumnWidthExists($contentXml, '10.000000cm');
        $this->assertColumnWidthExists($contentXml, '7.000000cm');
    }

    /**
     * Тест 4: Таблица с colspan и шириной
     */
    public function testTableWithColspanAndWidth()
    {
        $html = '<table>
            <tr>
                <td width="6cm" colspan="2">Объединённая ячейка</td>
                <td style="width:8cm;">Колонка 3</td>
            </tr>
            <tr>
                <td>Ячейка 1-1</td>
                <td>Ячейка 1-2</td>
                <td>Ячейка 1-3</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_colspan_width.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем, что ширина применяется к колонкам с учётом colspan
        $this->assertColumnWidthExists($contentXml, '6.000000cm', 'Ширина должна применяться с учётом colspan');
        $this->assertColumnWidthExists($contentXml, '8.000000cm');
    }

    /**
     * Тест 5: Вложенная таблица
     */
    public function testNestedTable()
    {
        $html = '<table>
            <tr>
                <td width="15cm">
                    <p>Внешняя таблица</p>
                    <table>
                        <tr>
                            <td width="4cm">Внутренняя 1-1</td>
                            <td style="width:6cm;">Внутренняя 1-2</td>
                        </tr>
                    </table>
                </td>
                <td style="width:8cm;">Внешняя ячейка 2</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_nested_table.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем наличие всех ширин для внешней и внутренней таблицы
        $this->assertColumnWidthExists($contentXml, '15.000000cm', 'Внешняя таблица - колонка 1');
        $this->assertColumnWidthExists($contentXml, '8.000000cm', 'Внешняя таблица - колонка 2');
        $this->assertColumnWidthExists($contentXml, '4.000000cm', 'Внутренняя таблица - колонка 1');
        $this->assertColumnWidthExists($contentXml, '6.000000cm', 'Внутренняя таблица - колонка 2');
    }

    /**
     * Тест 6: Таблица с заголовками (th)
     */
    public function testTableWithHeaders()
    {
        $html = '<table>
            <thead>
                <tr>
                    <th width="8cm">Заголовок 1</th>
                    <th style="width:12cm;">Заголовок 2</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Данные 1</td>
                    <td>Данные 2</td>
                </tr>
            </tbody>
        </table>';

        $generator = new OdtGenerator($html, 'test_headers.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        $this->assertColumnWidthExists($contentXml, '8.000000cm');
        $this->assertColumnWidthExists($contentXml, '12.000000cm');
    }

    /**
     * Тест 7: Текст на кириллице и латинице
     */
    public function testCyrillicAndLatinText()
    {
        $html = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
        <p>Ло́рем ипсу́м — это текст-«рыба», часто используемый в печати и веб-дизайне.</p>
        <table>
            <tr>
                <td width="10cm">English text</td>
                <td style="width:10cm;">Русский текст</td>
            </tr>
            <tr>
                <td>Data 1-1</td>
                <td>Данные 1-2</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_multilingual.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем наличие текста
        $this->assertStringContainsString('Lorem ipsum', $contentXml, 'Латинский текст должен сохраниться');
        $this->assertStringContainsString('Ло́рем ипсу́м', $contentXml, 'Кириллический текст должен сохраниться');
        
        // Проверяем ширину колонок
        $this->assertColumnWidthExists($contentXml, '10.000000cm');
    }

    /**
     * Тест 8: Полный тестовый документ из fixtures
     */
    public function testFullDocumentFromFile()
    {
        $fixturePath = __DIR__ . '/fixtures/test_document.html';
        
        $this->assertFileExists($fixturePath, 'Файл fixtures/test_document.html должен существовать');
        
        $html = file_get_contents($fixturePath);
        
        $generator = new OdtGenerator($html, 'test_full_document.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем наличие различных элементов
        $this->assertStringContainsString('Lorem ipsum', $contentXml, 'Текст Lorem ipsum должен присутствовать');
        $this->assertStringContainsString('Ло́рем ипсу́м', $contentXml, 'Кириллический текст должен присутствовать');
        
        // Проверяем наличие таблиц
        $this->assertStringContainsString('<table:table', $contentXml, 'Должны присутствовать таблицы');
        
        // Проверяем наличие различных ширин колонок из тестового файла
        $expectedWidths = [
            '5.000000cm',
            '10.000000cm',
            '7.000000cm',
            '6.000000cm',
            '8.000000cm',
            '15.000000cm',
            '4.000000cm',
            '12.000000cm'
        ];
        
        foreach ($expectedWidths as $width) {
            $this->assertColumnWidthExists(
                $contentXml, 
                $width, 
                "Ширина {$width} должна присутствовать в документе"
            );
        }
    }

    /**
     * Тест 9: Таблица без указания ширины
     */
    public function testTableWithoutWidth()
    {
        $html = '<table>
            <tr>
                <td>Ячейка 1</td>
                <td>Ячейка 2</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_no_width.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем, что таблица создана без атрибута ширины
        $this->assertStringContainsString('<table:table-column/>', $contentXml, 
            'Колонки без ширины должны создаваться без атрибута table:column-width');
    }

    /**
     * Тест 10: Различные единицы измерения
     */
    public function testDifferentUnits()
    {
        $html = '<table>
            <tr>
                <td width="5cm">Сантиметры</td>
                <td style="width:50mm;">Миллиметры</td>
                <td style="width:2in;">Дюймы</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_units.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Все значения должны быть конвертированы в cm
        $this->assertStringContainsString('table:column-width=', $contentXml, 
            'Должны присутствовать колонки с шириной');
    }

    /**
     * Тест 11: Форматирование текста в ячейках
     */
    public function testTextFormattingInCells()
    {
        $html = '<table>
            <tr>
                <td width="10cm"><strong>Жирный текст</strong></td>
                <td style="width:10cm;"><em>Курсив</em></td>
            </tr>
            <tr>
                <td><span style="color:red;">Цветной текст</span></td>
                <td>Обычный текст</td>
            </tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_formatting.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Проверяем наличие форматирования
        $this->assertStringContainsString('<text:span', $contentXml, 
            'Должны присутствовать элементы text:span для форматирования');
        
        // Проверяем ширину колонок
        $this->assertColumnWidthExists($contentXml, '10.000000cm');
    }

    /**
     * Тест 12: Несколько таблиц подряд
     */
    public function testMultipleTables()
    {
        $html = '<table>
            <tr><td width="5cm">Таблица 1</td></tr>
        </table>
        <table>
            <tr><td style="width:10cm;">Таблица 2</td></tr>
        </table>
        <table>
            <tr><td width="15cm">Таблица 3</td></tr>
        </table>';

        $generator = new OdtGenerator($html, 'test_multiple_tables.odt');
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        $contentXml = $this->extractContentXml($odtPath);
        
        // Считаем количество таблиц
        preg_match_all('/<table:table/', $contentXml, $matches);
        $tableCount = count($matches[0]);
        
        $this->assertEquals(3, $tableCount, 'Должно быть создано 3 таблицы');
        
        // Проверяем наличие всех ширин
        $this->assertColumnWidthExists($contentXml, '5.000000cm');
        $this->assertColumnWidthExists($contentXml, '10.000000cm');
        $this->assertColumnWidthExists($contentXml, '15.000000cm');
    }
}
