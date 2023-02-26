<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer\Display;

use Innmind\CLI\Environment;
use Innmind\Immutable\Str;

/**
 * @psalm-immutable
 */
final class Nothing implements Output
{
    private function __construct()
    {
    }

    public function __invoke(Environment $env, Str $data): Environment
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
