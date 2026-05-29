<?php
namespace App\Support;

use Illuminate\Contracts\Translation\Translator;

class Localization
{
    /**
     * Translate the given message.
     * @param  string  $key
     * @param  array<string, mixed>  $replace
     * @return string
     */
    public static function translate(string $key, array $replace = []): string
    {
        return (string) app(Translator::class)->get($key, $replace);
    }
}
