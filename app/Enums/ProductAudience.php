<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ProductAudience: string
{
    case Women = 'women';
    case Men = 'men';
    case Unisex = 'unisex';

    public function label(): string
    {
        return match ($this) {
            self::Women => 'Mujer', self::Men => 'Hombre', self::Unisex => 'Unisex',
        };
    }

    public static function fromQuery(string $text): ?self
    {
        $text = Str::lower(Str::ascii($text));
        $women = preg_match('/\b(mujer(?:es)?|femenin[oa]s?|women|woman)\b/', $text) === 1;
        $men = preg_match('/\b(hombre(?:s)?|masculin[oa]s?|men|man)\b/', $text) === 1;

        if ($women && $men) {
            return null;
        }
        if (preg_match('/\bunisex\b/', $text) === 1) {
            return self::Unisex;
        }

        return $women ? self::Women : ($men ? self::Men : null);
    }

    public static function forCatalog(string $name, string $category): self
    {
        return self::fromQuery($name.' '.$category) ?? self::Unisex;
    }
}
