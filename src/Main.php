<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\CLI\{
    Environment,
    Main as Cli,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\Mantle\{
    Forerunner,
    Suspend\Strategy,
    Suspend\TimeFrame,
};
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

abstract class Main extends Cli
{
    protected function main(Environment $env, OperatingSystem $os): Environment
    {
        return $this->open($os, $env)($os)->match(
            fn($servers) => $this->serve($env, $os, $servers),
            static fn() => $env
                ->error(Str::of("Failed to open sockets\n"))
                ->exit(1),
        );
    }

    protected function open(OperatingSystem $os, Environment $env): Open
    {
        return Open::of(Port::of(8080));
    }

    /**
     * @return callable(): Strategy
     */
    protected function asyncStrategy(OperatingSystem $os, Environment $env): callable
    {
        return TimeFrame::of($os->clock(), ElapsedPeriod::of(100));
    }

    abstract protected function handle(
        ServerRequest $request,
        OperatingSystem $os,
    ): Response;

    /**
     * @param Sequence<Socket\Server> $servers
     */
    private function serve(
        Environment $env,
        OperatingSystem $os,
        Sequence $servers,
    ): Environment {
        $source = Server::of(
            $os,
            match ($os instanceof OperatingSystem\Unix) {
                true => $os->config()->streamCapabilities(),
                false => Streams::fromAmbientAuthority(),
            },
            $servers,
            ElapsedPeriod::of(1_000),
            InjectEnvironment::of(new HttpEnv($env->variables())),
            $this->handle(...),
        );
        $forerunner = Forerunner::of(
            $os->clock(),
            $this->asyncStrategy($os, $env),
        );

        return $forerunner(
            $env->output(Str::of("HTTP server ready!\n")),
            $source,
        );
    }
}
