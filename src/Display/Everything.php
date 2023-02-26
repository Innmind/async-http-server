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
final class Everything implements Output
{
    private function __construct()
    {
    }

    /** @psalm-suppress InvalidReturnType */
    public function __invoke(Environment|Console $env, Str $data): Environment|Console
    {
        /** @psalm-suppress InvalidReturnStatement */
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
