<?php
declare(strict_types = 1);

namespace Tests\Innmind\Async\HttpServer;

use Innmind\OperatingSystem\Factory;
use Innmind\Server\Control\Server\{
    Command,
    Signal,
};
use Innmind\Http\{
    Request,
    Response,
    Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class FunctionalTest extends TestCase
{
    private $os;
    private $server;

    public function setUp(): void
    {
        $this->os = Factory::build();
        $this->server = $this
            ->os
            ->control()
            ->processes()
            ->execute(
                Command::foreground('php fixtures/server.php')
                    ->withEnvironment('PATH', \getenv('PATH')),
            );
    }

    public function tearDown(): void
    {
        $this->server->pid()->match(
            fn($pid) => $this->os->control()->processes()->kill(
                $pid,
                Signal::kill,
            ),
            static fn() => null,
        );
    }

    public function testServerRespond()
    {
        $found = $this
            ->server
            ->output()
            ->chunks()
            ->find(static fn($pair) => $pair[0]->startsWith('HTTP server ready!'));
        $this->assertTrue($found->match(
            static fn() => true,
            static fn() => false,
        ));

        $response = $this
            ->os
            ->remote()
            ->http()(Request::of(
                Url::of('http://127.0.0.1:8080'),
                Method::get,
                ProtocolVersion::v10,
            ))
            ->match(
                static fn($success) => $success->response(),
                static fn($error) => $error,
            );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->statusCode()->toInt());
        $this->assertSame('Hello world', $response->body()->toString());
    }
}
