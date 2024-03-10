<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\IO\Sockets\Server;
use Innmind\Socket\Internet\Transport;
use Innmind\IP\{
    IP,
    IPv4,
};
use Innmind\Url\Authority\Port;
use Innmind\Immutable\{
    Sequence,
    Maybe,
};

final class Open
{
    /** @var Sequence<array{Port, IP, Transport}> */
    private Sequence $addresses;

    /**
     * @param Sequence<array{Port, IP, Transport}> $addresses
     */
    private function __construct(Sequence $addresses)
    {
        $this->addresses = $addresses;
    }

    /**
     * @return Maybe<Server|Server\Pool>
     */
    public function __invoke(OperatingSystem $os): Maybe
    {
        /**
         * @psalm-suppress NamedArgumentNotAllowed
         * @var Maybe<Server|Server\Pool>
         */
        return $this
            ->addresses
            ->map(static fn($address) => $os->ports()->open(
                $address[2],
                $address[1],
                $address[0],
            ))
            ->match(
                static fn($server, $rest) => Maybe::all($server, ...$rest->toList())->map(
                    static fn(Server $server, Server ...$servers) => Sequence::of(...$servers)->reduce(
                        $server,
                        static fn(Server|Server\Pool $pool, $server) => $pool->with($server),
                    ),
                ),
                static fn() => Maybe::nothing(),
            );
    }

    public static function of(
        Port $port,
        IP $ip = null,
        Transport $transport = null,
    ): self {
        return (new self(Sequence::of()))->and($port, $ip, $transport);
    }

    public function and(
        Port $port,
        IP $ip = null,
        Transport $transport = null,
    ): self {
        return new self(($this->addresses)([
            $port,
            $ip ?? IPv4::localhost(),
            $transport ?? Transport::tcp(),
        ]));
    }
}
