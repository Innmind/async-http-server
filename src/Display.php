<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Async\HttpServer\Display\{
    Output,
    Everything,
};
use Innmind\CLI\Console;
use Innmind\Immutable\Str;

/**
 * @psalm-immutable
 */
final class Display
{
    private Output $output;

    private function __construct(Output $output)
    {
        $this->output = $output;
    }

    public function __invoke(Console $console, Str $data): Console
    {
        return ($this->output)($console, $data);
    }

    /**
     * @psalm-pure
     */
    public static function of(): self
    {
        return new self(Everything::of());
    }

    public function with(Output $output): self
    {
        return new self($output);
    }
}
