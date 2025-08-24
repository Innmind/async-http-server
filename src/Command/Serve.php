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
    Command\Usage,
    Console,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Async\Scheduler;
use Innmind\Http\{
    ServerRequest,
    Response,
    ServerRequest\Environment as HttpEnv,
};
use Innmind\Url\Authority\Port;
use Innmind\IP\IP;
use Innmind\Immutable\Attempt;

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

    #[\Override]
    public function __invoke(Console $console): Attempt
    {
        $port = $console
            ->options()
            ->maybe('port')
            ->filter(\is_numeric(...))
            ->match(
                static fn($port) => (int) $port,
                static fn() => 8080,
            );

        return $this->serve($console, Open::of(
            Port::of($port),
            $console
                ->options()
                ->maybe('allow-anyone')
                ->match(
                    static fn() => IP::v4('0.0.0.0'),
                    static fn() => null,
                ),
        ));
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
    #[\Override]
    public function usage(): Usage
    {
        return Usage::parse(<<<USAGE
        serve --port= --no-output --allow-anyone

        Start an HTTP server

        --port is the port on which to expose the server (default: 8080)
        --allow-anyone to accept connections coming from outside the machine (default: only local connections are allowed)
        USAGE);
    }

    /**
     * @return Attempt<Console>
     */
    private function serve(Console $console, Open $open): Attempt
    {
        $server = Server::of(
            $this->os->clock(),
            $open,
            InjectEnvironment::of(HttpEnv::of($console->variables())),
            $this->handle,
        );

        if ($console->options()->contains('no-output')) {
            $server = $server->withOutput(Nothing::of());
        }

        return Scheduler::of($this->os)
            ->sink(Attempt::result($console))
            ->with($server);
    }
}
