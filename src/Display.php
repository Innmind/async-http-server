<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Async\HttpServer\Display\{
    Output,
    Everything,
};
use Innmind\CLI\{
    Console,
    Environment,
};
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

    /**
     * @template T
     *
     * @param T $console
     *
     * @return T
     */
    public function __invoke(mixed $console, Str $data): mixed
    {
        /** @var T */
        return match ($console instanceof Environment || $console instanceof Console) {
            true => ($this->output)($console, $data),
            false => $console,
        };
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
