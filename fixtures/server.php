<?php
declare(strict_types = 1);

require __DIR__.'/../vendor/autoload.php';

use Innmind\Async\HttpServer\Main;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
    Headers,
    Header\ContentLength,
};
use Innmind\Filesystem\File\Content;

new class extends Main {
    protected static function handle(ServerRequest $request, OperatingSystem $os): Response
    {
        return Response::of(
            StatusCode::ok,
            $request->protocolVersion(),
            Headers::of(ContentLength::of(11)),
            Content::ofString('Hello world'),
        );
    }
};
