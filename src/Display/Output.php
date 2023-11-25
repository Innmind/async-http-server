<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer\Display;

use Innmind\CLI\Console;
use Innmind\Immutable\Str;

/**
 * @psalm-immutable
 */
interface Output
{
    public function __invoke(Console $env, Str $data): Console;
}
