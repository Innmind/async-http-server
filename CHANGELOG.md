# Changelog

## [Unreleased]

### Changed

- Requires `innmind/foundation:~1.9`
- Requires `innmind/http-parser:~3.0`

### Fixed

- PHP `8.4` deprecations

## 3.1.0 - 2024-10-27

### Added

- `--allow-anyone` option to allow connections from outside the machine

## 3.0.2 - 2024-10-26

### Changed

- Use `static` closures as much as possible to reduce the probability of creating circular references by capturing `$this` as it can lead to memory root buffer exhaustion.

## 3.0.1 - 2024-08-03

### Fixed

- The whole response body was loaded in memory
- The server was blocking while waiting new connections, preventing previous requests from being served

## 3.0.0 - 2024-03-10

### Changed

- Requires `innmind/operating-system:~5.0`
- Requires `innmind/io:~2.7`
- Requires `innmind/http-parser:~2.1`
- `Innmind\Async\HttpServer\Server::of()` now accept either a `Innmind\IO\Sockets\Server` or `Innmind\IO\Sockets\Server\Pool`

## 2.0.0 - 2023-11-25

### Changed

- Requires `innmind/mantle:~2.0`
- Requires `innmind/http-parser:~2.0`
- Requires `innmind/operating-system:~4.1`

### Removed

- `--time-frame` option

## 1.1.0 - 2023-09-24

### Added

- Support for `innmind/io:~2.0`

### Removed

- Support for PHP `8.1`
