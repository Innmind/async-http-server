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
final class Everything implements Output
{
    private function __construct()
    {
    }

    #[\Override]
    public function __invoke(Console $env, Str $data): Attempt
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
