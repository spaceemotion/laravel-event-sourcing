# Laravel Event Sourcing

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spaceemotion/laravel-event-sourcing.svg?style=flat-square)](https://packagist.org/packages/spaceemotion/laravel-event-sourcing)
[![Total Downloads](https://img.shields.io/packagist/dt/spaceemotion/laravel-event-sourcing.svg?style=flat-square)](https://packagist.org/packages/spaceemotion/laravel-event-sourcing)
[![Actions Status](https://github.com/spaceemotion/laravel-event-sourcing/workflows/CI/badge.svg)](https://github.com/spaceemotion/laravel-event-sourcing/actions)
[![CodeCov Status](https://codecov.io/gh/spaceemotion/laravel-event-sourcing/branch/master/graph/badge.svg)](https://codecov.io/gh/spaceemotion/laravel-event-sourcing)

Opinionated event sourcing framework for Laravel optimized for speed and type safety.

#### Functionality
- Uses generators for fetching and storing events for a small memory footprint
- Optimistic concurrent modification detection using event versioning
- Snapshot support for faster aggregate root load times
- Projections for read models using "native" Laravel events

#### Developer Experience
- Has type extensive hints for great IDE and static analysis support (no magic method calls)
- Integrated support for SQL and NoSQL event stores
- Flexible, but not bloated framework
- Unit-Test support via custom assertion helpers (`TestAggregateRoot` class)

#### Feature Support
Driver | Event Store | Snapshots
-------|-------------|----------
SQL | ✔ | ✔
DynamoDB | ✔ | ➖ _(storing works, loading still loads all events)_
In-Memory _(for unit tests)_ | ✔ | ❌

## Installation

You can install the package via composer:

```bash
composer require spaceemotion/laravel-event-sourcing
```

## Usage
### 1. Planning your domain
For this example, we want to create a simple to do list. A list has a title and items that have a name and can be
checked when they have been completed.

### 2. Custom identifier(s)
Every aggregate (root) is identified by a unique identifier. The package comes with a UUID base class
that can be extended to create custom ID types for better type safety (like not using a UserId in a TodoList domain).

```php
class ListId extends Uuid {}
```

### 2. Custom events
All changes in the system are driven by recorded events. Whenever a state change happens,
there's a corresponding event created by an aggregate root.

```php
class ListCreated implements Event
{
    private ListId $id;
    private string $title;

    public function __construct(ListId $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
    }

    public function getId(): ListId
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function serialize(): array
    {
        return ['id' => (string) $this->id, 'title' => $this->title];
    }

    public static function deserialize(array $payload): self
    {
        return new self(ListId::fromString($payload['id']), $payload['title']);
    }
}
```

Other events we'll use are:
- ItemAdded
- ItemCompleted

Let's just assume we've created them in a similar fashion.

### 3. Aggregate roots

Then we create the aggregate root that handles all the business requirements.

```php
class TodoList extends AggregateRoot
{
    private ListId $id;
    private array $items;

    // This method creates a new instance.
    // All constructors are closed off, so only manual construction
    // or rebuilding from events are allowed.
    public static function create(string $title): self
    {
        $instance = new self();
        $instance->record(new ListCreated(ListId::next(), $title));

        return $instance;
    }

    // This is an abstract method that all aggregate roots need to implement.
    // Which means that for creation events you have to add the aggregate ID.
    // (which should be the first event recorded upon new-ing an instance).
    public function getId(): ListId
    {
        return $this->id;
    }

    public function addItem(string $name): self
    {
        // Prevent adding duplicate items
        if (array_key_exists($name, $this->items)) {
            return $this;
        }

        return $this->record(new ItemAdded($name));
    }

    public function complete(string $name): self
    {
        // This checks business requirements - we cannot complete a non-existent item
        if (!array_key_exists($name, $this->items)) {
            throw new InvalidItemException($name);
        }

        return $this->record(new ItemCompleted($name));
    }

    // Each recorded event will be applied either at runtime, or when rebuilding from a list
    // of stored events during the rebuilding process. These change the internal state of
    // the aggregate root to check against business requirements.
    protected function getEventHandlers(): array
    {
        // Not all recorded events need to have an event handler
        return [
            ListCreated::class => function (ListCreated $event) {
                $this->id = $event->getId();
            },
            ItemAdded::class => function (ItemAdded $event) {
                $this->items[$event->name] = false;
            },
            ItemCompleted::class => function (ItemCompleted $event) {
                $this->items[$event->name] = true;
            },
        ];
    }
}
```

### 4. Controller actions
Example usage inside a possible TodoListController:

```php
function store(Request $request, EventStore $store)
{
    $list = TodoList::create($request->get('title'));
    $list->addItem('Read the documentation');

    $store->persist($list);

    return [
        'id' => (string) $list->getAggregateId(),
    ];
}
```

Example for a "create new item" action:

```php
function store(string $id, Request $request, EventStore $store)
{
    $list = TodoList::rebuild($store->retrieveAll(ListId::fromString($id)));
    $list->addItem($request->get('name'));

    $store->persist($list);
}
```

## Changelog

Please look at [the releases](https://github.com/spaceemotion/laravel-event-sourcing/releases) for more information on what has changed recently.

## Credits

- [spaceemotion](https://github.com/spaceemotion)
- [All Contributors](https://github.com/spaceemotion/laravel-event-sourcing/contributors)

## License

The ISC License (ISC). Please see [License File](LICENSE.md) for more information.
