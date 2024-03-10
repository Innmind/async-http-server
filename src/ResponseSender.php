<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\TimeContinuum\Clock;
use Innmind\IO\Sockets\Client;
use Innmind\Http\{
    Response,
    Header\Date,
};
use Innmind\Immutable\{
    Maybe,
    Str,
    Sequence,
    SideEffect,
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
     * @return Maybe<SideEffect>
     */
    public function __invoke(
        Client $connection,
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
        /** @var Sequence<string> */
        $chunks = Sequence::of($firstLine);
        $chunks = $chunks
            ->append($headers->all()->map(
                static fn($header) => $header->toString(),
            ))
            ->add('')
            ->map(Str::of(...))
            ->map(static fn($line) => $line->append(self::EOL))
            ->append($response->body()->chunks())
            ->add(Str::of(self::EOL))
            ->add(Str::of(self::EOL));

        return $connection->send($chunks);
    }
}
