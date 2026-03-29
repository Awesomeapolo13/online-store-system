# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Online store system built with Symfony 7.3, Doctrine ORM 3.5, and PHP 8.4. The project is a **modular monolith** following DDD principles with CQRS pattern.

## Development Environment

This project runs in Docker. All commands should be executed through the Makefile.

### Initial Setup

1. Copy `.env.dist` to `.env` in both `.deployment/docker/` and project root
2. Build and start containers: `make dc_up_build`
3. Install dependencies and initialize: `make init` (runs composer install, migrations, and fixtures)

### Common Commands

**Docker:**
- `make dc_up` / `make dc_stop` / `make dc_down` - Manage containers
- `make app_bash` - Access PHP container shell

**Development:**
- `make com_i` - Install Composer dependencies
- `make test` - Run PHPUnit tests
- `make m_run` - Run database migrations
- `make fx_load` - Load fixtures

**Static Analysis:**
- `make stan` - Run PHPStan
- `make cs_check` / `make cs_fix` - Check/fix code style (PHP-CS-Fixer)
- `make deptrac` - Check layer and module dependencies

**Single Test Execution:**
```bash
make app_bash
php bin/phpunit tests/Path/To/SpecificTest.php
```

## Architecture

### Modular Monolith Structure

The project consists of independent modules, each implementing hexagonal architecture:
- `Shared` - Common kernel with shared infrastructure (buses, messaging)
- `Order` - Order management module
- `Notification` - Notification module

Each module has three layers:
```
src/{Module}/
├── Domain/          # Entities, ValueObjects, Events, Repository interfaces, Exceptions, Enums
├── Application/     # Commands, Queries, Events handlers, UseCases, Request/Response DTOs, Assemblers
└── Infrastructure/  # Controllers, Repository implementations, Database (ORM mappings, migrations)
```

### CQRS Pattern

Implemented via Symfony Messenger with three buses defined in `config/packages/messenger.yaml`:

| Bus | Purpose | Middleware |
|-----|---------|------------|
| `command.bus` | Write operations | `doctrine_transaction`, `validation` |
| `query.bus` | Read operations | `allow_no_handlers` |
| `event.bus` | Domain events | `allow_no_handlers`, `validation` |

**Interfaces** (in `App\Shared\*`):
- Commands: `App\Shared\Application\Command\CommandInterface` / `CommandHandlerInterface` / `CommandBusInterface`
- Queries: `App\Shared\Application\Query\QueryInterface` / `QueryHandlerInterface` / `QueryBusInterface`
- Events: `App\Shared\Domain\Event\EventInterface`, `App\Shared\Application\Event\EventHandlerInterface` / `EventBusInterface`

**Bus implementations:** `App\Shared\Infrastructure\Bus\*`

### Creating CQRS Handlers

Create a directory in the module's Application layer containing both message and handler:

**Command/Query Example** (`App\Order\Application\Command\AddProductFromCatalog\`):
```
AddProductFromCatalogCommand.php    # implements CommandInterface
AddProductFromCatalogHandler.php    # implements CommandHandlerInterface
```

**Event Example:**
- Domain event: `App\Order\Domain\Event\ProductAddedEvent` (implements `EventInterface`)
- Handler: `App\Order\Application\Event\ProductAdded\ProductAddedHandler` (implements `EventHandlerInterface`)

Handlers are auto-registered via `_instanceof` configuration in `config/services.yaml`.

### Creating Endpoints

1. Create controller: `App\{Module}\Infrastructure\Http\Controller\{Action}Controller` (invokable with `__invoke`)
2. Create request DTO: `App\{Module}\Application\Request\{Action}Request`
3. Create use case: `App\{Module}\Application\UseCase\{Action}UseCase` (invokable, orchestrates commands/queries)
4. For responses: create assembler in `App\{Module}\Application\Assembler\` and response DTO in `App\{Module}\Application\Response\`

### Request Validation

Validation uses `symfony/validator`. Rules are declared in `config/validator/validation.yaml`.

Custom constraints live in `App\{Module}\Infrastructure\Service\Validation\Constraint\{ParameterName}\` and contain two classes:
- A `Constraint` subclass (the annotation/attribute)
- A `ConstraintValidator` subclass (the logic)

### Serialization

Serialization uses `symfony/serializer`. Mapping is declared in `config/serializer/` using YAML files named after the fully-qualified class name with dots instead of backslashes (e.g., `App.Order.Application.Response.OrderResponse.yaml`).

### ORM Mappings

Doctrine ORM is used throughout. Mappings are XML-based, **not** annotations/attributes.

- **Entity mappings**: `src/{Module}/Infrastructure/Database/ORM/Entity/{EntityName}.orm.xml`
- **Value object mappings** (embeddables): `src/{Module}/Infrastructure/Database/ORM/ValueObject/{ValueObjectName}.orm.xml`
- **Migrations**: part of the `Shared` module at `src/Shared/Infrastructure/DatabaseMigrations/`. Generate with `php bin/console doctrine:migrations:diff`, then edit as needed.

### Async Processing

For async command/event handling, configure dedicated transports per queue in `messenger.yaml` using `AMQP_CONSUME_DSN`.

## Testing

Tests are in `tests/` with three categories: `Integration/`, `Unit/`, `Functional/`. Helper classes can be added to `tests/Tools/`.

**Database cleanup:** Tests that create database records must clean them up after completion.

Run all tests: `make test`
