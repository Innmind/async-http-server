<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Mantle\{
    Source,
    Task,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Async\OperatingSystem\Factory;
use Innmind\HttpParser\{
    Request\Parse,
    ServerRequest\Transform,
    ServerRequest\DecodeCookie,
    ServerRequest\DecodeQuery,
    ServerRequest\DecodeForm,
};
use Innmind\Async\Socket\Server\Connection\Async;
use Innmind\Async\Stream\Streams as AsyncStreams;
use Innmind\Socket;
use Innmind\Stream\{
    Watch,
    Streams,
};
use Innmind\Immutable\{
    Sequence,
    Set,
    Predicate\Instance,
};

final class Server implements Source
{
    private OperatingSystem $synchronous;
    private Factory $os;
    private Watch $watch;

    public function __construct(
        OperatingSystem $synchronous,
        Watch $watch,
    ) {
        $this->synchronous = $synchronous;
        $this->os = Factory::of($synchronous);
        $this->watch = $watch;
    }

    public function emerge(mixed $carry, Sequence $active): array
    {
        $ready = ($this->watch)()->match(
            static fn($ready) => $ready->toRead(),
            static fn() => Set::of(),
        );
        /** @psalm-suppress InvalidArgument Due to empty Set */
        $connections = $ready
            ->keep(Instance::of(Socket\Server::class))
            ->flatMap(static fn($server) => $server->accept()->match(
                static fn($connection) => Set::of($connection),
                static fn() => Set::of(),
            ))
            ->map(fn($connection) => Task::of(function($suspend) use ($connection) {
                $os = $this->os->build($suspend);
                $incoming = Incoming::of($os);
                $chunks = $incoming(Async::of($connection, $suspend));
                $parse = new Parse(
                    $os->clock(),
                    AsyncStreams::of(
                        Streams::fromAmbientAuthority(),
                        $suspend,
                        $os->clock(),
                    ),
                );
                $request = $parse($chunks)
                    ->map(new Transform)
                    ->map(new DecodeCookie)
                    ->map(new DecodeQuery)
                    ->map(new DecodeForm);
                // todo handle request
            }));

        return [$carry, Sequence::of(...$connections->toList())];
    }

    public function active(): bool
    {
        return true;
    }
}
