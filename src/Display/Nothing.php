<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer\Display;

use Innmind\CLI\{
    Console,
    Environment,
};
use Innmind\Immutable\Str;

/**
 * @psalm-immutable
 */
final class Nothing implements Output
{
    private function __construct()
    {
    }

    public function __invoke(Environment|Console $env, Str $data): Environment|Console
    {
        return $env;
    }

    /**
     * @psalm-pure
     */
    public static function of(): self
    {
        return new self;
    }
}
