<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Mapping;

/**
 * Неизменяемый контекст фазы маппинга.
 *
 * Вместо мутабельного глобального состояния генератора (border/padding
 * таблиц и т.п.) контекст реализует стек областей видимости: pushScope()
 * возвращает НОВЫЙ экземпляр контекста, родительский остаётся нетронутым.
 * Это корректно работает при вложенных таблицах любой глубины.
 */
final class ParseContext
{
    /** @var array<string, mixed> Стек опций, последний элемент — текущая область. */
    private $scopes;

    /** @param array<string, mixed> $scopes */
    private function __construct(array $scopes)
    {
        $this->scopes = $scopes;
    }

    public static function create(): self
    {
        return new self([]);
    }

    /**
     * Возвращает новый контекст с добавленной областью видимости.
     * Текущий объект не изменяется.
     *
     * @param array<string, mixed> $options Например ['tableBorder' => '0.5pt solid #000']
     */
    public function pushScope(array $options): self
    {
        $scopes = $this->scopes;
        $scopes[] = $options;
        return new self($scopes);
    }

    /**
     * Ищет значение опции сверху вниз по стеку областей.
     *
     * @param mixed $default
     * @return mixed
     */
    public function option(string $name, $default = null)
    {
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (array_key_exists($name, $this->scopes[$i])) {
                return $this->scopes[$i][$name];
            }
        }
        return $default;
    }
}
