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
interface Output
{
    /**
     * @return Attempt<Console>
     */
    public function __invoke(Console $env, Str $data): Attempt;
}
