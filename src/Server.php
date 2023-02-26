<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Async\HttpServer\Display\Output;
use Innmind\Mantle\{
    Source,
    Task,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Async\OperatingSystem\Factory;
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\Filesystem\File\Content;
use Innmind\HttpParser\{
    Request\Parse,
    ServerRequest\Transform,
    ServerRequest\DecodeCookie,
    ServerRequest\DecodeQuery,
    ServerRequest\DecodeForm,
};
use Innmind\Http\{
    Message\ServerRequest,
    Message\Response,
    Message\StatusCode,
    ProtocolVersion,
};
use Innmind\Async\Socket\Server\Connection\Async;
use Innmind\Async\Stream\Streams;
use Innmind\IO\IO;
use Innmind\Socket;
use Innmind\Stream\{
    Watch,
    Watch\Ready,
    Capabilities,
};
use Innmind\Immutable\{
    Sequence,
    Set,
    Maybe,
    Str,
    Predicate\Instance,
};

final class Server implements Source
{
    private OperatingSystem $synchronous;
    private Capabilities $capabilities;
    private Factory $os;
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
        OperatingSystem $synchronous,
        Capabilities $capabilities,
        Factory $os,
        Sequence $servers,
        InjectEnvironment $injectEnv,
        ResponseSender $send,
        callable $handle,
        Display $display,
    ) {
        $this->synchronous = $synchronous;
        $this->capabilities = $capabilities;
        $this->os = $os;
        $this->servers = $servers;
        $this->injectEnv = $injectEnv;
        $this->send = $send;
        $this->handle = $handle;
        $this->display = $display;
    }

    /**
     * @param Sequence<Socket\Server> $servers
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    public static function of(
        OperatingSystem $synchronous,
        Capabilities $capabilities,
        Sequence $servers,
        InjectEnvironment $injectEnv,
        callable $handle,
    ): self {
        return new self(
            $synchronous,
            $capabilities,
            Factory::of($synchronous),
            $servers,
            $injectEnv,
            new ResponseSender($synchronous->clock()),
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
            $this->synchronous,
            $this->capabilities,
            $this->os,
            $this->servers,
            $this->injectEnv,
            $this->send,
            $this->handle,
            $this->display->with($output),
        );
    }

    public function emerge(mixed $carry, Sequence $active): array
    {
        $carry = ($this->display)($carry, Str::of("Connections still active: {$active->size()}\n"));

        $ready = $this->watch($active)->match(
            static fn($ready) => $ready->toRead(),
            static fn() => Set::of(),
        );
        /** @psalm-suppress InvalidArgument Due to empty Set */
        $connections = $ready
            ->keep(Instance::of(Socket\Server::class))
            ->flatMap(static fn($server) => $server->accept()->match(
                static fn($connection) => Set::of($connection),
                static fn() => Set::of(),
            ))
            ->map(fn($connection) => Task::of(function($suspend) use ($connection) {
                $os = $this->os->build($suspend);
                $io = IO::of($os->sockets()->watch(...));

                $connection = Async::of($connection, $suspend);
                $capabilities = Streams::of(
                    $this->capabilities,
                    $suspend,
                    $os->clock(),
                );

                $chunks = $io
                    ->readable()
                    ->wrap($connection)
                    ->toEncoding('ASCII')
                    ->watch()
                    ->chunks(8192);

                $parse = Parse::of(
                    $capabilities,
                    $os->clock(),
                );

                $_ = $parse($chunks)
                    ->map(Transform::of())
                    ->map(DecodeCookie::of())
                    ->map(DecodeQuery::of())
                    ->map(DecodeForm::of())
                    ->map($this->injectEnv)
                    ->map(function($request) use ($os) {
                        try {
                            return ($this->handle)($request, $os);
                        } catch (\Throwable $e) {
                            return new Response\Response(
                                StatusCode::internalServerError,
                                $request->protocolVersion(),
                            );
                        }
                    })
                    ->otherwise(static fn() => Maybe::just(new Response\Response( // failed to parse the request
                        StatusCode::badRequest,
                        ProtocolVersion::v10,
                        null,
                        Content\Lines::ofContent('Request doesn\'t respect HTTP protocol'),
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
        $carry = ($this->display)($carry, Str::of("New connections: {$connections->size()}\n"));

        return [$carry, Sequence::of(...$connections->toList())];
    }

    public function active(): bool
    {
        return true;
    }

    /**
     * @param Sequence<Task> $active
     *
     * @return Maybe<Ready>
     */
    private function watch(Sequence $active): Maybe
    {
        $watch = $this
            ->synchronous
            ->sockets()
            ->watch(match ($active->empty()) {
                true => null,
                false => ElapsedPeriod::of(0), // use polling to avoid blocking the tasks
            });
        $watch = $this->servers->reduce(
            $watch,
            static fn(Watch $watch, $server) => $watch->forRead($server),
        );

        return $watch();
    }
}
