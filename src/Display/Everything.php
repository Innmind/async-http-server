<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer\Display;

use Innmind\CLI\Console;
use Innmind\Immutable\Str;

/**
 * @psalm-immutable
 */
final class Everything implements Output
{
    private function __construct()
    {
    }

    public function __invoke(Console $env, Str $data): Console
    {
        return $env->output($data);
    }

    /**
     * @psalm-pure
     */
    public static function of(): self
    {
        return new self;
    }
}
