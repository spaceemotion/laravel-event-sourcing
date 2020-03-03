<!--README_START-->

# Laravel Event Sourcing

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spaceemotion/laravel-event-sourcing.svg?style=flat-square)](https://packagist.org/packages/spaceemotion/laravel-event-sourcing)
[![Total Downloads](https://img.shields.io/packagist/dt/spaceemotion/laravel-event-sourcing.svg?style=flat-square)](https://packagist.org/packages/spaceemotion/laravel-event-sourcing)
[![Actions Status](https://github.com/spaceemotion/laravel-event-sourcing/workflows/CI/badge.svg)](https://github.com/spaceemotion/laravel-event-sourcing/actions)
[![CodeCov Status](https://codecov.io/gh/spaceemotion/laravel-event-sourcing/branch/master/graph/badge.svg)](https://codecov.io/gh/spaceemotion/laravel-event-sourcing)

Opiniated event sourcing framework for Laravel optimized for speed and type safety.

#### Functionality
- Uses generators for fetching and storing events for a small memory footprint
- Optimistic concurrent modification detection using event versioning
- Snapshot support for faster aggregate root load times

#### Developer Experience
- Has type extensive hints for great IDE (and static analysis) support
- Integrated support for SQL and NoSQL event stores
- Flexible, but not bloated framework

## Installation

You can install the package via composer:

```bash
composer require spaceemotion/laravel-event-sourcing
```

## Usage

```php
<?php
function store(Request $request, EventStore $store)
{
    $list = TodoListAggregate::forId(Uuid::new());
    $list->setTitle($request->get('title'));

    $store->persist($list);
}
```

### Changelog

Please look at [the releases](https://github.com/spaceemotion/laravel-event-sourcing/releases) for more information on what has changed recently.

## Credits

- [spaceemotion](https://github.com/spaceemotion)
- [All Contributors](https://github.com/spaceemotion/laravel-event-sourcing/contributors)

## License

The ISC License (ISC). Please see [License File](LICENSE.md) for more information.
