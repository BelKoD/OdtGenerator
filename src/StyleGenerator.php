<?php

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\Utils\Misc;

/**
 * Генератор стилей.
 */
class StyleGenerator implements StyleGeneratorInterface
{
    /* @var array Кеш текстовых стилей */
    private $textStyles = [];
    /* @var array Кеш инлайновых стилей */
    private $inlineStyles = [];
    /* @var bool Тригер создания стиля разрыва старницы */
    private $pageBreakStyleCreated = false;
    /* @var OdtGenerator */
    private $generator;
    /* @var array Настройки страницы */
    private $globalSettings = [
        'page-size' => 'A4',
        'page-orientation' => 'portrait',
        'margin-top' => '1.5cm',
        'margin-bottom' => '1.5cm',
        'margin-left' => '1.5cm',
        'margin-right' => '1.5cm',
        'font-family' => 'Liberation Serif',
        'font-size' => '12pt',
        'line-height' => '115%',
    ];

    public function __construct($generator)
    {
        $this->generator = $generator;
    }

    /**
     * Добавляет базовые настройки.
     *
     * @param array $settings
     * @return void
     */
    public function setGlobalSettings(array $settings)
    {
        $this->globalSettings = array_merge($this->globalSettings, $settings);
    }

    /**
     * Возвращает базовые настройки.
     *
     * @return array|string[]
     */
    public function getGlobalSettings(): array
    {
        return $this->globalSettings;
    }

    /**
     * Создает текстовый именованый стиль.
     *
     * @param string $styleName Наименование стиля.
     * @param string $properties Свойства стиля.
     * @return void
     */
    public function ensureTextStyle(string $styleName, string $properties)
    {
        if (isset($this->textStyles[$styleName])) {
            return;
        }

        $this->textStyles[$styleName] = true;

        $styleXml = '<style:style style:name="' . $styleName . '" style:family="text" style:parent-style-name="StandardText">' .
            '<style:text-properties ' . $properties . '/>' .
            '</style:style>';

        $this->generator->addAutomaticStyle($styleXml);
    }

    /**
     * Создает стили на основе массива.
     *
     * @param array $cssProperties Массив стилей ['key' => 'value'], например ['color' => 'red']
     * @param string|null $parentStyleName Имя наследуемого стиля (родителя) при необходимости.
     * @param bool $forParagraph Тригер формирования стилей для текста или абзаца.
     * @return mixed|string
     */
    public function ensureInlineStyle(array $cssProperties, $parentStyleName = null, bool $forParagraph = false)
    {
        ksort($cssProperties);
        $key = md5(json_encode($cssProperties) . ($forParagraph ? '_para' : ''));

        if (isset($this->inlineStyles[$key])) {
            return $this->inlineStyles[$key];
        }

        $styleName = 'InlineStyle_' . substr($key, 0, 8);
        $this->inlineStyles[$key] = $styleName;

        $textProps = [];
        $paraProps = [];

        foreach ($cssProperties as $property => $value) {
            switch ($property) {
                case 'font-family':
                    $textProps[] = 'fo:font-family="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                    break;
                case 'font-size':
                    $converted = StyleHelper::convertToPt($value);
                    $textProps[] = 'fo:font-size="' . htmlspecialchars($converted, ENT_QUOTES, 'UTF-8') . '"';
                    break;
                case 'color':
                    $textProps[] = 'fo:color="' . StyleHelper::normalizeColor($value) . '"';
                    break;
                case 'background-color':
                    if ($forParagraph) {
                        $paraProps[] = 'fo:background-color="' . StyleHelper::normalizeColor($value) . '"';
                    } else {
                        $textProps[] = 'fo:background-color="' . StyleHelper::normalizeColor($value) . '"';
                    }
                    break;
                case 'font-weight':
                    $textProps[] = 'fo:font-weight="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                    break;
                case 'font-style':
                    $textProps[] = 'fo:font-style="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                    break;
                case 'text-decoration':
                    if ($value === 'underline') {
                        $textProps[] = 'style:text-underline-style="solid" style:text-underline-width="auto" style:text-underline-color="font-color"';
                    } elseif ($value === 'line-through') {
                        $textProps[] = 'style:text-line-through-style="solid"';
                    }
                    break;
                case 'text-align':
                    $paraProps[] = 'fo:text-align="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                    break;
                case 'line-height':
                    $paraProps[] = 'fo:line-height="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                    break;
                case 'margin':
                case 'margin-top':
                case 'margin-bottom':
                case 'margin-left':
                case 'margin-right':
                    $converted = StyleHelper::convertToCm($value);
                    $prop = str_replace('margin-', 'fo:margin-', $property);
                    $paraProps[] = $prop . '="' . htmlspecialchars($converted, ENT_QUOTES, 'UTF-8') . '"';
                    break;
                case 'padding':
                case 'padding-top':
                case 'padding-bottom':
                case 'padding-left':
                case 'padding-right':
                    $converted = StyleHelper::convertToCm($value);
                    $prop = str_replace('padding-', 'fo:padding-', $property);
                    $paraProps[] = $prop . '="' . htmlspecialchars($converted, ENT_QUOTES, 'UTF-8') . '"';
                    break;
            }
        }

        $styleXml = '';
        if ($forParagraph) {
            $styleXml .= '<style:style style:name="' . $styleName . '" style:family="paragraph"';
            if ($parentStyleName) {
                $styleXml .= ' style:parent-style-name="' . $parentStyleName . '"';
            }
            $styleXml .= '>';
            if (!empty($textProps)) {
                $styleXml .= '<style:text-properties ' . implode(' ', $textProps) . '/>';
            }
            if (!empty($paraProps)) {
                $styleXml .= '<style:paragraph-properties ' . implode(' ', $paraProps) . '/>';
            }
            $styleXml .= '</style:style>';
        } else {
            $styleXml .= '<style:style style:name="' . $styleName . '" style:family="text"';
            if ($parentStyleName) {
                $styleXml .= ' style:parent-style-name="' . $parentStyleName . '"';
            }
            $styleXml .= '>';
            if (!empty($textProps)) {
                $styleXml .= '<style:text-properties ' . implode(' ', $textProps) . '/>';
            }
            $styleXml .= '</style:style>';
        }

        $this->generator->addAutomaticStyle($styleXml);
        return $styleName;
    }

    /**
     * Создает стиль для абзаца начинающего новый лист (разрыв страницы).
     *
     * @return void
     */
    public function ensurePageBreakStyle()
    {
        if ($this->pageBreakStyleCreated) {
            return;
        }

        $styleXml = '<style:style style:name="PageBreakParagraph" style:family="paragraph" style:parent-style-name="StandardParagraph">' .
            '<style:paragraph-properties fo:break-before="page"/>' .
            '</style:style>';

        $this->generator->addAutomaticStyle($styleXml);
        $this->pageBreakStyleCreated = true;
    }

    /**
     * Создает блок документа ODT, содержащий стили.
     *
     * @param array $automaticStyles Массив созданых стилей
     * @return string
     */
    public function buildDocumentStyles(array $automaticStyles = []): string
    {
        $xml = '';
        // Автоматические стили (включая page-layout)
        $xml .= '<office:automatic-styles>' . "\n";
        $xml .= $this->buildPageLayout();
        foreach ($automaticStyles as $style) {
            $xml .= $style . "\n";
        }
        $xml .= '</office:automatic-styles>' . "\n";

        // Стили документа (default-style, именованные стили)
        $xml .= $this->buildDefaultStyles();

        // Мастер-стили (master-page)
        $xml .= '<office:master-styles>' . "\n";
        $xml .= '<style:master-page style:name="Standard" style:page-layout-name="PageLayout1">' . "\n";
        $xml .= $this->buildMasterStyles();
        $xml .= '</style:master-page>' . "\n";
        $xml .= '</office:master-styles>' . "\n";

        return $xml;
    }

    /**
     * Создает стиль базовых настроек страницы.
     *
     * @return string
     */
    private function buildPageLayout(): string
    {
        $settings = $this->globalSettings;
        $xml = '<style:page-layout style:name="PageLayout1">' . "\n";
        $xml .= '<style:page-layout-properties ' .
            'fo:page-width="' . StyleHelper::getPageSizeWidth($settings['page-size'], $settings['page-orientation']) . '" ' .
            'fo:page-height="' . StyleHelper::getPageSizeHeight($settings['page-size'], $settings['page-orientation']) . '" ' .
            'style:print-orientation="' . $settings['page-orientation'] . '" ' .
            'fo:margin-top="' . StyleHelper::convertToCm($settings['margin-top']) . '" ' .
            'fo:margin-bottom="' . StyleHelper::convertToCm($settings['margin-bottom']) . '" ' .
            'fo:margin-left="' . StyleHelper::convertToCm($settings['margin-left']) . '" ' .
            'fo:margin-right="' . StyleHelper::convertToCm($settings['margin-right']) . '" ' .
            'style:writing-mode="lr-tb" ' .
            'fo:padding="0cm" ' .
            'style:footnote-max-height="0cm" ' .
            'loext:margin-gutter="0cm" ' .
            '>' .
            '<style:footnote-sep style:width="0.018cm" style:distance-before-sep="0.101cm" style:distance-after-sep="0.101cm" style:line-style="solid" style:adjustment="left" style:rel-width="25%" style:color="#000000"/>' .
            '</style:page-layout-properties>' . "\n";
        // Стили верхнего и нижнего колонтитулов
        $xml .= '<style:header-style>' .
            '<style:header-footer-properties fo:min-height="0,100cm" fo:margin-left="0cm" fo:margin-right="0cm" fo:margin-bottom="0cm" fo:background-color="transparent" style:dynamic-spacing="true" draw:fill="none"/>' .
            '</style:header-style>' .
            '<style:footer-style>' .
            '<style:header-footer-properties fo:min-height="0,100cm" fo:margin-left="0cm" fo:margin-right="0cm" fo:margin-top="0cm" fo:background-color="transparent" style:dynamic-spacing="true" draw:fill="none"/>' .
            '</style:footer-style>' . "\n";

        $xml .= '</style:page-layout>' . "\n";
        return $xml;
    }

    /**
     * Создает стандартные стили.
     *
     * @return string
     */
    private function buildDefaultStyles(): string
    {
        $settings = $this->globalSettings;

        $styles = '<office:styles>' . "\n";

        // Глобальный стиль текста по умолчанию
        $styles .= '<style:default-style style:family="text">' .
            '<style:text-properties ' .
            'fo:font-family="' . $settings['font-family'] . '" ' .
            'fo:font-size="' . StyleHelper::convertToPt($settings['font-size']) . '" ' .
            '/>' .
            '</style:default-style>' . "\n";

        // Глобальный стиль абзаца по-умолчанию
        $styles .= '<style:default-style style:family="paragraph">' .
            '<style:text-properties ' .
            'fo:font-family="' . $settings['font-family'] . '" ' .
            'fo:font-size="' . StyleHelper::convertToPt($settings['font-size']) . '" ' .
            '/>' .
            '<style:paragraph-properties ' .
            'fo:line-height="' . $settings['line-height'] . '" ' .
            '/>' .
            '</style:default-style>' . "\n";

        // Именованный стиль абзаца
        $styles .= '<style:style style:name="StandardParagraph" style:family="paragraph">' .
            '<style:text-properties ' .
            'fo:font-family="' . $settings['font-family'] . '" ' .
            'fo:font-size="' . StyleHelper::convertToPt($settings['font-size']) . '" ' .
            '/>' .
            '<style:paragraph-properties ' .
            'fo:line-height="' . $settings['line-height'] . '" ' .
            '/>' .
            '</style:style>' . "\n";

        // Именованный стиль текста
        $styles .= '<style:style style:name="StandardText" style:family="text">' .
            '<style:text-properties ' .
            'fo:font-family="' . $settings['font-family'] . '" ' .
            'fo:font-size="' . StyleHelper::convertToPt($settings['font-size']) . '" ' .
            '/>' .
            '</style:style>' . "\n";

        // Изображение
        $styles .= '<style:style style:name="Graphics" style:family="graphic">' .
            '<style:graphic-properties draw:stroke="none" draw:fill="none"/>' .
            '</style:style>' . "\n";

        // Базовые именованые стили: bold, italic и т.д.
        $styles .= '<style:style style:name="Bold" style:family="text" style:parent-style-name="StandardText"><style:text-properties fo:font-weight="bold"/></style:style>' . "\n";
        $styles .= '<style:style style:name="Italic" style:family="text" style:parent-style-name="StandardText"><style:text-properties fo:font-style="italic"/></style:style>' . "\n";
        $styles .= '<style:style style:name="Underline" style:family="text" style:parent-style-name="StandardText"><style:text-properties style:text-underline-style="solid" style:text-underline-width="auto" style:text-underline-color="font-color"/></style:style>' . "\n";
        $styles .= '<style:style style:name="StrikeThrough" style:family="text" style:parent-style-name="StandardText"><style:text-properties style:text-line-through-style="solid"/></style:style>' . "\n";
        $styles .= '<style:style style:name="Subscript" style:family="text" style:parent-style-name="StandardText"><style:text-properties style:text-position="sub 58%"/></style:style>' . "\n";
        $styles .= '<style:style style:name="Superscript" style:family="text" style:parent-style-name="StandardText"><style:text-properties style:text-position="super 58%"/></style:style>' . "\n";

        // Нумерованный список
        $styles .= '<text:list-style style:name="OrderedList"  style:display-name="OrderedList 123" style:family="list">' .
            '<text:list-level-style-number text:level="1" text:style-name="OrderedList_Symbols" loext:num-list-format="%1%." style:num-suffix="." style:num-format="1">' .
            '<style:list-level-properties text:list-level-position-and-space-mode="label-alignment">' .
            '<style:list-level-label-alignment text:label-followed-by="listtab" text:list-tab-stop-position="0.7cm" fo:text-indent="-0.7cm" fo:margin-left="1.33cm"/>' .
            '</style:list-level-properties>' .
            '</text:list-level-style-number>' .
            '<text:list-level-style-number text:level="2" text:style-name="OrderedList_Symbols" loext:num-list-format="%2%." style:num-suffix="." style:num-format="1">' .
            '<style:list-level-properties text:list-level-position-and-space-mode="label-alignment">' .
            '<style:list-level-label-alignment text:label-followed-by="listtab" text:list-tab-stop-position="1.401cm" fo:text-indent="-0.7cm" fo:margin-left="2.03cm"/>' .
            '</style:list-level-properties>' .
            '</text:list-level-style-number>' .
            '<text:list-level-style-number text:level="3" text:style-name="OrderedList_Symbols" loext:num-list-format="%3%." style:num-suffix="." style:num-format="1">' .
            '<style:list-level-properties text:list-level-position-and-space-mode="label-alignment">' .
            '<style:list-level-label-alignment text:label-followed-by="listtab" text:list-tab-stop-position="2.101cm" fo:text-indent="-0.7cm" fo:margin-left="2.731cm"/>' .
            '</style:list-level-properties>' .
            '</text:list-level-style-number>' .
            '</text:list-style>';

        $styles .= '</office:styles>' . "\n";

        return $styles;
    }

    /**
     * Создает мастер-стили, используется для колонтитулов.
     *
     * @return string
     */
    private function buildMasterStyles(): string
    {
        $masterStyles = $this->generator->getMasterStyles();
        $header = Misc::arrayExtract($masterStyles, 'header', []);
        $footer = Misc::arrayExtract($masterStyles, 'footer', []);
        $xml = '<style:header>' . implode('', $header) . '</style:header>' . "\n";
        $xml .= '<style:footer>' . implode('', $footer) . '</style:footer>' . "\n";
        return $xml;
    }
}