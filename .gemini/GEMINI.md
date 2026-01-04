# FlightPHP/Core Project Instructions

## Overview
This is the main FlightPHP core library for building fast, simple, and extensible PHP web applications. It is dependency-free for core usage and supports PHP 7.4+.

## Project Guidelines
- PHP 7.4 must be supported. PHP 8 or greater also supported, but avoid PHP 8+ only features.
- Keep the core library dependency-free (no polyfills or interface-only repositories).
- All Flight projects are meant to be kept simple and fast. Performance is a priority.
- Flight is extensible and when implementing new features, consider how they can be added as plugins or extensions rather than bloating the core library.
- Any new features built into the core should be well-documented and tested.
- Any new features should be added with a focus on simplicity and performance, avoiding unnecessary complexity.
- This is not a Laravel, Yii, Code Igniter or Symfony clone. It is a simple, fast, and extensible framework that allows you to build applications quickly without the overhead of large frameworks.

## Development & Testing
- Run tests: `composer test` (uses phpunit/phpunit and spatie/phpunit-watcher)
- Run test server: `composer test-server` or `composer test-server-v2`
- Lint code: `composer lint` (uses phpstan/phpstan, level 6)
- Beautify code: `composer beautify` (uses squizlabs/php_codesniffer, PSR1)
- Check code style: `composer phpcs`
- Test coverage: `composer test-coverage`

## Coding Standards
- Follow PSR1 coding standards (enforced by PHPCS)
- Use strict comparisons (`===`, `!==`)
- PHPStan level 6 compliance
- Focus on PHP 7.4 compatibility (avoid PHP 8+ only features)
