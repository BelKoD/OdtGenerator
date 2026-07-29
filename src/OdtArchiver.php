<?php

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\Exception\IOException;

/**
 * Класс для работы с ZIP-архивами ODT документов
 */
class OdtArchiver implements OdtArchiverInterface
{
    /**
     * Создает ZIP-архив с файлами ODT документа
     *
     * @param string $outputPath Путь к выходному файлу
     * @param array $files Массив файлов для добавления в архив ['путь_в_архиве' => 'содержимое']
     * @param array $directories Массив директорий для добавления [['source' => 'путь', 'target' => 'путь_в_архиве']]
     * @return void
     * @throws IOException
     */
    public function createArchive(string $outputPath, array $files, array $directories = []): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new IOException("Не удалось создать файл: " . $outputPath);
        }

        // mimetype (должен быть первым и не сжатым!)
        if (isset($files['mimetype'])) {
            $zip->addFromString('mimetype', $files['mimetype']);
            $zip->setCompressionIndex(0, \ZipArchive::CM_STORE);
            unset($files['mimetype']);
        } else {
            $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
            $zip->setCompressionIndex(0, \ZipArchive::CM_STORE);
        }

        $subManifest = '';

        // Добавляем дополнительные директории
        foreach ($directories as $dirInfo) {
            $sourceDir = $dirInfo['source'];
            $targetDir = $dirInfo['target'];
            
            if (!is_dir($sourceDir)) {
                continue;
            }

            $manifestEntries = $this->addDirectory($zip, $sourceDir, $targetDir);
            foreach ($manifestEntries as $entry) {
                $subManifest .= $entry . "\n";
            }
        }

        // META-INF/manifest.xml
        $manifest = $this->buildManifest($subManifest);
        $zip->addFromString('META-INF/manifest.xml', $manifest);

        // Добавляем остальные файлы
        foreach ($files as $archivePath => $content) {
            if ($archivePath === 'mimetype') {
                continue; // уже добавлен
            }
            $zip->addFromString($archivePath, $content);
        }

        $zip->close();
    }

    /**
     * Добавляет директорию с файлами в архив
     *
     * @param \ZipArchive $zip Экземпляр ZipArchive
     * @param string $sourceDir Исходная директория
     * @param string $targetDir Целевая директория в архиве
     * @return array Массив записей манифеста
     * @throws IOException
     */
    public function addDirectory(\ZipArchive $zip, string $sourceDir, string $targetDir): array
    {
        $manifestEntries = [];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $archivePath = $targetDir . '/' . $relativePath;

            if ($file->isDir()) {
                // Папки добавлять не нужно — ZipArchive добавит автоматически при добавлении файлов
                continue;
            }

            $mimeType = $this->getMimeType($filePath);
            $manifestEntries[] = '<manifest:file-entry manifest:full-path="' . $archivePath . '" manifest:media-type="' . $mimeType . '"/>';

            if (!$zip->addFile($filePath, $archivePath)) {
                throw new IOException("Не удалось добавить файл в архив: {$archivePath}");
            }
        }

        return $manifestEntries;
    }

    /**
     * Определяет MIME-тип файла
     *
     * @param string $filePath Путь к файлу
     * @return string MIME-тип файла
     */
    public function getMimeType(string $filePath): string
    {
        $mime = mime_content_type($filePath);
        if ($mime === 'image/svg') {
            return 'image/svg+xml';
        }
        return $mime ?: 'application/octet-stream';
    }

    /**
     * Строит XML манифеста
     *
     * @param string $subManifest Дополнительные записи манифеста
     * @return string XML манифеста
     */
    private function buildManifest(string $subManifest): string
    {
        $manifest = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $manifest .= '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">' . "\n";
        $manifest .= '<manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>' . "\n";
        $manifest .= '<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>' . "\n";
        $manifest .= $subManifest;
        $manifest .= '</manifest:manifest>';
        
        return $manifest;
    }
}
