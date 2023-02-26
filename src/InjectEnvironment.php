<?php
declare(strict_types = 1);

namespace Innmind\Async\HttpServer;

use Innmind\Http\Message\{
    ServerRequest,
    Environment,
};

final class InjectEnvironment
{
    private Environment $env;

    private function __construct(Environment $env)
    {
        $this->env = $env;
    }

    public function __invoke(ServerRequest $request): ServerRequest
    {
        return new ServerRequest\ServerRequest(
            $request->url(),
            $request->method(),
            $request->protocolVersion(),
            $request->headers(),
            $request->body(),
            $this->env,
            $request->cookies(),
            $request->query(),
            $request->form(),
            $request->files(),
        );
    }

    public static function of(Environment $env): self
    {
        return new self($env);
    }
}
