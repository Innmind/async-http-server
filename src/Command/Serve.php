<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer\Command;

use Innmind\Async\HttpServer\{
    Server,
    Open,
    InjectEnvironment,
    Display\Nothing,
};
use Innmind\CLI\{
    Command,
    Console,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Mantle\Forerunner;
use Innmind\Http\{
    ServerRequest,
    Response,
    ServerRequest\Environment as HttpEnv,
};
use Innmind\Url\Authority\Port;
use Innmind\IO\Sockets\Server as IOServer;
use Innmind\Immutable\Str;

final class Serve implements Command
{
    private OperatingSystem $os;
    /** @var callable(ServerRequest, OperatingSystem): Response */
    private $handle;

    /**
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    private function __construct(
        OperatingSystem $os,
        callable $handle,
    ) {
        $this->os = $os;
        $this->handle = $handle;
    }

    public function __invoke(Console $console): Console
    {
        $port = $console
            ->options()
            ->maybe('port')
            ->filter(\is_numeric(...))
            ->match(
                static fn($port) => (int) $port,
                static fn() => 8080,
            );

        return Open::of(Port::of($port))($this->os)->match(
            fn($servers) => $this->serve($console, $servers),
            static fn() => $console
                ->error(Str::of("Failed to open sockets\n"))
                ->exit(1),
        );
    }

    /**
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    public static function of(
        OperatingSystem $os,
        callable $handle,
    ): self {
        return new self($os, $handle);
    }

    /**
     * @psalm-mutation-free
     */
    public function usage(): string
    {
        return <<<USAGE
        serve --port= --no-output

        Start an HTTP server

        --port is the port on which to expose the server (default: 8080)
        USAGE;
    }

    private function serve(Console $console, IOServer|IOServer\Pool $servers): Console
    {
        $source = Server::of(
            $this->os->clock(),
            $servers,
            InjectEnvironment::of(HttpEnv::of($console->variables())),
            $this->handle,
        );
        $forerunner = Forerunner::of($this->os);

        if ($console->options()->contains('no-output')) {
            $source = $source->withOutput(Nothing::of());
        } else {
            $console = $console->output(Str::of("HTTP server ready!\n"));
        }

        return $forerunner($console, $source);
    }
}
