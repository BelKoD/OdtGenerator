<?php

namespace BelKoD\OdtGenerator\Tests;

use BelKoD\OdtGenerator\OdtArchiver;
use BelKoD\OdtGenerator\Exception\IOException;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для OdtArchiver
 */
class OdtArchiverTest extends TestCase
{
    private $tempDir;
    private $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/odt_archiver_tests_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        
        // Создаем тестовый файл
        $this->testFilePath = $this->tempDir . '/test.txt';
        file_put_contents($this->testFilePath, 'Test content');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
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
     * Тест создания архива с файлами
     */
    public function testCreateArchiveWithFiles()
    {
        $outputPath = $this->tempDir . '/test.odt';
        $files = [
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'content.xml' => '<?xml version="1.0"?><test>Content</test>',
            'META-INF/manifest.xml' => '<?xml version="1.0"?><manifest></manifest>'
        ];

        $archiver = new OdtArchiver();
        $archiver->createArchive($outputPath, $files);

        $this->assertFileExists($outputPath, 'Архив должен быть создан');

        // Проверяем содержимое архива
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($outputPath) === true, 'Файл должен быть корректным ZIP');

        $mimetype = $zip->getFromName('mimetype');
        $this->assertEquals('application/vnd.oasis.opendocument.text', $mimetype);

        $contentXml = $zip->getFromName('content.xml');
        $this->assertEquals('<?xml version="1.0"?><test>Content</test>', $contentXml);

        $zip->close();
    }

    /**
     * Тест добавления директории в архив
     */
    public function testAddDirectory()
    {
        $outputPath = $this->tempDir . '/test_with_dir.odt';
        $files = [
            'mimetype' => 'application/vnd.oasis.opendocument.text'
        ];
        
        $directories = [
            [
                'source' => $this->tempDir,
                'target' => 'Pictures'
            ]
        ];

        $archiver = new OdtArchiver();
        $archiver->createArchive($outputPath, $files, $directories);

        $this->assertFileExists($outputPath);

        $zip = new \ZipArchive();
        $zip->open($outputPath);

        // Проверяем, что файл из директории добавлен
        $testFileInArchive = $zip->getFromName('Pictures/test.txt');
        $this->assertEquals('Test content', $testFileInArchive, 'Файл из директории должен быть в архиве');

        $zip->close();
    }

    /**
     * Тест определения MIME-типа
     */
    public function testGetMimeType()
    {
        $archiver = new OdtArchiver();

        // Создаем разные типы файлов для теста
        $pngPath = $this->tempDir . '/test.png';
        imagepng(imagecreatetruecolor(10, 10), $pngPath);

        $txtPath = $this->tempDir . '/test2.txt';
        file_put_contents($txtPath, 'text');

        $mimeTypePng = $archiver->getMimeType($pngPath);
        $this->assertEquals('image/png', $mimeTypePng, 'MIME тип PNG должен быть определен правильно');

        $mimeTypeTxt = $archiver->getMimeType($txtPath);
        $this->assertEquals('text/plain', $mimeTypeTxt, 'MIME тип TXT должен быть определен правильно');
    }

    /**
     * Тест обработки несуществующей директории
     */
    public function testAddNonExistentDirectory()
    {
        $outputPath = $this->tempDir . '/test_nonexistent.odt';
        $files = [
            'mimetype' => 'application/vnd.oasis.opendocument.text'
        ];
        
        $directories = [
            [
                'source' => '/non/existent/path',
                'target' => 'NonExistent'
            ]
        ];

        $archiver = new OdtArchiver();
        // Не должно выбрасывать исключение, просто игнорирует несуществующую директорию
        $archiver->createArchive($outputPath, $files, $directories);

        $this->assertFileExists($outputPath, 'Архив должен быть создан даже с несуществующей директорией');
    }

    /**
     * Тест создания архива без mimetype
     */
    public function testCreateArchiveWithoutMimetype()
    {
        $outputPath = $this->tempDir . '/test_no_mime.odt';
        $files = [
            'content.xml' => '<?xml version="1.0"?><test>Content</test>'
        ];

        $archiver = new OdtArchiver();
        $archiver->createArchive($outputPath, $files);

        $this->assertFileExists($outputPath);

        $zip = new \ZipArchive();
        $zip->open($outputPath);

        $mimetype = $zip->getFromName('mimetype');
        $this->assertEquals('application/vnd.oasis.opendocument.text', $mimetype, 'Mimetype должен быть добавлен по умолчанию');

        $zip->close();
    }

    /**
     * Тест перезаписи существующего архива
     */
    public function testOverwriteExistingArchive()
    {
        $outputPath = $this->tempDir . '/test_overwrite.odt';
        
        // Создаем первый архив
        $files1 = [
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'content.xml' => 'First content'
        ];

        $archiver = new OdtArchiver();
        $archiver->createArchive($outputPath, $files1);

        // Перезаписываем вторым архивом
        $files2 = [
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'content.xml' => 'Second content'
        ];

        $archiver->createArchive($outputPath, $files2);

        $zip = new \ZipArchive();
        $zip->open($outputPath);

        $content = $zip->getFromName('content.xml');
        $this->assertEquals('Second content', $content, 'Содержимое должно быть перезаписано');

        $zip->close();
    }

    /**
     * Тест добавления файла с кириллическим путем
     */
    public function testAddFileWithCyrillicName()
    {
        $cyrillicFilePath = $this->tempDir . '/тест_файл.txt';
        file_put_contents($cyrillicFilePath, 'Кириллическое содержимое');

        $outputPath = $this->tempDir . '/test_cyrillic.odt';
        $files = [
            'mimetype' => 'application/vnd.oasis.opendocument.text'
        ];
        
        $directories = [
            [
                'source' => $this->tempDir,
                'target' => 'Files'
            ]
        ];

        $archiver = new OdtArchiver();
        $archiver->createArchive($outputPath, $files, $directories);

        $zip = new \ZipArchive();
        $zip->open($outputPath);

        // Ищем файл с кириллическим именем
        $found = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (strpos($stat['name'], 'тест_файл') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Файл с кириллическим именем должен быть в архиве');
        $zip->close();
    }
}
