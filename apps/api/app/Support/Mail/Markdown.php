<?php

namespace App\Support\Mail;

/** Mail bodies are Markdown; text that users or instructors wrote must not become links or formatting. */
final class Markdown
{
    public static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|>~<])/', '\\\\$1', $text);
    }
}
