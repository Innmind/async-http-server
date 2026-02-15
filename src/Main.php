<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Async\HttpServer\Command\Serve;
use Innmind\CLI\{
    Environment,
    Main as Cli,
    Commands,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Http\{
    ServerRequest,
    Response,
};
use Innmind\Immutable\{
    Attempt,
    Map,
};

abstract class Main extends Cli
{
    #[\Override]
    protected function main(Environment $env, OperatingSystem $os): Attempt
    {
        $run = Commands::of(Serve::of($os, static::handle(...)));

        return $run($env);
    }

    /**
     * The handler is static to prevent sharing state between requests
     *
     * @param Map<string, string> $env Environment variables
     */
    abstract protected static function handle(
        ServerRequest $request,
        OperatingSystem $os,
        Map $env,
    ): Response;
}
