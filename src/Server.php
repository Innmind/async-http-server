<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Mantle\{
    Source,
    Task,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Async\OperatingSystem\Factory;
use Innmind\TimeContinuum\ElapsedPeriod;
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
    Predicate\Instance,
};

final class Server implements Source
{
    private OperatingSystem $synchronous;
    private Capabilities $capabilities;
    private Factory $os;
    /** @var Sequence<Socket\Server> */
    private Sequence $servers;
    private ElapsedPeriod $timeout;
    private InjectEnvironment $injectEnv;
    private ResponseSender $send;
    /** @var callable(ServerRequest, OperatingSystem): Response */
    private $handle;

    /**
     * @param Sequence<Socket\Server> $servers
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    private function __construct(
        OperatingSystem $synchronous,
        Capabilities $capabilities,
        Sequence $servers,
        ElapsedPeriod $timeout,
        InjectEnvironment $injectEnv,
        callable $handle,
    ) {
        $this->synchronous = $synchronous;
        $this->capabilities = $capabilities;
        $this->os = Factory::of($synchronous);
        $this->servers = $servers;
        $this->timeout = $timeout;
        $this->injectEnv = $injectEnv;
        $this->send = new ResponseSender($synchronous->clock());
        $this->handle = $handle;
    }

    /**
     * @param Sequence<Socket\Server> $servers
     * @param callable(ServerRequest, OperatingSystem): Response $handle
     */
    public static function of(
        OperatingSystem $synchronous,
        Capabilities $capabilities,
        Sequence $servers,
        ElapsedPeriod $timeout,
        InjectEnvironment $injectEnv,
        callable $handle,
    ): self {
        return new self(
            $synchronous,
            $capabilities,
            $servers,
            $timeout,
            $injectEnv,
            $handle,
        );
    }

    public function emerge(mixed $carry, Sequence $active): array
    {
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
            ->watch(match ($active->size()) {
                0 => null,
                default => $this->timeout,
            });
        $watch = $this->servers->reduce(
            $watch,
            static fn(Watch $watch, $server) => $watch->forRead($server),
        );

        return $watch();
    }
}
