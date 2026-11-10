<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\Mapping\DocumentMapper;

use BelKoD\OdtGenerator\Rendering\OdtRenderer;

/**
 * Фасад двухфазного конвейера: HTML -> DOM -> IR -> ODT body XML.
 *
 * Ключевое отличие от старой схемы: нигде нет передачи массива по ссылке
 * (`array &$paragraphs`). Каждый этап возвращает результат как значение:
 *
 *   mapDocument()  : \DOMNode  -> Ir\DocumentNode   (чистая функция)
 *   render()       : DocumentNode -> string          (чистая функция + StyleRegistry)
 *
 * Вложенные таблицы/списки поддерживаются рекурсией на обеих фазах:
 * маппер строит CellNode->BlockNode[]->TableNode->..., рендерер обходит
 * то же дерево без знания о глубине вложенности.
 */
final class PipelineGenerator
{
    /** @var DocumentMapper */
    private $mapper;

    public function __construct(?DocumentMapper $mapper = null)
    {
        $this->mapper = $mapper ?? new DocumentMapper();
    }

    /**
     * Фаза 1: HTML-строка -> дерево IR.
     */
    public function map(string $html): Ir\DocumentNode
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // Обёртка UTF-8 через <meta charset> вместо XML-инструкции (совместимо с PHP 8.2+).
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
            . str_replace(["\r\n", "\r", "\n"], ' ', $html),
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        return $this->mapper->mapDocument($dom);
    }

    /**
     * Полный конвейер: HTML -> тело content.xml + XML автоматических стилей.
     *
     * @return array{body: string, styles: string}
     */
    public function generate(string $html): array
    {
        $ir = $this->map($html);
        $renderer = new OdtRenderer();
        $body = $renderer->render($ir);

        return [
            'body' => $body,
            'styles' => $renderer->getStyleRegistry()->toXml(),
        ];
    }
}
