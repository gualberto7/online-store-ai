<?php

namespace App\Actions;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

readonly class CatalogPriceFilter
{
    /** @param list<array{operator: string, price: string}> $constraints */
    public function __construct(public array $constraints = [], public string $search = '') {}

    public static function fromQuery(string $query): self
    {
        $text = Str::lower(Str::ascii($query));
        $number = '(?:\d{1,3}(?:[., ]\d{3})+|\d+)(?:[.,]\d{1,2})?(?![\d.,])';
        $currency = '(?:bs\.?|bob|bolivianos?)';
        $notQuantity = '(?!\s*(?:anos?|tallas?|cm|kg|litros?|unidades?|personas?|%)(?:\b|$))';
        $constraints = [];
        $text = preg_replace_callback('/\bentre\s+(?:'.$currency.'\s*)?(?<low>'.$number.')\s*(?:'.$currency.')?\s+(?:y|a)\s+(?:'.$currency.'\s*)?(?<high>'.$number.')'.$notQuantity.'\s*(?:'.$currency.')?/iu', function (array $match) use (&$constraints): string {
            $constraints[] = ['operator' => '>=', 'price' => self::decimal($match['low'])];
            $constraints[] = ['operator' => '<=', 'price' => self::decimal($match['high'])];

            return ' ';
        }, $text) ?? $text;
        $comparisons = 'menor(?:es)?\s+o\s+igual(?:es)?\s+a|mayor(?:es)?\s+o\s+igual(?:es)?\s+a|no\s+mas\s+de|no\s+superior(?:es)?\s+a|que\s+no\s+(?:supere|pase\s+de)|menor(?:es)?\s+(?:que|a)|menos\s+de|por\s+debajo\s+de|inferior(?:es)?\s+a|hasta|como\s+maximo|maximo(?:\s+de)?|mayor(?:es)?\s+(?:que|a)|mas\s+de|por\s+encima\s+de|superior(?:es)?\s+a|desde|al\s+menos|como\s+minimo|minimo(?:\s+de)?|a\s+partir\s+de|presupuesto(?:\s+de)?|<=|>=|<|>';
        $text = preg_replace_callback('/(?<!\w)(?:(?:con\s+)?(?:un\s+)?precios?\s+)?(?<comparison>'.$comparisons.')\s*(?:'.$currency.'\s*)?(?<number>'.$number.')'.$notQuantity.'\s*(?:'.$currency.')?/iu', function (array $match) use (&$constraints): string {
            $comparison = preg_replace('/\s+/', ' ', $match['comparison']);
            $operator = match (true) {
                in_array($comparison, ['<=', '>=', '<', '>'], true) => $comparison,
                str_contains($comparison, 'igual') => str_starts_with($comparison, 'mayor') ? '>=' : '<=',
                preg_match('/^(no |que no |hasta|como maximo|maximo|presupuesto)/', $comparison) === 1 => '<=',
                preg_match('/^(desde|al menos|como minimo|minimo|a partir)/', $comparison) === 1 => '>=',
                preg_match('/^(mayor|mas de|por encima|superior)/', $comparison) === 1 => '>',
                default => '<',
            };
            $constraints[] = ['operator' => $operator, 'price' => self::decimal($match['number'])];

            return ' ';
        }, $text) ?? $text;
        if ($constraints === []) {
            return new self([], $query);
        }
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text, " \t\n\r\0\x0B,.;");
        $text = preg_replace('/(?:\b(?:con|de|por|y|a|precio|precios)\s*)+$/u', '', $text) ?? $text;

        return new self($constraints, trim($text));
    }

    /** @param Builder<ProductVariant> $query */
    public function apply(Builder $query): void
    {
        foreach ($this->constraints as $constraint) {
            $query->where('price', $constraint['operator'], $constraint['price']);
        }
    }

    public function label(): ?string
    {
        if ($this->constraints === []) {
            return null;
        }
        $labels = ['<' => 'Menos de', '<=' => 'Hasta', '>' => 'Más de', '>=' => 'Desde'];

        return implode(' y ', array_map(fn (array $constraint): string => $labels[$constraint['operator']].' '.self::display($constraint['price']).' Bs', $this->constraints));
    }

    private static function display(string $price): string
    {
        [$whole, $fraction] = explode('.', $price);

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }

    private static function decimal(string $number): string
    {
        $number = str_replace(' ', '', $number);
        $parts = preg_split('/[.,]/', $number) ?: [$number];
        $fraction = count($parts) > 1 && strlen(end($parts)) <= 2 ? array_pop($parts) : '00';
        $whole = ltrim(implode('', $parts), '0') ?: '0';

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
