<?php

namespace BelKoD\OdtGenerator\Tests;

use BelKoD\OdtGenerator\OdtGenerator;
use BelKoD\OdtGenerator\OdtArchiver;
use BelKoD\OdtGenerator\Exception\ValidationException;
use BelKoD\OdtGenerator\Exception\IOException;
use PHPUnit\Framework\TestCase;

/**
 * Базовые тесты для OdtGenerator
 */
class OdtGeneratorTest extends TestCase
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
     * Тест создания простого документа
     */
    public function testCreateSimpleDocument()
    {
        $html = '<p>Простой тестовый документ</p>';
        $outputFile = 'test_simple.odt';
        
        $generator = new OdtGenerator($html, $outputFile);
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath, 'ODT файл должен быть создан');
        
        // Проверяем, что файл можно открыть как ZIP
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($odtPath) === true, 'Файл должен быть корректным ZIP архивом');
        
        // Проверяем наличие mimetype
        $mimetype = $zip->getFromName('mimetype');
        $this->assertEquals('application/vnd.oasis.opendocument.text', $mimetype, 'MIME тип должен совпадать');
        
        $zip->close();
    }

    /**
     * Тест валидации пустого HTML
     */
    public function testEmptyHtmlValidation()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('HTML содержимое не может быть пустым');
        
        new OdtGenerator('', 'test.odt');
    }

    /**
     * Тест валидации пустого имени файла
     */
    public function testEmptyFilenameValidation()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Имя выходного файла не может быть пустым');
        
        new OdtGenerator('<p>Test</p>', '');
    }

    /**
     * Тест валидации расширения файла
     */
    public function testInvalidExtensionValidation()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Выходной файл должен иметь расширение .odt');
        
        new OdtGenerator('<p>Test</p>', 'document.txt');
    }

    /**
     * Тест обработки текста с форматированием
     */
    public function testTextFormatting()
    {
        $html = '<p><strong>Жирный текст</strong> и <em>курсив</em></p>';
        
        $generator = new OdtGenerator($html, 'test_formatting.odt');
        $generator->generate();
        
        $contentXml = $this->extractContentXml($generator->getOutputPath());
        
        $this->assertStringContainsString('<text:span', $contentXml, 'Должны присутствовать элементы text:span');
        $this->assertStringContainsString('Жирный текст', $contentXml, 'Текст должен сохраниться');
    }

    /**
     * Тест обработки кириллического текста
     */
    public function testCyrillicText()
    {
        $html = '<p>Ло́рем ипсу́м — текст-«рыба»</p>';
        
        $generator = new OdtGenerator($html, 'test_cyrillic.odt');
        $generator->generate();
        
        $contentXml = $this->extractContentXml($generator->getOutputPath());
        
        $this->assertStringContainsString('Ло́рем ипсу́м', $contentXml, 'Кириллический текст должен сохраниться');
    }

    /**
     * Тест создания документа с таблицей
     */
    public function testTableCreation()
    {
        $html = '<table>
            <tr>
                <td>Ячейка 1</td>
                <td>Ячейка 2</td>
            </tr>
        </table>';
        
        $generator = new OdtGenerator($html, 'test_table.odt');
        $generator->generate();
        
        $contentXml = $this->extractContentXml($generator->getOutputPath());
        
        $this->assertStringContainsString('<table:table', $contentXml, 'Должна присутствовать таблица');
        $this->assertStringContainsString('<table:table-row', $contentXml, 'Должны присутствовать строки таблицы');
        $this->assertStringContainsString('<table:table-cell', $contentXml, 'Должны присутствовать ячейки таблицы');
    }

    /**
     * Тест использования кастомного архиватора
     */
    public function testCustomArchiver()
    {
        $html = '<p>Тест с кастомным архиватором</p>';
        $customArchiver = new OdtArchiver();
        
        $generator = new OdtGenerator($html, 'test_custom_archiver.odt', $customArchiver);
        $generator->generate();
        
        $this->assertFileExists($generator->getOutputPath(), 'ODT файл должен быть создан с кастомным архиватором');
    }

    /**
     * Тест получения содержимого файла
     */
    public function testGetOutputFile()
    {
        $html = '<p>Тест получения файла</p>';
        
        $generator = new OdtGenerator($html, 'test_get_file.odt');
        $generator->generate();
        
        $content = $generator->getOutputFile();
        
        $this->assertNotNull($content, 'Содержимое файла не должно быть null');
        $this->assertNotEmpty($content, 'Содержимое файла не должно быть пустым');
    }

    /**
     * Тест множественных абзацев
     */
    public function testMultipleParagraphs()
    {
        $html = '<p>Первый абзац</p><p>Второй абзац</p><p>Третий абзац</p>';
        
        $generator = new OdtGenerator($html, 'test_paragraphs.odt');
        $generator->generate();
        
        $contentXml = $this->extractContentXml($generator->getOutputPath());
        
        preg_match_all('/<text:p>/', $contentXml, $matches);
        $this->assertEquals(3, count($matches[0]), 'Должно быть создано 3 абзаца');
    }

    /**
     * Тест заголовков разных уровней
     */
    public function testHeadings()
    {
        $html = '<h1>Заголовок 1</h1><h2>Заголовок 2</h2><h3>Заголовок 3</h3>';
        
        $generator = new OdtGenerator($html, 'test_headings.odt');
        $generator->generate();
        
        $contentXml = $this->extractContentXml($generator->getOutputPath());
        
        $this->assertStringContainsString('Заголовок 1', $contentXml);
        $this->assertStringContainsString('Заголовок 2', $contentXml);
        $this->assertStringContainsString('Заголовок 3', $contentXml);
    }

    /**
     * Тест списков
     */
    public function testLists()
    {
        $html = '<ul><li>Элемент 1</li><li>Элемент 2</li></ul>';
        
        $generator = new OdtGenerator($html, 'test_lists.odt');
        $generator->generate();
        
        $contentXml = $this->extractContentXml($generator->getOutputPath());
        
        $this->assertStringContainsString('<text:list>', $contentXml, 'Должен присутствовать список');
        $this->assertStringContainsString('Элемент 1', $contentXml);
    }

    /**
     * Тест изображений (если есть в Pictures)
     */
    public function testImageHandling()
    {
        // Создаем тестовое изображение
        $imagePath = $this->outputDir . '/test.png';
        imagepng(imagecreatetruecolor(10, 10), $imagePath);
        
        $html = '<p>Текст с изображением</p>';
        
        $generator = new OdtGenerator($html, 'test_image.odt');
        
        // Копируем изображение во временную директорию генератора
        $picturesDir = $generator->getTempDir() . '/Pictures';
        if (!is_dir($picturesDir)) {
            mkdir($picturesDir, 0755, true);
        }
        copy($imagePath, $picturesDir . '/test.png');
        
        $generator->generate();
        
        $odtPath = $generator->getOutputPath();
        $this->assertFileExists($odtPath);
        
        // Проверяем наличие изображения в архиве
        $zip = new \ZipArchive();
        $zip->open($odtPath);
        $imageInArchive = $zip->getFromName('Pictures/test.png');
        $zip->close();
        
        $this->assertNotFalse($imageInArchive, 'Изображение должно быть в архиве');
    }
}
