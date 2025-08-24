<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer\Display;

use Innmind\CLI\Console;
use Innmind\Immutable\{
    Attempt,
    Str,
};

/**
 * @psalm-immutable
 */
final class Nothing implements Output
{
    private function __construct()
    {
    }

    #[\Override]
    public function __invoke(Console $env, Str $data): Attempt
    {
        return Attempt::result($env);
    }

    /**
     * @psalm-pure
     */
    public static function of(): self
    {
        return new self;
    }
}
