# Async HTTP Server

[![CI](https://github.com/Innmind/async-http-server/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/Innmind/async-http-server/actions/workflows/ci.yml)
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
use Innmind\Http\{
    ServerRequest,
    Response,
    Response\StatusCode,
};
use Innmind\Filesystem\Name;
use Innmind\Url\Path;

new class extends Main {
    protected static function handle(ServerRequest $request, OperatingSystem $os): Response
    {
        return $os
            ->filesystem()
            ->mount(Path::of('somewhere/'))
            ->unwrap()
            ->get(Name::of('some-file'))
            ->match(
                static fn($file) => Response::of(
                    StatusCode::ok,
                    $request->protocolVersion(),
                    null,
                    $file->content(),
                ),
                static fn() => Response::of(
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

> [!NOTE]
> you can run `php server.php --help` to see the options available to configure the server.
