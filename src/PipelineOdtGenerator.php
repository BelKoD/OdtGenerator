<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\Exception\IOException;
use BelKoD\OdtGenerator\Exception\ValidationException;
use BelKoD\OdtGenerator\Interfaces\OdtArchiverInterface;
use BelKoD\OdtGenerator\Interfaces\OdtGeneratorInterface;
use BelKoD\OdtGenerator\Mapping\DocumentMapper;
use BelKoD\OdtGenerator\Rendering\OdtRenderer;

/**
 * Мост между двухфазным конвейером (DOM -> IR -> Render) и старым API.
 *
 * Реализует OdtGeneratorInterface, поэтому вызывающий код меняется минимально:
 *
 *   // было:
 *   $gen = new OdtGenerator($html, 'doc.odt');
 *   // стало:
 *   $gen = new PipelineOdtGenerator($html, 'doc.odt');
 *   $gen->generate();
 *   file_put_contents('doc.odt', $gen->getOutputFile());
 *
 * Внутри generate() вместо processNode(\DOMNode, array &$paragraphs) работает
 * PipelineGenerator: маппинг в IR и рендеринг возвращают значения, ссылок нет.
 *
 * Методы старого API, относящиеся к системе стилей TagHandler
 * (addAutomaticStyle / hasAutomaticStyle / setDefaultBorder / setDefaultCellPadding /
 *  getAutomaticStyles), приняты "впустую": стили генерирует Rendering\StyleRegistry.
 * setGlobalSettings() реально влияет на страницу (margins, size).
 */
final class PipelineOdtGenerator implements OdtGeneratorInterface
{
    /** @var string */
    private $html;

    /** @var string Полный путь к будущему .odt */
    private $outputPath;

    /** @var string Временная директория */
    private $tempDir;

    /** @var PipelineGenerator */
    private $pipeline;

    /** @var StyleGenerator Генератор обёртки content.xml (page layout, default styles) */
    private $styleGenerator;

    /** @var OdtArchiverInterface */
    private $archiver;

    /** @var array Глобальные настройки страницы (marL/marR/marT/marB/pageWidth/pageHeight) */
    private $globalSettings = [];

    /** @var array Мастер-стили (колонтитулы) в XML-виде, ключи: header|footer */
    private $masterStyles = [];

    /** @var array Директории, добавляемые в архив (Pictures для изображений) */
    private $addedDirs = ['Pictures'];

    /**
     * @param string $html         HTML-содержимое
     * @param string $outputFile   Имя выходного файла ("document.odt")
     * @param OdtArchiverInterface|null $archiver
     * @param DocumentMapper|null  $mapper Кастомный маппер IR (для расширения тегов)
     * @throws ValidationException
     * @throws IOException
     */
    public function __construct(
        string $html,
        string $outputFile,
        ?OdtArchiverInterface $archiver = null,
        ?DocumentMapper $mapper = null
    ) {
        if ($html === '') {
            throw new ValidationException('HTML содержимое не может быть пустым');
        }
        if ($outputFile === '') {
            throw new ValidationException('Имя выходного файла не может быть пустым');
        }
        if (!preg_match('/\.odt$/i', $outputFile)) {
            throw new ValidationException('Выходной файл должен иметь расширение .odt');
        }

        $tempBase = tempnam(sys_get_temp_dir(), 'pk_pipeline_');
        if ($tempBase === false) {
            throw new IOException('Не удалось создать временный файл');
        }
        if (!unlink($tempBase) || !mkdir($tempBase, 0755, true)) {
            throw new IOException("Не удалось подготовить временную директорию: {$tempBase}");
        }

        $this->html = $html;
        $this->tempDir = $tempBase;
        $this->outputPath = $tempBase . '/' . $outputFile;
        $this->pipeline = new PipelineGenerator($mapper);
        $this->styleGenerator = new StyleGenerator(new LegacyStyleBridge($this));
        $this->archiver = $archiver ?? new OdtArchiver();
    }

    /**
     * Полный цикл: HTML -> IR -> XML -> .odt файл во временной директории.
     */
    public function generate(): OdtGeneratorInterface
    {
        $ir = $this->pipeline->map($this->html);

        $renderer = new OdtRenderer();
        $bodyXml = $renderer->render($ir);
        $autoStylesXml = $renderer->getStyleRegistry()->toXml();

        $contentXml = $this->buildContentXml($bodyXml, $autoStylesXml);

        $this->writeSourceXml($ir);
        $this->createOdtFile($contentXml);

        return $this;
    }

    /**
     * Собирает content.xml: обёртка от StyleGenerator + тело от OdtRenderer.
     * Автоматические стили реестра IR-рендерера вставляются внутрь
     * <office:automatic-styles> рядом со стилями страницы.
     */
    private function buildContentXml(string $bodyXml, string $autoStylesXml): string
    {
        $wrapper = $this->styleGenerator->buildDocumentStyles([]);

        // Вставляем стили реестра перед закрытием automatic-styles
        $wrapper = str_replace(
            '</office:automatic-styles>',
            $autoStylesXml . "\n" . '</office:automatic-styles>',
            $wrapper
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<office:document-content '
            . 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
            . 'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" '
            . 'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" '
            . 'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" '
            . 'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" '
            . 'xmlns:loext="urn:org:documentfoundation:names:experimental:office:xmlns:loext:1.0" '
            . 'xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" '
            . 'xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" '
            . 'xmlns:xlink="http://www.w3.org/1999/xlink">' . "\n";
        $xml .= $wrapper;
        $xml .= '<office:body><office:text text:style-name="Standard">' . "\n";
        $xml .= $bodyXml . "\n";
        $xml .= '</office:text></office:body>' . "\n";
        $xml .= '</office:document-content>';

        return $xml;
    }

    /**
     * Сохраняем IR-дерево как source.xml (аналог отладочного дампа DOM в старом коде).
     */
    private function writeSourceXml(Ir\DocumentNode $ir): void
    {
        $dump = $this->dumpIr($ir);
        if (file_put_contents($this->tempDir . '/source.xml', $dump) === false) {
            throw new IOException('Не удалось записать source.xml во временную директорию');
        }
    }

    private function dumpIr(Ir\Node $node, int $depth = 0): string
    {
        $pad = str_repeat('  ', $depth);
        $out = $pad . get_class($node) . "\n";
        foreach ($node->children() as $child) {
            $out .= $this->dumpIr($child, $depth + 1);
        }
        return $out;
    }

    private function createOdtFile(string $contentXml): void
    {
        $files = [
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'content.xml' => $contentXml,
            'source.xml' => (string) file_get_contents($this->tempDir . '/source.xml'),
        ];

        $directories = [];
        foreach ($this->addedDirs as $target) {
            $dir = $this->tempDir . '/' . $target;
            if (is_dir($dir)) {
                $directories[] = ['source' => $dir, 'target' => $target];
            }
        }

        $this->archiver->createArchive($this->outputPath, $files, $directories);
    }

    /* ------------------------------------------------------------------
     |  OdtGeneratorInterface: методы, сохранённые для совместимости
     '  ---------------------------------------------------------------- */

    public function setGlobalSettings(array $settings = []): OdtGeneratorInterface
    {
        $this->globalSettings = $settings;
        $this->styleGenerator->setGlobalSettings($settings);
        return $this;
    }

    public function getGlobalSettings(): array
    {
        return $this->globalSettings;
    }

    /**
     * @param string|string[] $masterStyles XML-фрагменты колонтитула (массив или строка)
     */
    public function setMasterStyles($masterStyles, string $type = 'header'): OdtGeneratorInterface
    {
        $this->masterStyles[$type] = (array) $masterStyles;
        return $this;
    }

    public function setArchiver(OdtArchiverInterface $archiver): OdtGeneratorInterface
    {
        $this->archiver = $archiver;
        return $this;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * @return string|null Бинарное содержимое созданного .odt
     * @throws IOException
     */
    public function getOutputFile()
    {
        if (!file_exists($this->outputPath)) {
            error_log("ODT file not found: {$this->outputPath}");
            return null;
        }
        $content = file_get_contents($this->outputPath);
        if ($content === false) {
            throw new IOException("Не удалось прочитать файл: {$this->outputPath}");
        }
        return $content;
    }

    /**
     * Удаляет временную директорию и (опционально) готовый файл.
     * Вызовите после того, как забрали getOutputFile()/getOutputPath().
     */
    public function cleanup(bool $removeOutput = true): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        if ($removeOutput) {
            @rmdir($this->tempDir);
        }
    }

    /* ------------------------------------------------------------------
     |  Устаревшие методы системы стилей TagHandler — no-op мосты.
     |  В новой схеме стили создаёт Rendering\StyleRegistry.
     '  ---------------------------------------------------------------- */

    /** @deprecated Не используется в pipeline-режиме; стили формирует StyleRegistry. */
    public function addAutomaticStyle(string $styleXml, $styleName = null): OdtGeneratorInterface
    {
        return $this;
    }

    /** @deprecated Не используется в pipeline-режиме. */
    public function hasAutomaticStyle(string $styleName): bool
    {
        return false;
    }

    /** @deprecated Границы по умолчанию задаются через CSS/атрибуты таблицы в pipeline. */
    public function setDefaultBorder($border): OdtGeneratorInterface
    {
        return $this;
    }

    /** @deprecated */
    public function getDefaultBorder()
    {
        return null;
    }

    /** @deprecated Отступы ячеек вычисляются TableGridCalculator/StyleRegistry. */
    public function setDefaultCellPadding($padding): OdtGeneratorInterface
    {
        return $this;
    }

    /** @deprecated */
    public function getDefaultCellPadding()
    {
        return null;
    }

    /** @deprecated */
    public function clearAutomaticStyles()
    {
        return $this;
    }

    /** @deprecated Всегда пусто: стили живут в StyleRegistry рендерера. */
    public function getAutomaticStyles(): array
    {
        return [];
    }

    public function getMasterStyles(): array
    {
        return $this->masterStyles;
    }

    /** @deprecated Совместимость: возвращает пустой массив, IR-рендерер не использует processNode. */
    public function processNode(\DOMNode $node, array &$paragraphs)
    {
        trigger_error(
            __METHOD__ . ' устарел: используйте PipelineGenerator::map()/generate() вместо обхода по ссылкам.',
            E_USER_DEPRECATED
        );
    }
}
