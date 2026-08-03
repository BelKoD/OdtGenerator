<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\StyleHelper;
use BelKoD\OdtGenerator\Utils\Misc;

/**
 * Генератор TD.
 */
class TdHandler extends TagHandler
{
    /* @var int Доступное кол-во столбцов */
    private $availableCols;

    public function __construct($factory, int $availableCols)
    {
        $this->factory = $factory;
        $this->availableCols = $availableCols;
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        $tagName = strtolower($node->tagName);
        $isHeader = ($tagName === 'th');

        // Получаем стили из CSS (style="...")
        $cssCellProperties = [];
        $cssParaProperties = [];

        $css_inline = [];
        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));

            $display = Misc::arrayExtract($css, 'display');
            if ($display == 'none') {
                return;
            }

            // Обрабатываем CSS-свойства
            foreach ($css as $property => $value) {
                switch ($property) {
                    case 'background-color':
                        $cssCellProperties[] = 'fo:background-color="' . StyleHelper::normalizeColor($value) . '"';
                        break;
                    case 'width':
                        // Ширина теперь обрабатывается на уровне столбцов таблицы в TableHandler
                        // Это свойство игнорируется на уровне ячейки
                        break;
                    case 'vertical-align':
                        $cssCellProperties[] = 'style:vertical-align="' . StyleHelper::getVerticalAlignmentOdt($value) . '"';
                        break;
                    case 'padding':
                    case 'padding-top':
                    case 'padding-bottom':
                    case 'padding-left':
                    case 'padding-right':
                        $converted = StyleHelper::convertToCm($value);
                        $prop = str_replace('padding-', 'fo:padding-', $property);
                        $cssCellProperties[] = $prop . '="' . htmlspecialchars($converted, ENT_QUOTES, 'UTF-8') . '"';
                        break;
                    /*case 'color':
                        $cssParaProperties[] = 'fo:color="' . StyleHelper::normalizeColor($value) . '"';
                        break;*/
                    case 'font-family':
                    case 'font-size':
                    case 'font-weight':
                    case 'font-style':
                    case 'text-decoration':
                    case 'text-align':
                    case 'color':
                        // Эти свойства обрабатываются через ensureInlineStyle для <text:p>
                        $css_inline[$property] = $value;
                        break;
                }
            }
        }

        // Получаем базовые стили из атрибутов (bgcolor, align, cellpadding и т.д.)
        $baseStyles = $this->generateCellStyle($node);


        // Объединяем базовые и CSS-свойства
        $cellProperties = array_merge($baseStyles['cell'], $cssCellProperties);
        $paraProperties = array_merge($baseStyles['paragraph'], $cssParaProperties);

        $cellStyleName = null;
        $textStyleName = 'StandardParagraph';

        // Генерация стиля ячейки
        if (!empty($cellProperties)) {
            $cellStyleXml = '<style:table-cell-properties ' . implode(' ', $cellProperties) . '/>';
            $cellStyleName = 'CellStyle_' . substr(md5($cellStyleXml), 0, 8);
            $this->factory->getGenerator()->addAutomaticStyle(
                '<style:style style:name="' . $cellStyleName . '" style:family="table-cell">' .
                $cellStyleXml .
                '</style:style>'
            );
        }

        // Генерация стиля абзаца
        if (!empty($paraProperties)) {
            $paraStyleXml = '<style:paragraph-properties ' . implode(' ', $paraProperties) . '/>';
            $paraStyleName = 'ParaStyle_' . substr(md5($paraStyleXml), 0, 8);
            $this->factory->getGenerator()->addAutomaticStyle(
                '<style:style style:name="' . $paraStyleName . '" style:family="paragraph">' .
                $paraStyleXml .
                '</style:style>'
            );
            $textStyleName = $paraStyleName;
        }

        // Обработка colspan/rowspan
        $colspan = 1;
        if ($node->hasAttribute('colspan')) {
            $colspan = (int)$node->getAttribute('colspan');
            $colspan = min($colspan, $this->availableCols);
        }

        $rowspan = 1;
        if ($node->hasAttribute('rowspan')) {
            $rowspan = (int)$node->getAttribute('rowspan');
            if ($rowspan < 1) $rowspan = 1;
        }


        // Если есть CSS-свойства для текста — создаём отдельный стиль
        if (!empty($css_inline)) {
            $textStyleName = $this->factory->getStyleGenerator()->ensureInlineStyle($css_inline, null,true);
        }

        // Формируем атрибуты ячейки
        $attrs = '';
        if ($colspan > 1) {
            $attrs .= ' table:number-columns-spanned="' . $colspan . '"';
        }
        if ($rowspan > 1) {
            $attrs .= ' table:number-rows-spanned="' . $rowspan . '"';
        }
        if ($cellStyleName) {
            $attrs .= ' table:style-name="' . $cellStyleName . '"';
        }

        // Обработка содержимого ячейки
        $content = $this->build($node, $textStyleName);

        $paragraphs[] = '<table:table-cell' . $attrs . '>' .
            $content .
            '</table:table-cell>';
    }

    /**
     * Для текстового содержимого возвращает абзац.
     *
     * @inheritDoc
     */
    protected function build(\DOMNode $node, string $styleName = ''): string
    {
        $result = '';
        $textContent = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === \XML_TEXT_NODE) {
                /* текстовое содержимое */
                if (trim($child->nodeValue)) {
                    $textContent .= \htmlspecialchars($child->nodeValue, \ENT_NOQUOTES, 'UTF-8');
                }
            } elseif ($child->nodeType === \XML_ELEMENT_NODE) {
                /* Нода */
                $handler = $this->factory->getHandler($child);
                $output = [];
                $handler->handle($child, $output);
                if (!empty($output)) {
                    // Если есть накопленный текст, добавляем его перед элементом
                    if ($textContent !== '') {
                        $result .= '<text:span text:style-name="' . $styleName . '">' . $textContent . '</text:span>';
                        $textContent = '';
                    }
                    $result .= implode('', $output);
                }
            }
        }

        // Добавляем оставшийся текст после всех элементов
        if ($textContent !== '') {
            $result .= '<text:span text:style-name="' . $styleName . '">' . $textContent . '</text:span>';
        }

        // Оборачиваем всё содержимое в один text:p, если оно есть
        if ($result !== '') {
            $result = '<text:p text:style-name="' . $styleName . '">' . $result . '</text:p>';
        }
        return $result;
    }

    /**
     * Возвращает массив стилей для ячейки и абзаца (содержимого ячейки).
     *
     * @param \DOMNode $node
     * @return array[]
     */
    private function generateCellStyle(\DOMNode $node): array
    {
        $cellProperties = []; // Для <style:table-cell-properties>
        $paraProperties = []; // Для <style:paragraph-properties>

        // 1. Границы (из таблицы)
        $defaultBorder = $this->factory->getGenerator()->getDefaultBorder();
        if ($defaultBorder) {
            $borders = StyleHelper::generateBorderSides($defaultBorder);
            foreach ($borders as $prop => $value) {
                $cellProperties[] = $prop . '="' . $value . '"';
            }
        }

        // 2. Padding (из таблицы или ячейки)
        $padding = null;
        if ($node->hasAttribute('cellpadding')) {
            $padding = $node->getAttribute('cellpadding');
        } elseif ($this->factory->getGenerator()->getDefaultCellPadding()) {
            $padding = $this->factory->getGenerator()->getDefaultCellPadding();
        }
        if ($padding !== null) {
            $converted = StyleHelper::convertToCm($padding);
            if ($converted) {
                $cellProperties[] = 'fo:padding="' . $converted . '"';
            }
        }

        // 3. Фон
        if ($node->hasAttribute('bgcolor')) {
            $bgcolor = $node->getAttribute('bgcolor');
            $normalizedColor = StyleHelper::normalizeColor($bgcolor);
            $cellProperties[] = 'fo:background-color="' . $normalizedColor . '"';
        }

        // 4. Вертикальное выравнивание
        if ($node->hasAttribute('valign')) {
            $valign = StyleHelper::getVerticalAlignmentOdt($node->getAttribute('valign'));
            $cellProperties[] = 'style:vertical-align="' . $valign . '"';
        }

        // 5. Горизонтальное выравнивание ? на абзац
        if ($node->hasAttribute('align')) {
            $align = StyleHelper::getAlignmentOdt($node->getAttribute('align'));
            $paraProperties[] = 'fo:text-align="' . $align . '"';
        }

        // 6. Ширина ячейки
        // Ширина теперь обрабатывается на уровне столбцов таблицы в TableHandler
        // Атрибут width игнорируется на уровне ячейки

        return [
            'cell' => $cellProperties,
            'paragraph' => $paraProperties,
        ];
    }

}
