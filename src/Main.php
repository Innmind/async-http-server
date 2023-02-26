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
use Innmind\Http\Message\{
    ServerRequest,
    Response,
};

abstract class Main extends Cli
{
    protected function main(Environment $env, OperatingSystem $os): Environment
    {
        $run = Commands::of(new Serve($os, $this->handle(...)));

        return $run($env);
    }

    /**
     * The handler is static to prevent sharing state between requests
     */
    abstract protected static function handle(
        ServerRequest $request,
        OperatingSystem $os,
    ): Response;
}
