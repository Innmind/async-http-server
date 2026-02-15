<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Time\Clock;
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
        $now = $this->clock->now();
        $headers = $response->headers();
        $headers = $headers
            ->get('date')
            ->match(
                static fn() => $headers,
                static fn() => ($headers)(Date::of($now)),
            );

        $firstLine = \sprintf(
            'HTTP/%s %s %s',
            $response->protocolVersion()->toString(),
            $response->statusCode()->toString(),
            $response->statusCode()->reasonPhrase(),
        );

        return $response
            ->body()
            ->chunks()
            ->prepend(
                $headers
                    ->all()
                    ->map(static fn($header) => $header->toString())
                    ->prepend(Sequence::of($firstLine))
                    ->add('')
                    ->map(Str::of(...))
                    ->map(static fn($line) => $line->append(self::EOL)),
            )
            ->add(Str::of(self::EOL))
            ->add(Str::of(self::EOL));
    }
}
