<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Rendering;

/**
 * Реестр автоматических стилей (фаза 2).
 *
 * Ключ — канонический CSS-набор, значение — XML стиля. Повторная регистрация
 * того же набора игнорируется, что решает проблему дедупликации
 * (hasAutomaticStyle) детерминированно и без md5(rand()).
 */
final class StyleRegistry
{
    /** @var array<string, string> styleName => styleXml */
    private $paragraphStyles = [];

    /** @var array<string, string> */
    private $textStyles = [];

    /** @var array<string, string> */
    private $tableStyles = [];

    /** @var array<string, string> */
    private $tableCellStyles = [];

    /**
     * Регистрирует стиль абзаца. Возвращает имя стиля.
     */
    public function registerParagraph(string $name, string $propertiesXml): string
    {
        $this->paragraphStyles[$name] ??= $propertiesXml;
        return $name;
    }

    public function registerText(string $name, string $propertiesXml): string
    {
        $this->textStyles[$name] ??= $propertiesXml;
        return $name;
    }

    public function registerTable(string $name, string $propertiesXml): string
    {
        $this->tableStyles[$name] ??= $propertiesXml;
        return $name;
    }

    public function registerTableCell(string $name, string $propertiesXml): string
    {
        $this->tableCellStyles[$name] ??= $propertiesXml;
        return $name;
    }

    public function has(string $name): bool
    {
        return isset($this->paragraphStyles[$name])
            || isset($this->textStyles[$name])
            || isset($this->tableStyles[$name])
            || isset($this->tableCellStyles[$name]);
    }

    /**
     * Финальный XML блока <office:automatic-styles>.
     */
    public function toXml(): string
    {
        $xml = '';
        foreach ($this->paragraphStyles as $name => $props) {
            $xml .= '<style:style style:name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '" style:family="paragraph"><style:paragraph-properties fo:margin-top="0cm" '
                . 'fo:margin-bottom="0.15cm"/>' . ($props !== '' ? $props : '<style:text-properties/>')
                . '</style:style>';
        }
        foreach ($this->textStyles as $name => $props) {
            $xml .= '<style:style style:name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '" style:family="text">' . $props . '</style:style>';
        }
        foreach ($this->tableStyles as $name => $props) {
            $xml .= '<style:style style:name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '" style:family="table">' . $props . '</style:style>';
        }
        foreach ($this->tableCellStyles as $name => $props) {
            $xml .= '<style:style style:name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                . '" style:family="table-cell">' . $props . '</style:style>';
        }
        return $xml;
    }
}
