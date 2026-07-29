<?php

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\Exception\IOException;

/**
 * Интерфейс для архиватора ODT файлов
 */
interface OdtArchiverInterface
{
    /**
     * Создает ZIP-архив с файлами ODT документа
     *
     * @param string $outputPath Путь к выходному файлу
     * @param array $files Массив файлов для добавления в архив
     * @param array $directories Массив директорий для добавления в архив
     * @return void
     * @throws IOException
     */
    public function createArchive(string $outputPath, array $files, array $directories = []): void;

    /**
     * Добавляет директорию с файлами в архив
     *
     * @param \ZipArchive $zip Экземпляр ZipArchive
     * @param string $sourceDir Исходная директория
     * @param string $targetDir Целевая директория в архиве
     * @return array Массив записей манифеста
     * @throws IOException
     */
    public function addDirectory(\ZipArchive $zip, string $sourceDir, string $targetDir): array;

    /**
     * Определяет MIME-тип файла
     *
     * @param string $filePath Путь к файлу
     * @return string MIME-тип файла
     */
    public function getMimeType(string $filePath): string;
}
