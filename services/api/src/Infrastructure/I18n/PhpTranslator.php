<?php
declare(strict_types=1);

namespace App\Infrastructure\I18n;

class PhpTranslator
{
    private array $translations;
    private string $lang;

    private const SUPPORTED = ['en', 'es', 'de'];

    public function __construct(string $lang)
    {
        $this->lang = in_array($lang, self::SUPPORTED, true) ? $lang : 'en';
        $this->translations = require __DIR__ . "/lang/{$this->lang}.php";
    }

    public function t(string $key): string
    {
        return $this->translations[$key] ?? $key;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    /** @return string[] */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }
}
