<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\TimeContinuum\Clock;
use Innmind\Socket\Server\Connection;
use Innmind\Http\{
    Response,
    Header\Date,
};
use Innmind\Stream\{
    FailedToWriteToStream,
    DataPartiallyWritten,
};
use Innmind\Immutable\{
    Maybe,
    Either,
    Str,
};

/**
 * @internal
 */
final class ResponseSender
{
    private const EOL = "\r\n";

    private Clock $clock;

    public function __construct(Clock $clock)
    {
        $this->clock = $clock;
    }

    /**
     * @return Maybe<Connection>
     */
    public function __invoke(
        Connection $connection,
        Response $response,
    ): Maybe {
        $headers = $response->headers();
        $headers = $headers
            ->get('date')
            ->match(
                static fn() => $headers,
                fn() => ($headers)(Date::of($this->clock->now())),
            );

        $firstLine = \sprintf(
            'HTTP/%s %s %s',
            $response->protocolVersion()->toString(),
            $response->statusCode()->toString(),
            $response->statusCode()->reasonPhrase(),
        );
        $connection = $connection->write(Str::of($firstLine)->append(self::EOL));
        /**
         * @psalm-suppress ArgumentTypeCoercion Due to the reduce
         * @var Either<FailedToWriteToStream|DataPartiallyWritten, Connection>
         */
        $connection = $headers->reduce(
            $connection,
            static fn(Either $either, $header) => $either->flatMap(
                static fn(Connection $connection) => $connection->write(Str::of($header->toString())->append(self::EOL)),
            ),
        );
        $connection = $connection->flatMap(
            static fn(Connection $connection) => $connection->write(Str::of(self::EOL)),
        );

        /**
         * @psalm-suppress MixedArgumentTypeCoercion Due to the reduce
         * @var Maybe<Connection>
         */
        return $response
            ->body()
            ->chunks()
            ->add(Str::of(self::EOL))
            ->add(Str::of(self::EOL))
            ->reduce(
                $connection,
                static fn(Either $either, $chunk) => $either->flatMap(
                    static fn(Connection $connection) => $connection->write($chunk),
                ),
            )
            ->maybe();
    }
}
