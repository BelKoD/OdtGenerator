<?php

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\HtmlTags\TagHandler;
use BelKoD\OdtGenerator\Exception\ValidationException;
use BelKoD\OdtGenerator\Exception\IOException;

class OdtGenerator implements OdtGeneratorInterface
{
    /**
     * @var string HTML-содержимое для обработки
     */
    private $html;
    
    /**
     * @var string Имя выходного файла
     */
    private $outputPath;
    
    /**
     * @var string Путь к временной директории
     */
    private $tempDir;
    
    /**
     * @var TagHandlerFactory Фабрика обработчиков тегов
     */
    private $factory;
    
    /**
     * @var array Массив автоматических стилей
     */
    private $automaticStyles = [];
    
    /**
     * @var array Индексы существующих автоматических стилей для быстрой проверки
     */
    private $automaticStyleIndex = [];
    
    /**
     * @var string|null Граница по умолчанию
     */
    private $defaultBorder = null;
    
    /**
     * @var string|null Отступ ячеек по умолчанию
     */
    private $defaultCellPadding = null;
    
    /**
     * @var StyleGeneratorInterface Генератор стилей
     */
    private $styleGenerator;
    
    /**
     * @var OdtArchiverInterface Архиватор ODT файлов
     */
    private $archiver;
    
    /**
     * @var array Массив мастер-стилей
     */
    private $masterStyles = [];
    
    /**
     * @var array Массив добавленных директорий
     */
    private $added_dir = ['Pictures'];
    
    /**
     * Конструктор
     *
     * @param string $html HTML-содержимое для обработки
     * @param string $outputFile Имя выходного файла, например "document.odt"
     * @param OdtArchiverInterface|null $archiver Архиватор (по умолчанию создается новый)
     * @throws IOException
     * @throws ValidationException
     */
    public function __construct(string $html, string $outputFile, ?OdtArchiverInterface $archiver = null)
    {
        // Валидация входных данных
        if (empty($html)) {
            throw new ValidationException('HTML содержимое не может быть пустым');
        }
        
        if (empty($outputFile)) {
            throw new ValidationException('Имя выходного файла не может быть пустым');
        }
        
        if (!preg_match('/\.odt$/i', $outputFile)) {
            throw new ValidationException('Выходной файл должен иметь расширение .odt');
        }

        $this->html = $html;

        // Создаём временную директорию
        $tempBase = tempnam(sys_get_temp_dir(), 'pk_' . time() . '_');
        if ($tempBase === false) {
            throw new IOException('Не удалось создать временный файл');
        }
        
        if (!unlink($tempBase)) {
            throw new IOException("Не удалось удалить временный файл: {$tempBase}");
        }
        
        if (!mkdir($tempBase, 0755, true)) {
            throw new IOException("Не удалось создать временную директорию: {$tempBase}");
        }

        $this->tempDir = $tempBase;
        $this->outputPath = $this->tempDir . '/' . $outputFile;

        $this->styleGenerator = new StyleGenerator($this);
        $this->factory = new TagHandlerFactory($this, $this->styleGenerator);
        $this->archiver = $archiver ?? new OdtArchiver();
    }

    /**
     * Обработка HTML данных, генерация XML представления и создание файла ODT.
     *
     * @return self
     * @throws IOException
     */
    public function generate(): self
    {
        $this->automaticStyles = []; // сброс при генерации
        $this->automaticStyleIndex = []; // сброс индекса стилей
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
        if ($xml === false) {
            throw new IOException('Не удалось сохранить XML представление документа');
        }
        
        if (file_put_contents($this->tempDir.'/source.xml', $xml) === false) {
            throw new IOException('Не удалось записать source.xml во временную директорию');
        }

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

    /**
     * Устанавливает границу по умолчанию
     *
     * @param string|null $border Граница (например, "1pt solid #000")
     * @return self
     */
    public function setDefaultBorder($border): self
    {
        $this->defaultBorder = $border;
        return $this;
    }

    /**
     * Возвращает границу по умолчанию
     *
     * @return string|null
     */
    public function getDefaultBorder(): ?string
    {
        return $this->defaultBorder;
    }

    /**
     * Добавляет автоматический стиль
     *
     * @param string $styleXml XML-строка стиля
     * @param string|null $styleName Имя стиля для индексации (опционально)
     * @return self
     */
    public function addAutomaticStyle(string $styleXml, ?string $styleName = null): self
    {
        $this->automaticStyles[] = $styleXml;
        if ($styleName !== null) {
            $this->automaticStyleIndex[$styleName] = true;
        }
        return $this;
    }
    
    /**
     * Проверяет, существует ли автоматический стиль с данным именем
     *
     * @param string $styleName Имя стиля
     * @return bool
     */
    public function hasAutomaticStyle(string $styleName): bool
    {
        return isset($this->automaticStyleIndex[$styleName]);
    }

    /**
     * Устанавливает отступ ячеек по умолчанию
     *
     * @param string|null $padding Отступ (например, "0.1cm")
     * @return self
     */
    public function setDefaultCellPadding($padding): self
    {
        $this->defaultCellPadding = $padding;
        return $this;
    }

    /**
     * Возвращает отступ ячеек по умолчанию
     *
     * @return string|null
     */
    public function getDefaultCellPadding(): ?string
    {
        return $this->defaultCellPadding;
    }

    /**
     * Сбрасывает все автоматические стили и индекс
     */
    public function clearAutomaticStyles(): void
    {
        $this->automaticStyles = [];
        $this->automaticStyleIndex = [];
    }
    
    /**
     * Возвращает массив автоматических стилей
     *
     * @return array
     */
    public function getAutomaticStyles(): array
    {
        return $this->automaticStyles;
    }

    /**
     * @return array
     */
    public function getMasterStyles(): array
    {
        return $this->masterStyles;
    }

    /**
     * Устанавливает мастер-стили
     *
     * @param string $masterStyles XML-строка мастер-стилей
     * @param string $type Тип мастер-стиля (например, 'header', 'footer')
     * @return self
     */
    public function setMasterStyles(string $masterStyles, string $type = 'header'): self
    {
        $this->masterStyles[$type][] = $masterStyles;
        return $this;
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
     * @throws IOException
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
            throw new IOException("Не удалось прочитать файл: {$this->outputPath}");
        }
        
        return $content;
    }

    /**
     * Обрабатывает тег HTML как объект.
     *
     * @param \DOMNode $node Нода тега HTML/
     * @param array $paragraphs Массив "абзацев", построенных на основе HTML.
     * @return void
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
     * @throws IOException
     */
    private function createODTFile(string $contentXml)
    {
        // Подготавливаем файлы для архива
        $files = [
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'META-INF/manifest.xml' => $this->buildManifest(),
            'content.xml' => $contentXml,
            'source.xml' => file_get_contents($this->tempDir.'/source.xml')
        ];

        // Подготавливаем директории для архива
        $directories = [];
        foreach ($this->added_dir as $target) {
            $addDir = $this->tempDir . '/' . $target;
            if (is_dir($addDir)) {
                $directories[] = [
                    'source' => $addDir,
                    'target' => $target
                ];
            }
        }

        // Создаем архив с помощью делегированного класса
        $this->archiver->createArchive($this->outputPath, $files, $directories);
    }

    /**
     * Строит XML манифеста
     *
     * @return string XML манифеста
     */
    private function buildManifest(): string
    {
        $subManifest = '';
        
        // Добавляем записи манифеста для дополнительных директорий
        foreach ($this->added_dir as $target) {
            $addDir = $this->tempDir . '/' . $target;
            if (!is_dir($addDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($addDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $filePath = $file->getPathname();
                $relativePath = substr($filePath, strlen($addDir) + 1);
                $archivePath = $target . '/' . $relativePath;
                $mimeType = $this->archiver->getMimeType($filePath);
                $subManifest .= '<manifest:file-entry manifest:full-path="' . $archivePath . '" manifest:media-type="' . $mimeType . '"/>' . "\n";
            }
        }

        $manifest = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $manifest .= '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">' . "\n";
        $manifest .= '<manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>' . "\n";
        $manifest .= '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>' . "\n";
        $manifest .= $subManifest;
        $manifest .= '</manifest:manifest>';

        return $manifest;
    }
}