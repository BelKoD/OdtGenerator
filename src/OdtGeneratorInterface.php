<?php

namespace BelKoD\OdtGenerator;

/**
 * Интерфейс для генератора ODT документов.
 */
interface OdtGeneratorInterface
{
    /**
     * Обработка HTML данных, генерация XML представления и создание файла ODT.
     *
     * @return self
     * @throws Exception\IOException
     */
    public function generate(): self;

    /**
     * Устанавливает глобальные настройки.
     *
     * @param array $settings
     * @return self
     */
    public function setGlobalSettings(array $settings): self;

    /**
     * Возвращает глобальные настройки.
     *
     * @return array
     */
    public function getGlobalSettings(): array;

    /**
     * Устанавливает границу по умолчанию.
     *
     * @param string|null $border Граница (например, "1pt solid #000")
     * @return self
     */
    public function setDefaultBorder(?string $border): self;

    /**
     * Возвращает границу по умолчанию.
     *
     * @return string|null
     */
    public function getDefaultBorder(): ?string;

    /**
     * Добавляет автоматический стиль.
     *
     * @param string $styleXml XML-строка стиля
     * @return self
     */
    public function addAutomaticStyle(string $styleXml): self;

    /**
     * Устанавливает отступ ячеек по умолчанию.
     *
     * @param string|null $padding Отступ (например, "0.1cm")
     * @return self
     */
    public function setDefaultCellPadding(?string $padding): self;

    /**
     * Возвращает отступ ячеек по умолчанию.
     *
     * @return string|null
     */
    public function getDefaultCellPadding(): ?string;

    /**
     * Возвращает массив мастер-стилей.
     *
     * @return array
     */
    public function getMasterStyles(): array;

    /**
     * Устанавливает мастер-стили.
     *
     * @param string $masterStyles XML-строка мастер-стилей
     * @param string $type Тип мастер-стиля (например, 'header', 'footer')
     * @return self
     */
    public function setMasterStyles(string $masterStyles, string $type = 'header'): self;

    /**
     * Возвращает полный путь к созданному ODT-файлу.
     *
     * @return string
     */
    public function getOutputPath(): string;

    /**
     * Возвращает содержимое созданного ODT документа.
     *
     * @return string|null
     * @throws Exception\IOException
     */
    public function getOutputFile(): ?string;
}
