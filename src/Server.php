<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Async\HttpServer\Display\Output;
use Innmind\CLI\Console;
use Innmind\Async\{
    Scope\Continuation,
    Task\Discard,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Time\Clock;
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
use Innmind\IO\Sockets\Servers\Server as IOServer;
use Innmind\Immutable\{
    Attempt,
    Maybe,
    Str,
};

final class Server
{
    /**
     * @psalm-mutation-free
     *
     * @param \Closure(ServerRequest, OperatingSystem): Response $handle
     */
    private function __construct(
        private Open $open,
        private Encode $encode,
        private \Closure $handle,
        private Display $display,
        private IOServer|IOServer\Pool|null $servers = null,
    ) {
    }

    /**
     * @param Attempt<Console> $console
     * @param Continuation<Attempt<Console>> $continuation
     *
     * @return Continuation<Attempt<Console>>
     */
    public function __invoke(
        Attempt $console,
        OperatingSystem $os,
        Continuation $continuation,
    ): Continuation {
        $failed = $console->match(
            static fn() => false,
            static fn() => true,
        );

        if ($failed) {
            return $continuation->terminate();
        }

        if (\is_null($this->servers)) {
            $this->servers = ($this->open)($os)->match(
                static fn($servers) => $servers,
                static fn() => null,
            );

            if (!\is_null($this->servers)) {
                $console = $console->flatMap(
                    fn($console) => ($this->display)(
                        $console,
                        Str::of("HTTP server ready!\n"),
                    ),
                );
            }
        }

        if (\is_null($this->servers)) {
            return $continuation
                ->carryWith(
                    $console
                        ->map(static fn($console) => $console->exit(1))
                        ->flatMap(static fn($console) => $console->error(Str::of("Failed to open sockets\n"))),
                )
                ->terminate();
        }

        $console = $console->flatMap(
            fn($console) => ($this->display)(
                $console,
                Str::of("Pending connections...\n"),
            ),
        );

        $handle = $this->handle;
        $encode = $this->encode;

        $connections = $this
            ->servers
            ->accept()
            ->map(static fn($connection) => static function(OperatingSystem $os) use (
                $connection,
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
                    ->flatMap(
                        static fn($response) => $connection
                            ->sink($encode($response))
                            ->maybe(),
                    )
                    ->flatMap(
                        static fn() => $connection
                            ->close()
                            ->maybe(),
                    )
                    ->match(
                        static fn() => null, // response sent
                        static fn() => null, // failed to send response or close connection
                    );

                return Discard::result;
            });

        if ($connections instanceof Attempt) {
            $connections = $connections
                ->maybe()
                ->toSequence();
        }

        $console = $console->flatMap(
            fn($console) => ($this->display)(
                $console,
                Str::of("New connections: {$connections->size()}\n"),
            ),
        );

        return $continuation
            ->carryWith($console)
            ->schedule($connections->memoize());
    }

    /**
     * @param \Closure(ServerRequest, OperatingSystem): Response $handle
     */
    public static function of(
        Clock $clock,
        Open $open,
        \Closure $handle,
    ): self {
        return new self(
            $open,
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
            $this->encode,
            $this->handle,
            $this->display->with($output),
        );
    }
}
