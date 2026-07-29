<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\StyleHelper;
use BelKoD\OdtGenerator\Utils\Misc;

class ImgHandler extends TagHandler
{

    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        $src = $node->getAttribute('src');
        if (!$src) {
            return;
        }

        // Определяем путь к изображению
        $imagePath = $this->resolveImagePath($src);
        if (!$imagePath) {
            return; // Не удалось загрузить изображение
        }

        // Генерируем имя файла в архиве
        $archivePath = 'Pictures/' . basename($imagePath);

        // Копируем изображение во временную директорию (для последующей упаковки)
        $tempDir = $this->factory->getGenerator()->getTempDir();
        $targetPath = $tempDir . '/' . $archivePath;
        if (!is_dir(dirname($targetPath))) {
            mkdir(dirname($targetPath), 0777, true);
        }
        copy($imagePath, $targetPath);


        $style_arr = $this->sub_style($node, $imagePath);
        // Генерируем XML
        $xml = '<text:p text:style-name="' . $style_arr['styleName'] . '">';
        $xml .= '<draw:frame';
        if ($style_arr['width']) {
            $xml .= ' svg:width="' . $style_arr['width'] . '"';
        }
        if ($style_arr['height']) {
            $xml .= ' svg:height="' . $style_arr['height'] . '"';
        }
        $xml .= ' draw:style-name="Graphics" draw:text-style-name="Graphics">';
        $xml .= '<draw:image xlink:href="' . $archivePath . '" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/>';
        $xml .= '</draw:frame>';
        $xml .= '</text:p>';

        $paragraphs[] = $xml;
    }

    /**
     * Вовзращает стиль и размеры изображения.
     *
     * @param \DOMNode $node Нода
     * @param string $imagePath Путь к изображению
     * @return array Вовзращает массив содержащий наименование стиля и размеры изображения.
     */
    protected function sub_style(\DOMNode $node, string $imagePath): array
    {
        $styleName = $this->style($node, ['forParagraph' => true, 'parentStyleName' => 'StandardParagraph']);

        // Получаем размеры изображения
        $imgSize = @getimagesize($imagePath);
        $width = $height = null;
        if ($imgSize) {
            $width = $imgSize[0];
            $height = $imgSize[1];
        }

        // Обрабатываем атрибуты width/height
        $styleWidth = $styleHeight = null;

        if ($node->hasAttribute('width')) {
            $styleWidth = $node->getAttribute('width');
            if (is_numeric($styleWidth)) {
                $styleWidth .= 'px';
            }
        }
        if ($node->hasAttribute('height')) {
            $styleHeight = $node->getAttribute('height');
            if (is_numeric($styleHeight)) {
                $styleHeight .= 'px';
            }
        }

        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));
            $styleWidth = Misc::arrayExtract($css, 'width', $styleWidth);
            $styleHeight = Misc::arrayExtract($css, 'height', $styleHeight);
        }

        // Конвертируем заданные размеры в cm
        $widthCm = $styleWidth ? StyleHelper::convertToCm($styleWidth) : null;
        $heightCm = $styleHeight ? StyleHelper::convertToCm($styleHeight) : null;

        // Если задан только один размер — вычисляем второй с сохранением пропорций
        if ($width && $height) {
            if ($widthCm && !$heightCm) {
                // Задана ширина -> вычисляем высоту
                $ratio = $height / $width;
                $heightInCm = (float)filter_var($widthCm, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $heightCm = number_format($heightInCm * $ratio, 6, '.', '') . 'cm';
            } elseif ($heightCm && !$widthCm) {
                // Задана высота -> вычисляем ширину
                $ratio = $width / $height;
                $widthInCm = (float)filter_var($heightCm, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $widthCm = number_format($widthInCm * $ratio, 6, '.', '') . 'cm';
            } elseif (!$widthCm && !$heightCm) {
                // Ничего не задано -> используем оригинальные размеры в cm
                $widthCm = StyleHelper::convertToCm($width);
                $heightCm = StyleHelper::convertToCm($height);
            }
        } else {
            // Не удалось получить оригинальные размеры -> используем заданные или оставляем пустыми
            if (!$widthCm && !$heightCm) {
                $widthCm = '0cm';
                $heightCm = '0cm';
                // Нет данных — пропускаем изображение или используем значения по умолчанию
            }
        }

        return ['styleName' => $styleName, 'width' => $widthCm, 'height' => $heightCm];
    }

    private function resolveImagePath($src)
    {
        // Абсолютный URL
        if (preg_match('#^https?://#', $src)) {
            $tempDir = sys_get_temp_dir();
            $filename = basename(parse_url($src, PHP_URL_PATH));
            if (!$filename) {
                $filename = 'image_' . md5($src) . '.jpg';
            }
            $localPath = $tempDir . '/' . uniqid('img_') . '_' . $filename;

            // Скачиваем изображение
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'ODT-Generator/1.0'
                ]
            ]);
            $data = @file_get_contents($src, false, $context);
            if ($data !== false) {
                file_put_contents($localPath, $data);
                return $localPath;
            }
            return null;
        }

        // Относительный путь — предполагаем, что путь от корня проекта
        $basePath = GAR_PATH_ROOT ?? getcwd();
        $fullPath = realpath($basePath . '/' . ltrim($src, '/'));

        if ($fullPath && file_exists($fullPath)) {
            return $fullPath;
        }

        // Если не найдено — пробуем относительно текущего скрипта
        $fullPath = realpath(dirname(__FILE__) . '/../' . $src);
        if ($fullPath && file_exists($fullPath)) {
            return $fullPath;
        }

        return null;
    }
}