<?php

namespace BelKoD\OdtGenerator\Utils;

/**
 * Вспомогательный класс с утилитами
 */
class Misc
{
    /**
     * Извлекает значение из массива по ключу, возвращает значение по умолчанию если ключ не найден
     *
     * @param array $array Исходный массив
     * @param string $key Ключ для извлечения
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public static function arrayExtract(array &$array, string $key, $default = null)
    {
        if (array_key_exists($key, $array)) {
            $value = $array[$key];
            unset($array[$key]);
            return $value;
        }
        return $default;
    }
}
