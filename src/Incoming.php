<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Socket\Server\Connection;
use Innmind\Immutable\{
    Sequence,
    Set,
    Str,
};

final class Incoming
{
    private OperatingSystem $os;

    private function __construct(OperatingSystem $os)
    {
        $this->os = $os;
    }

    /**
     * @return Sequence<Str>
     */
    public function __invoke(Connection $connection): Sequence
    {
        $watch = $this
            ->os
            ->sockets()
            ->watch()
            ->forRead($connection);

        return Sequence::lazy(static function() use ($watch, $connection) {
            do {
                /** @var Set<Connection> */
                $ready = $watch()->match(
                    static fn($ready) => $ready->toRead(),
                    static fn() => Set::of(),
                );

                /** @psalm-suppress InvalidArgument */
                yield from $ready
                    ->flatMap(static fn($connection) => $connection->read(8192)->match(
                        static fn($chunk) => Set::of($chunk),
                        static fn() => Set::of(),
                    ))
                    ->toList();
            } while ($ready->contains($connection));
        });
    }

    public static function of(OperatingSystem $os): self
    {
        return new self($os);
    }
}
