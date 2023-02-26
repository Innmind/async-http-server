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
interface Output
{
    /**
     * @template T of Environment|Console
     *
     * @param T $env
     *
     * @return T
     */
    public function __invoke(Environment|Console $env, Str $data): Environment|Console;
}
