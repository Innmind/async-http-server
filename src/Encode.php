<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\TimeContinuum\Clock;
use Innmind\Http\{
    Response,
    Header\Date,
};
use Innmind\Immutable\{
    Str,
    Sequence,
};

/**
 * @internal
 */
final class Encode
{
    private const EOL = "\r\n";

    private Clock $clock;

    public function __construct(Clock $clock)
    {
        $this->clock = $clock;
    }

    /**
     * @return Sequence<Str>
     */
    public function __invoke(Response $response): Sequence
    {
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

        return $chunks
            ->append($headers->all()->map(
                static fn($header) => $header->toString(),
            ))
            ->add('')
            ->map(Str::of(...))
            ->map(static fn($line) => $line->append(self::EOL))
            ->append($response->body()->chunks())
            ->add(Str::of(self::EOL))
            ->add(Str::of(self::EOL));
    }
}
