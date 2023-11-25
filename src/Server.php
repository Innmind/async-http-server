<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Async\HttpServer\Display\Output;
use Innmind\CLI\Console;
use Innmind\Mantle\{
    Source\Continuation,
    Task,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\TimeContinuum\Clock;
use Innmind\Filesystem\File\Content;
use Innmind\HttpParser\{
    Request\Parse,
    ServerRequest\Transform,
    ServerRequest\DecodeCookie,
    ServerRequest\DecodeQuery,
    ServerRequest\DecodeForm,
};
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
    ProtocolVersion,
};
use Innmind\IO\IO;
use Innmind\Socket;
use Innmind\Stream\{
    Watch,
    Watch\Ready,
};
use Innmind\Immutable\{
    Sequence,
    Set,
    Maybe,
    Str,
    Predicate\Instance,
};

final class Server
{
    /** @var Sequence<Socket\Server> */
    private Sequence $servers;
    private InjectEnvironment $injectEnv;
    private ResponseSender $send;
    /** @var callable(ServerRequest, OperatingSystem): Response */
    private $handle;
    private Display $display;

    /**
     * @psalm-mutation-free
     *
     * @param Sequence<Socket\Server> $servers
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    private function __construct(
        Sequence $servers,
        InjectEnvironment $injectEnv,
        ResponseSender $send,
        callable $handle,
        Display $display,
    ) {
        $this->servers = $servers;
        $this->injectEnv = $injectEnv;
        $this->send = $send;
        $this->handle = $handle;
        $this->display = $display;
    }

    /**
     * @param Continuation<Console, void> $continuation
     * @param Sequence<void> $terminated
     *
     * @return Continuation<Console, void>
     */
    public function __invoke(
        Console $console,
        OperatingSystem $os,
        Continuation $continuation,
        Sequence $terminated,
    ): Continuation {
        $console = ($this->display)($console, Str::of("Pending connections...\n"));

        $ready = $this->watch($os)->match(
            static fn($ready) => $ready->toRead(),
            static fn() => Set::of(),
        );
        $connections = $ready
            ->keep(Instance::of(Socket\Server::class))
            ->flatMap(static fn($server) => $server->accept()->match(
                static fn($connection) => Set::of($connection),
                static fn() => Set::of(),
            ))
            ->map(fn($connection) => Task::of(function($os) use ($connection) {
                $io = IO::of($os->sockets()->watch(...))
                    ->readable()
                    ->wrap($connection)
                    ->toEncoding(Str\Encoding::ascii)
                    ->watch();

                $parse = Parse::default($os->clock());

                $_ = $parse($io)
                    ->map(Transform::of())
                    ->map(DecodeCookie::of())
                    ->map(DecodeQuery::of())
                    ->map(DecodeForm::of())
                    ->map($this->injectEnv)
                    ->map(function($request) use ($os) {
                        try {
                            return ($this->handle)($request, $os);
                        } catch (\Throwable $e) {
                            return Response::of(
                                StatusCode::internalServerError,
                                $request->protocolVersion(),
                            );
                        }
                    })
                    ->otherwise(static fn() => Maybe::just(Response::of( // failed to parse the request
                        StatusCode::badRequest,
                        ProtocolVersion::v10,
                        null,
                        Content::ofString('Request doesn\'t respect HTTP protocol'),
                    )))
                    ->flatMap(fn($response) => ($this->send)($connection, $response))
                    ->flatMap(
                        static fn($connection) => $connection
                            ->close()
                            ->maybe(),
                    )
                    ->match(
                        static fn() => null, // response sent
                        static fn() => null, // failed to send response or close connection
                    );
            }));
        $console = ($this->display)($console, Str::of("New connections: {$connections->size()}\n"));

        return $continuation
            ->carryWith($console)
            ->launch(Sequence::of(...$connections->toList()));
    }

    /**
     * @param Sequence<Socket\Server> $servers
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    public static function of(
        Clock $clock,
        Sequence $servers,
        InjectEnvironment $injectEnv,
        callable $handle,
    ): self {
        return new self(
            $servers,
            $injectEnv,
            new ResponseSender($clock),
            $handle,
            Display::of(),
        );
    }

    /**
     * @psalm-mutation-free
     */
    public function withOutput(Output $output): self
    {
        return new self(
            $this->servers,
            $this->injectEnv,
            $this->send,
            $this->handle,
            $this->display->with($output),
        );
    }

    /**
     * @return Maybe<Ready>
     */
    private function watch(OperatingSystem $os): Maybe
    {
        $watch = $os
            ->sockets()
            ->watch();
        $watch = $this->servers->reduce(
            $watch,
            static fn(Watch $watch, $server) => $watch->forRead($server),
        );

        return $watch();
    }
}
