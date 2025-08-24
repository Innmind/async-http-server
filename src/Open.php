<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\OperatingSystem\OperatingSystem;
use Innmind\IO\Sockets\{
    Servers\Server,
    Internet\Transport,
};
use Innmind\IP\{
    IP,
    IPv4,
};
use Innmind\Url\Authority\Port;
use Innmind\Immutable\{
    Sequence,
    Maybe,
    Predicate\Instance,
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
        /** @var Server|Server\Pool|null */
        $server = null;

        return $this
            ->addresses
            ->map(static fn($address) => $os->ports()->open(
                $address[2],
                $address[1],
                $address[0],
            ))
            ->sink($server)
            ->attempt(
                static fn($pool, $server) => $server->map(
                    static fn($server) => match (true) {
                        \is_null($pool) => $server,
                        $pool instanceof Server => $pool->pool($server),
                        default => $pool->with($server),
                    },
                ),
            )
            ->maybe()
            ->keep(
                Instance::of(Server::class)->or(
                    Instance::of(Server\Pool::class),
                ),
            );
    }

    public static function of(
        Port $port,
        ?IP $ip = null,
        ?Transport $transport = null,
    ): self {
        return (new self(Sequence::of()))->and($port, $ip, $transport);
    }

    public function and(
        Port $port,
        ?IP $ip = null,
        ?Transport $transport = null,
    ): self {
        return new self(($this->addresses)([
            $port,
            $ip ?? IPv4::localhost(),
            $transport ?? Transport::tcp(),
        ]));
    }
}
