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
use Innmind\IO\Sockets\Server as IOServer;
use Innmind\Immutable\{
    Sequence,
    Maybe,
    Str,
};

final class Server
{
    private IOServer|IOServer\Pool $servers;
    private InjectEnvironment $injectEnv;
    private ResponseSender $send;
    /** @var callable(ServerRequest, OperatingSystem): Response */
    private $handle;
    private Display $display;

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    private function __construct(
        IOServer|IOServer\Pool $servers,
        InjectEnvironment $injectEnv,
        ResponseSender $send,
        callable $handle,
        Display $display,
    ) {
        $this->servers = $servers->watch();
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

        $connections = $this
            ->servers
            ->accept()
            ->map(fn($connection) => Task::of(function($os) use ($connection) {
                $io = $connection
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
                        static fn() => $connection
                            ->unwrap()
                            ->close()
                            ->maybe(),
                    )
                    ->match(
                        static fn() => null, // response sent
                        static fn() => null, // failed to send response or close connection
                    );
            }));

        if ($connections instanceof Maybe) {
            $connections = $connections->toSequence();
        }

        $console = ($this->display)($console, Str::of("New connections: {$connections->size()}\n"));

        return $continuation
            ->carryWith($console)
            ->launch(Sequence::of(...$connections->toList()));
    }

    /**
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    public static function of(
        Clock $clock,
        IOServer|IOServer\Pool $servers,
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
}
