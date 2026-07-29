<?php

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\HtmlTags\IgnoredTagHandler;
use BelKoD\OdtGenerator\Utils\Misc;

/**
 * Помощник по обработке значений стилей.
 */
class StyleHelper
{
    /**
     * Конвертирует значение в cm (сантиметры).
     *
     * @param mixed $value Значение атрибута
     * @return string|null
     */
    public static function convertToCm($value)
    {
        if (is_numeric($value)) {
            $value .= 'px';
        }

        if (preg_match('/^(\d*\.?\d+)(px|pt|cm|mm|in|%)?$/', $value, $matches)) {
            $number = (float)$matches[1];
            $unit = $matches[2] ?? 'px';

            switch ($unit) {
                case 'cm':
                    return number_format($number, 6, '.', '') . 'cm';
                case 'mm':
                    return number_format($number / 10, 6, '.', '') . 'cm';
                case 'in':
                    return number_format($number * 2.54, 6, '.', '') . 'cm';
                case 'pt':
                    return number_format($number * 0.0352778, 6, '.', '') . 'cm';
                case '%':
                    return null;
                case 'px':
                default:
                    return number_format($number * 0.0264583, 6, '.', '') . 'cm';
            }
        }

        return null;
    }

    /**
     * Конвертирует значение в pt (пункты).
     *
     * @param mixed $value Значение атрибута
     * @return string
     */
    public static function convertToPt($value): string
    {
        if (is_numeric($value)) {
            $value .= 'px';
        }

        if (preg_match('/^(\d*\.?\d+)(px|pt|cm|mm|in)?$/i', $value, $matches)) {
            $number = (float)$matches[1];
            $unit = isset($matches[2]) ? strtolower($matches[2]) : 'px';

            $pt = null;

            switch ($unit) {
                case 'pt':
                    $pt = $number;
                    break;
                case 'px':
                    $pt = $number * 0.75; // 1px = 0.75pt
                    break;
                case 'cm':
                    $pt = $number * 28.3465; // 1cm = 28.3465pt
                    break;
                case 'mm':
                    $pt = $number * 2.83465; // 1mm = 2.83465pt
                    break;
                case 'in':
                    $pt = $number * 72; // 1in = 72pt
                    break;
                default:
                    $pt = $number * 0.75; // fallback to px
                    break;
            }

            return number_format($pt, 3, '.', '') . 'pt';
        }

        return $value; // возвращаем как есть, если не распознали
    }

    /**
     * Возвращает значение горизонтальной ориентации.
     *
     * @param string $align Горизонтальная ориентация.
     * @return string
     */
    public static function getAlignmentOdt(string $align): string
    {
        $map = [
            'left' => 'left',
            'right' => 'right',
            'center' => 'center',
            'justify' => 'justify',
        ];
        return $map[strtolower($align)] ?? 'start';
    }

    /**
     * Возвращает значение вертикальной ориентации.
     *
     * @param string $valign Вертикальная ориентация.
     * @return string
     */
    public static function getVerticalAlignmentOdt(string $valign): string
    {
        $map = [
            'top' => 'top',
            'middle' => 'middle',
            'bottom' => 'bottom',
        ];
        return $map[strtolower($valign)] ?? 'top';
    }

    /**
     * Возвращает массив значений "бордюра".
     *
     * @param mixed $border Значение "бордюра".
     * @return array|string[]
     */
    public static function generateBorderSides($border): array
    {
        if (empty($border)) return [];

        $borderDef = null;

        if (is_numeric($border)) {
            //$widthCm = number_format((float)$border * 0.0264583, 6, '.', '');
            $widthCm = number_format((float)$border * 0.01763887, 6, '.', '');
            $borderDef = $widthCm . 'cm solid #000000';
        } else {
            // Пока не парсим сложные значения — только число
            return [];
        }

        return [
            'fo:border-top' => $borderDef,
            'fo:border-bottom' => $borderDef,
            'fo:border-left' => $borderDef,
            'fo:border-right' => $borderDef,
        ];
    }

    /**
     * Возвращает ширину страницы в cm для заданного формата и ориентации
     *
     * @param string $size Формат страницы (A4, A5, Letter, Legal)
     * @param string $orientation Ориентация (portrait, landscape)
     * @return string Ширина в формате "21.000cm"
     */
    public static function getPageSizeWidth(string $size, string $orientation): string
    {
        $sizes = self::getPageSizes();

        if (!isset($sizes[$size])) {
            $size = 'A4';
        }

        if ($orientation === 'landscape') {
            return $sizes[$size]['height'];
        } else {
            return $sizes[$size]['width'];
        }
    }

    /**
     * Возвращает высоту страницы в cm для заданного формата и ориентации
     *
     * @param string $size Формат страницы (A4, A5, Letter, Legal)
     * @param string $orientation Ориентация (portrait, landscape)
     * @return string Высота в формате "29.700cm"
     */
    public static function getPageSizeHeight(string $size, string $orientation): string
    {
        $sizes = self::getPageSizes();

        if (!isset($sizes[$size])) {
            $size = 'A4';
        }

        if ($orientation === 'landscape') {
            return $sizes[$size]['width'];
        } else {
            return $sizes[$size]['height'];
        }
    }

    /**
     * Возвращает массив стандартных размеров страниц
     *
     * @return array
     */
    private static function getPageSizes(): array
    {
        return [
            'A4' => ['width' => '21.000cm', 'height' => '29.700cm'],
            'A5' => ['width' => '14.800cm', 'height' => '21.000cm'],
            'Letter' => ['width' => '21.590cm', 'height' => '27.940cm'],
            'Legal' => ['width' => '21.590cm', 'height' => '35.560cm'],
        ];
    }

    /**
     * Нормализует CSS-цвет в формат #rrggbb
     *
     * @param string $color CSS-цвет (например, "#abc", "red", "#aabbcc")
     * @return string Нормализованный цвет в формате #rrggbb
     */
    public static function normalizeColor(string $color): string
    {
        // Преобразуем #rgb -> #rrggbb
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $color, $matches)) {
            return '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
        }

        // Именованные цвета — можно расширить по необходимости
        $namedColors = [
            'black' => '#000000',
            'white' => '#ffffff',
            'red' => '#ff0000',
            'green' => '#008000',
            'blue' => '#0000ff',
            'yellow' => '#ffff00',
            'cyan' => '#00ffff',
            'magenta' => '#ff00ff',
            'gray' => '#808080',
            'silver' => '#c0c0c0',
            'maroon' => '#800000',
            'olive' => '#808000',
            'purple' => '#800080',
            'teal' => '#008080',
            'navy' => '#000080',
            'lime' => '#00ff00',
            'fuchsia' => '#ff00ff',
            'aqua' => '#00ffff',
        ];

        $color = strtolower(trim($color));

        if (isset($namedColors[$color])) {
            return $namedColors[$color];
        }

        // Возвращаем как есть, если не распознали
        return $color;
    }

    /**
     * Возвращает массив пары стиль-значение распарсенного атрибута style.
     *
     * @param mixed $styleString
     * @return array
     */
    public static function parseCss($styleString): array
    {
        if (!is_string($styleString)) {
            return [];
        }

        $styles = [];
        $declarations = explode(';', $styleString);

        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if (empty($declaration)) {
                continue;
            }

            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = trim(strtolower($parts[0]));
            $value = trim($parts[1]);

            if ($property && $value) {
                $styles[$property] = $value;
            }
        }

        return $styles;
    }

    /**
     * Определяет видимость тега. True - тег видимый.
     * Базируется на стиле "display", тег считается видимым при любом значении кроме "none".
     *
     * @param \DOMNode $node нода узла
     * @return bool
     */
    public static function display(\DOMNode $node): bool
    {
        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));
            $display = Misc::arrayExtract($css, 'display');
            if ($display == 'none') {
                return false;
            }
        }
        return true;
    }
}