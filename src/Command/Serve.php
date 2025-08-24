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
use Innmind\IP\IP;

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
    public function usage(): string
    {
        return <<<USAGE
        serve --port= --no-output --allow-anyone

        Start an HTTP server

        --port is the port on which to expose the server (default: 8080)
        --allow-anyone to accept connections coming from outside the machine (default: only local connections are allowed)
        USAGE;
    }

    private function serve(Console $console, Open $open): Console
    {
        $source = Server::of(
            $this->os->clock(),
            $open,
            InjectEnvironment::of(HttpEnv::of($console->variables())),
            $this->handle,
        );
        $forerunner = Forerunner::of($this->os);

        if ($console->options()->contains('no-output')) {
            $source = $source->withOutput(Nothing::of());
        }

        return $forerunner($console, $source);
    }
}
