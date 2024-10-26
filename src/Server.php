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
    private Open $open;
    private IOServer|IOServer\Pool|null $servers = null;
    private InjectEnvironment $injectEnv;
    private Encode $encode;
    /** @var callable(ServerRequest, OperatingSystem): Response */
    private $handle;
    private Display $display;

    /**
     * @psalm-mutation-free
     *
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    private function __construct(
        Open $open,
        InjectEnvironment $injectEnv,
        Encode $encode,
        callable $handle,
        Display $display,
    ) {
        $this->open = $open;
        $this->injectEnv = $injectEnv;
        $this->encode = $encode;
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
        if (\is_null($this->servers)) {
            $this->servers = ($this->open)($os)->match(
                static fn($servers) => $servers->watch(),
                static fn() => null,
            );

            if (!\is_null($this->servers)) {
                $console = ($this->display)(
                    $console,
                    Str::of("HTTP server ready!\n"),
                );
            }
        }

        if (\is_null($this->servers)) {
            return $continuation
                ->carryWith(
                    $console
                        ->error(Str::of("Failed to open sockets\n"))
                        ->exit(1),
                )
                ->terminate();
        }

        $console = ($this->display)($console, Str::of("Pending connections...\n"));

        $injectEnv = $this->injectEnv;
        $handle = $this->handle;
        $encode = $this->encode;

        $connections = $this
            ->servers
            ->accept()
            ->map(static fn($connection) => Task::of(static function($os) use (
                $connection,
                $injectEnv,
                $handle,
                $encode,
            ) {
                $io = $connection
                    ->toEncoding(Str\Encoding::ascii)
                    ->watch();

                $parse = Parse::default($os->clock());

                $_ = $parse($io)
                    ->map(Transform::of())
                    ->map(DecodeCookie::of())
                    ->map(DecodeQuery::of())
                    ->map(DecodeForm::of())
                    ->map($injectEnv)
                    ->map(static function($request) use ($os, $handle) {
                        try {
                            return $handle($request, $os);
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
                    ->flatMap(static fn($response) => $connection->send($encode($response)))
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
            ->launch($connections->memoize());
    }

    /**
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    public static function of(
        Clock $clock,
        Open $open,
        InjectEnvironment $injectEnv,
        callable $handle,
    ): self {
        return new self(
            $open,
            $injectEnv,
            new Encode($clock),
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
            $this->open,
            $this->injectEnv,
            $this->encode,
            $this->handle,
            $this->display->with($output),
        );
    }
}
