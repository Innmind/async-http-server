# Async HTTP Server

[![Build Status](https://github.com/innmind/async-http-server/workflows/CI/badge.svg?branch=main)](https://github.com/innmind/async-http-server/actions?query=workflow%3ACI)
[![Type Coverage](https://shepherd.dev/github/innmind/async-http-server/coverage.svg)](https://shepherd.dev/github/innmind/async-http-server)

Experimental async HTTP server built on top of `Fiber`s.

## Installation

```sh
composer require innmind/async-http-server
```

## Usage

```php
# server.php
<?php
declare(strict_types=1);

require 'path/to/vendor/autoload.php';

use Innmind\Async\HttpServer\Main;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Http\Message\{
    ServerRequest,
    Response,
    StatusCode,
};
use Innmind\Filesystem\Name;
use Innmind\Url\Path;

new class extends Main {
    protected static function handle(ServerRequest $request, OperatingSystem $os): Response
    {
        return $os
            ->filesystem()
            ->mount(Path::of('somewhere/'))
            ->get(Name::of('some-file'))
            ->match(
                static fn($file) => new Response\Response(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    null,
                    $file->content(),
                ),
                static fn() => new Response\Response(
                    StatusCode::notFound,
                    $request->protocolVersion(),
                ),
            );
    }
};
```

You can run this server via the command `php server.php`. By default the server is exposed on the port `8080`.

This example will return the content of the file `somewhere/some-file` if it exists on the filesystem otherwise it will respond with a `404 not found`.

The asynchronicity of this program is handled by the `OperatingSystem` abstraction meaning you can write code _as if_ it was synchronous.

**Note**: you can run `php server.php --help` to see the options available to configure the server.
