<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer\Display;

use Innmind\CLI\Environment;
use Innmind\Immutable\Str;

/**
 * @psalm-immutable
 */
interface Output
{
    public function __invoke(Environment $env, Str $data): Environment;
}
