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
use Innmind\Mantle\{
    Forerunner,
    Suspend\TimeFrame,
};
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    Environment as HttpEnv,
};
use Innmind\Url\Authority\Port;
use Innmind\Socket;
use Innmind\Stream\Streams;
use Innmind\Immutable\{
    Sequence,
    Str,
};

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
        serve --port= --time-frame= --no-output

        Start an HTTP server

        --port is the port on which to expose the server (default: 8080)
        --time-frame is the amount of time (in milliseconds) allowed for a request before suspending (default: 100)
        USAGE;
    }

    /**
     * @param Sequence<Socket\Server> $servers
     */
    private function serve(Console $console, Sequence $servers): Console
    {
        $source = Server::of(
            $this->os,
            match ($this->os instanceof OperatingSystem\Unix) {
                true => $this->os->config()->streamCapabilities(),
                false => Streams::fromAmbientAuthority(),
            },
            $servers,
            InjectEnvironment::of(new HttpEnv($console->variables())),
            $this->handle,
        );
        $forerunner = Forerunner::of(
            $this->os->clock(),
            TimeFrame::of(
                $this->os->clock(),
                ElapsedPeriod::of(
                    $console
                        ->options()
                        ->maybe('time-frame')
                        ->filter(\is_numeric(...))
                        ->match(
                            static fn($ms) => (int) $ms,
                            static fn() => 100,
                        ),
                ),
            ),
        );

        if ($console->options()->contains('no-output')) {
            $source = $source->withOutput(Nothing::of());
        } else {
            $console = $console->output(Str::of("HTTP server ready!\n"));
        }

        return $forerunner(
            $console,
            $source,
        );
    }
}
