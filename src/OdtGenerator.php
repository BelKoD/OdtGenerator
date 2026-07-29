<?php

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\HtmlTags\TagHandler;

class OdtGenerator
{
    private $html;
    private $outputPath;
    private $tempDir; // Сохраняем ссылку на временную директорию, если понадобится очистка
    private $factory;
    private $automaticStyles = [];
    private $defaultBorder = null;
    private $defaultCellPadding = null;
    /* @var StyleGenerator */
    private $styleGenerator;
    private $masterStyles = [];
    private $added_dir = ['Pictures'];
    /**
     * Конструктор
     *
     * @param string $html HTML-содержимое для обработки
     * @param string $outputFile Имя выходного файла, например "document.odt"
     * @throws \Exception
     */
    public function __construct(string $html, string $outputFile)
    {
        $this->html = $html;

        // Создаём временную директорию
        $tempBase = tempnam(sys_get_temp_dir(), 'pk_' . time() . '_');
        if (!unlink($tempBase)) {
            throw new \Exception("Не удалось удалить временный файл: {$tempBase}");
        }
        if (!mkdir($tempBase)) {
            throw new \Exception("Не удалось создать временную директорию: {$tempBase}");
        }

        $this->tempDir = $tempBase;
        $this->outputPath = $this->tempDir . '/' . $outputFile;

        $this->styleGenerator = new StyleGenerator($this);
        $this->factory = new TagHandlerFactory($this, $this->styleGenerator);
    }

    /**
     * Обработка HTML данных, генерация XML представления и создание файла ODT.
     */
    public function generate(): self
    {
        $this->automaticStyles = []; // сброс при генерации
        $html = str_replace(["\n", "\r"],'', $this->html);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        
        // Обрабатываем ошибки загрузки HTML явно
        libxml_use_internal_errors(true);
        $result = $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NODEFDTD);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        if (!$result && !empty($errors)) {
            $errorMessages = array_map(function($error) {
                return "Line {$error->line}: {$error->message}";
            }, $errors);
            error_log("HTML parsing errors: " . implode("; ", $errorMessages));
        }

        $xml = $dom->saveXML();
        file_put_contents($this->tempDir.'/source.xml', $xml);

        $paragraphs = [];
        $this->processChildren($dom->documentElement, $paragraphs);

        $contentXml = $this->buildContentXml($paragraphs);
        $this->createODTFile($contentXml);
        return $this;
    }

    private function processChildren(\DOMNode $parentNode, array &$paragraphs)
    {
        foreach ($parentNode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $this->processNode($child, $paragraphs);
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                // Обработка текста вне тегов (если нужно)
                $text = trim($child->nodeValue);
                if ($text !== '') {
                    $paragraphs[] = '<text:p>' . \htmlspecialchars($text, ENT_NOQUOTES | ENT_XML1, 'UTF-8') . '</text:p>';
                }
            }
            // Рекурсия не нужна — processNode уже вызывает обработчики, которые сами обходят детей (например, TableHandler)
        }
    }

    public function setGlobalSettings(array $settings = []): self
    {
        $this->styleGenerator->setGlobalSettings($settings);
        return $this;
    }

    public function getGlobalSettings(): array
    {
        return $this->styleGenerator->getGlobalSettings();
    }

    public function setDefaultBorder($border)
    {
        $this->defaultBorder = $border;
    }

    public function getDefaultBorder()
    {
        return $this->defaultBorder;
    }

    public function addAutomaticStyle($styleXml)
    {
        $this->automaticStyles[] = $styleXml;
    }

    public function setDefaultCellPadding($padding)
    {
        $this->defaultCellPadding = $padding;
    }

    public function getDefaultCellPadding()
    {
        return $this->defaultCellPadding;
    }

    /**
     * @return array
     */
    public function getMasterStyles(): array
    {
        return $this->masterStyles;
    }

    /**
     * @param string $masterStyles
     */
    public function setMasterStyles(string $masterStyles, $type = 'header')
    {
        $this->masterStyles[$type][] = $masterStyles;
    }

    /**
     * Создает XML документа ODT в виде текстовой строки.
     *
     * @param array $paragraphs Массив "абзацев", построенных на основе HTML.
     * @return string Возвращает XML в виде текстовой строки.
     */
    private function buildContentXml(array $paragraphs): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<office:document-content ' .
            'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" ' .
            'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" ' .
            'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" ' .
            'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" ' .
            'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" ' .
            'xmlns:loext="urn:org:documentfoundation:names:experimental:office:xmlns:loext:1.0" ' .
            'xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" ' .
            'xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" ' .
            'xmlns:xlink="http://www.w3.org/1999/xlink">' .
            "\n";

        $xml .= $this->styleGenerator->buildDocumentStyles($this->automaticStyles);
        $xml .= '<office:body><office:text text:style-name="Standard">' . "\n";

        foreach ($paragraphs as $p) {
            if ($p === '') continue;
            $xml .= $p . "\n";
        }

        $xml .= '</office:text></office:body>' . "\n";
        $xml .= '</office:document-content>';

        return $xml;
    }

    private function buildStylesXml(): string
    {
        // Генерация стилей страницы, таблиц, колонтитулов
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<office:document-styles
        xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
        xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
        xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
        xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
        xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"
        office:version="1.2">';

        $xml .= $this->styleGenerator->buildDocumentStyles($this->automaticStyles);


        $xml .= '</office:document-styles>';
        return $xml;
    }

    /**
     * @return string
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * Возвращает полный путь к созданному ODT-файлу
     *
     * @return string
     */
    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * Возвращает содержимое созданного ODT документа
     *
     * @return string|null
     * @throws \Exception
     */
    public function getOutputFile(): ?string
    {
        if (!file_exists($this->outputPath)) {
            error_log("ODT file not found: {$this->outputPath}");
            return null;
        }
        
        $content = file_get_contents($this->outputPath);
        if ($content === false) {
            error_log("Failed to read ODT file: {$this->outputPath}");
            throw new \Exception("Не удалось прочитать файл: {$this->outputPath}");
        }
        
        return $content;
    }

    /**
     * Обрабатывает тег HTML как объект.
     *
     * @param \DOMNode $node Нода тега HTML/
     * @param array $paragraphs Массив "абзацев", построенных на основе HTML.
     * @return void
     * @throws \Exception
     */
    public function processNode(\DOMNode $node, array &$paragraphs)
    {
        if ($node->nodeType !== \XML_ELEMENT_NODE) {
            return;
        }

        $tagName = strtolower($node->tagName);

        /* Тег <br>, если он не находится внутри текстовых тегов, заменяется на пустой <p> */
        if ($tagName === 'br') {
            $brContent = '';
            $paragraphs[] = '<text:p>' . $brContent . '</text:p>';
            return;
        }

        /** @var TagHandler $handler */
        $handler = $this->factory->getHandler($node);
        $handler->handle($node, $paragraphs);
    }

    /**
     * Создает файл документа ODT.
     *
     * @param string $contentXml XML документа ODT в виде текстовой строки.
     * @return void
     * @throws \Exception
     */
    private function createODTFile(string $contentXml)
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Не удалось создать файл: " . $this->outputPath);
        }

        // mimetype (должен быть первым и не сжатым!)
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->setCompressionIndex(0, \ZipArchive::CM_STORE);

        $sub_manifest = '';
        // добавляем дополнительные каталоги
        foreach ($this->added_dir as $target) {
            $add_dir = $this->tempDir . '/' .$target;
            if (is_dir($add_dir)) {
                //$this->addDirectoryToZip($zip, $add_dir, $dir);
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($add_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $file) {
                    $filePath = $file->getPathname();
                    $relativePath = substr($filePath, strlen($add_dir) + 1);
                    $archivePath = $target . '/' . $relativePath;

                    if ($file->isDir()) {
                        // Папки добавлять не нужно — ZipArchive добавит автоматически при добавлении файлов
                        continue;
                    }

                    $mimeType = $this->getImageMimeType($filePath);
                    $sub_manifest .= '<manifest:file-entry manifest:full-path="' . $archivePath . '" manifest:media-type="' . $mimeType . '"/>' . "\n";

                    $zip->addFile($filePath, $archivePath);
                }
            }
        }

        // META-INF/manifest.xml
        $manifest = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $manifest .= '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">' . "\n";
        $manifest .= '<manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>' . "\n";
        $manifest .= '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>' . "\n";
        $manifest .= $sub_manifest;
        $manifest .= '</manifest:manifest>';
        $zip->addFromString('META-INF/manifest.xml', $manifest);

        // content.xml
        $zip->addFromString('content.xml', $contentXml);

        // source.xml исходный xml на основе обрабатываемого html, структуру документа не нарушает, только для диагностики
        $zip->addFromString('source.xml', file_get_contents($this->tempDir.'/source.xml'));


        // styles.xml
        //$zip->addFromString('styles.xml', $this->buildStylesXml());

        $zip->close();
    }

    /**
     * Возвращает тип файла.
     *
     * @param string $filePath Путь к файлу
     * @return string
     */
    private function getImageMimeType(string $filePath): string
    {
        $mime = mime_content_type($filePath);
        if ($mime === 'image/svg') {
            return 'image/svg+xml';
        }
        return $mime ?: 'application/octet-stream';
    }
}