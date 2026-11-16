<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\Exception\IOException;
use BelKoD\OdtGenerator\Exception\ValidationException;
use BelKoD\OdtGenerator\Interfaces\OdtArchiverInterface;
use BelKoD\OdtGenerator\Interfaces\OdtGeneratorInterface;
use BelKoD\OdtGenerator\Ir\DocumentNode;
use BelKoD\OdtGenerator\Ir\Node;
use BelKoD\OdtGenerator\Mapping\DocumentMapper;
use BelKoD\OdtGenerator\Rendering\OdtRenderer;

/**
 * Генератор ODT документов из HTML.
 *
 * Двухфазный конвейер: HTML -> DOM -> IR (Mapping\DocumentMapper)
 * -> content.xml (Rendering\OdtRenderer + Rendering\StyleRegistry).
 *
 * Обработчики тегов, передающие массивы по ссылке (&$paragraphs), удалены:
 * маппинг возвращает типизированное дерево IR, рендеринг — готовый XML.
 * Состояние (стили страниц/таблиц) изолировано в ParseContext и StyleRegistry,
 * глобальное мутабельное состояние генератора отсутствует.
 *
 * Использование не изменилось:
 *
 *   $gen = new OdtGenerator($html, 'document.odt');
 *   $gen->generate();
 *   file_put_contents('document.odt', $gen->getOutputFile());
 *   $gen->cleanup(); // опционально: удалить временную директорию
 */
class OdtGenerator implements OdtGeneratorInterface
{
    /** @var string HTML-содержимое для обработки */
    private $html;

    /** @var string Полный путь к будущему .odt */
    private $outputPath;

    /** @var string Временная директория */
    private $tempDir;

    /** @var DocumentMapper Маппер HTML DOM -> IR */
    private $mapper;

    /** @var StyleGenerator Обёртка content.xml (page layout, дефолтные стили) */
    private $styleGenerator;

    /** @var OdtArchiverInterface Архиватор ODT файлов */
    private $archiver;

    /** @var array Глобальные настройки страницы (margins, size, font) */
    private $globalSettings = [];

    /** @var array Мастер-стили (колонтитулы) в XML-виде, ключи: header|footer */
    private $masterStyles = [];

    /** @var string[] Директории, добавляемые в архив (Pictures для изображений) */
    private $addedDirs = ['Pictures'];

    /**
     * @param string $html HTML-содержимое для обработки
     * @param string $outputFile Имя выходного файла, например "document.odt"
     * @param OdtArchiverInterface|null $archiver Архиватор (по умолчанию создается новый)
     * @param DocumentMapper|null $mapper Кастомный маппер IR (для расширения набора тегов)
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

        $tempBase = tempnam(sys_get_temp_dir(), 'pk_' . getmypid() . '_');
        if ($tempBase === false) {
            throw new IOException('Не удалось создать временный файл');
        }

        if (!unlink($tempBase) || !mkdir($tempBase, 0755, true)) {
            throw new IOException("Не удалось подготовить временную директорию: {$tempBase}");
        }

        $this->html = $html;
        $this->tempDir = $tempBase;
        $this->outputPath = $tempBase . '/' . $outputFile;
        $this->mapper = $mapper ?? new DocumentMapper();
        $this->styleGenerator = new StyleGenerator($this);
        $this->archiver = $archiver ?? new OdtArchiver();
    }

    /**
     * Полный цикл: HTML -> IR -> content.xml -> .odt файл во временной директории.
     *
     * Каждый вызов полностью пересобирает документ: реестр стилей создаётся
     * заново внутри OdtRenderer, поэтому дублирования стилей нет.
     *
     * @return self
     * @throws IOException
     */
    public function generate(): OdtGeneratorInterface
    {
        $ir = $this->pipeline()->map($this->html);

        $renderer = new OdtRenderer();
        $bodyXml = $renderer->render($ir);
        $autoStylesXml = $renderer->getStyleRegistry()->toXml();

        $contentXml = $this->buildContentXml($bodyXml, $autoStylesXml);

        $this->writeSourceXml($ir);
        $this->createOdtFile($contentXml);

        return $this;
    }

    /**
     * Возвращает конвейер DOM -> IR (доступен для прототипирования/тестов:
     * можно получить IR-дерево без сборки файла).
     */
    public function pipeline(): PipelineGenerator
    {
        return new PipelineGenerator($this->mapper);
    }

    /* ------------------------------------------------------------------
     |  Сборка XML и архива
     '  ---------------------------------------------------------------- */

    /**
     * Собирает content.xml: обёртка от StyleGenerator + тело от OdtRenderer.
     * Автоматические стили реестра вставляются внутрь <office:automatic-styles>.
     */
    private function buildContentXml(string $bodyXml, string $autoStylesXml): string
    {
        $wrapper = $this->styleGenerator->buildDocumentStyles([]);

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
     * Сохраняет дамп IR-дерева как source.xml (отладочный артефакт в архиве).
     */
    private function writeSourceXml(DocumentNode $ir): void
    {
        if (file_put_contents($this->tempDir . '/source.xml', $this->dumpIr($ir)) === false) {
            throw new IOException('Не удалось записать source.xml во временную директорию');
        }
    }

    /**
     * Рекурсивный обход IR-дерева: дочерние узлы извлекаются по известным
     * геттерам каждого типа (у базового Node нет универсального children()).
     */
    private function dumpIr(Node $node, int $depth = 0): string
    {
        $out = str_repeat('  ', $depth) . substr(strrchr('\\' . get_class($node), '\\'), 1) . "\n";

        if ($node instanceof DocumentNode) {
            $children = $node->getChildren();
        } elseif ($node instanceof Ir\SpanNode) {
            $children = $node->getChildren();
        } elseif ($node instanceof Ir\ParagraphNode || $node instanceof Ir\HeadingNode) {
            $children = $node->getInlines();
        } elseif ($node instanceof Ir\ListNode) {
            $children = $node->getItems();
        } elseif ($node instanceof Ir\ListItemNode || $node instanceof Ir\CellNode) {
            $children = $node->getBlocks();
        } elseif ($node instanceof Ir\TableNode) {
            $children = $node->getRows();
        } elseif ($node instanceof Ir\RowNode) {
            $children = $node->getCells();
        } else {
            $children = []; // листовые узлы: TextNode, LineBreakNode
        }

        foreach ($children as $child) {
            if ($child instanceof Node) {
                $out .= $this->dumpIr($child, $depth + 1);
            }
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
     |  OdtGeneratorInterface
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

    public function getMasterStyles(): array
    {
        return $this->masterStyles;
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

    /* ------------------------------------------------------------------
     |  Методы интерфейса, ставшие производными от StyleRegistry.
     |  Реальный накопитель автоматических стилей — Rendering\StyleRegistry,
     |  он живёт внутри OdtRenderer на время одного generate().
     '  ---------------------------------------------------------------- */

    /**
     * @deprecated Автоматические стили создаёт StyleRegistry в фазе рендеринга.
     */
    public function addAutomaticStyle(string $styleXml, $styleName = null): OdtGeneratorInterface
    {
        return $this;
    }

    /** @deprecated */
    public function hasAutomaticStyle(string $styleName): bool
    {
        return false;
    }

    /** @deprecated Границы таблиц задаются CSS/атрибутами, обрабатываемыми маппером. */
    public function setDefaultBorder($border): OdtGeneratorInterface
    {
        return $this;
    }

    /** @deprecated */
    public function getDefaultBorder()
    {
        return null;
    }

    /** @deprecated Отступы ячеек вычисляются на этапе маппинга сетки таблицы. */
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
}
