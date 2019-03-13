<?php

namespace App\Services;

class ProductHelper
{
    public static function generateShortName(string $name): ?string
    {
        $charsPattern = 'а-яА-ЯЁёa-zA-Z0-9_';
        $name = preg_replace_callback(
            "/([$charsPattern]{3})([$charsPattern]+)/u",
            function(array $match) {
                return $match[1].'.';
            },
            $name
        );

        return preg_replace('/[.]{2,}/', '.', $name);
    }
}
