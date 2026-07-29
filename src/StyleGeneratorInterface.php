<?php

namespace BelKoD\OdtGenerator;

/**
 * Интерфейс для генератора стилей ODT.
 */
interface StyleGeneratorInterface
{
    /**
     * Устанавливает глобальные настройки.
     *
     * @param array $settings
     * @return void
     */
    public function setGlobalSettings(array $settings);

    /**
     * Возвращает глобальные настройки.
     *
     * @return array
     */
    public function getGlobalSettings(): array;

    /**
     * Создает текстовый именованный стиль.
     *
     * @param string $styleName Наименование стиля
     * @param string $properties Свойства стиля
     * @return void
     */
    public function ensureTextStyle(string $styleName, string $properties);

    /**
     * Создает стили на основе массива CSS-свойств.
     *
     * @param array $cssProperties Массив стилей ['key' => 'value'], например ['color' => 'red']
     * @param string|null $parentStyleName Имя наследуемого стиля (родителя) при необходимости
     * @param bool $forParagraph Триггер формирования стилей для текста или абзаца
     * @return string Имя созданного стиля
     */
    public function ensureInlineStyle(array $cssProperties, $parentStyleName = null, bool $forParagraph = false): string;

    /**
     * Создает стиль для абзаца, начинающего новый лист (разрыв страницы).
     *
     * @return void
     */
    public function ensurePageBreakStyle();

    /**
     * Создает блок документа ODT, содержащий стили.
     *
     * @param array $automaticStyles Массив созданных стилей
     * @return string XML-строка со стилями
     */
    public function buildDocumentStyles(array $automaticStyles = []): string;
}
