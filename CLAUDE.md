# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Online store system built with Symfony 7.3, Doctrine ORM 3.5, and PHP 8.4. The project follows a CQRS (Command Query Responsibility Segregation) architecture pattern with clean separation between Domain, Application, and Infrastructure layers.

## Development Environment

This project runs in Docker. All commands should be executed through the Makefile.

### Initial Setup

1. Copy `.env.dist` to `.env` in both `.deployment/docker/` and project root
2. Build and start containers: `make dc_up_build`
3. Install dependencies and initialize: `make init` (runs composer install, migrations, and fixtures)

### Common Commands

**Docker Management:**
- `make dc_up` - Start containers
- `make dc_stop` - Stop containers
- `make dc_down` - Remove containers, volumes, and images
- `make dc_logs` - View container logs
- `make app_bash` - Access PHP container shell

**Development:**
- `make com_i` - Install Composer dependencies
- `make com_r` - Require new Composer package
- `make test` - Run PHPUnit tests
- `make cache` - Clear Symfony cache
- `make m_run` - Run database migrations
- `make fx_load` - Load fixtures

**Single Test Execution:**
Access the container and run:
```bash
make app_bash
php bin/phpunit tests/Path/To/SpecificTest.php
```

## Architecture

### CQRS Pattern

This codebase implements CQRS using Symfony Messenger as the bus infrastructure. There are three types of buses:

**Command Bus** (`command.bus` in messenger.yaml)
- Handles write operations (state changes)
- Uses `doctrine_transaction` middleware for automatic transaction management
- Interface: `App\Application\Command\CommandBusInterface`
- Implementation: `App\Infrastructure\Bus\CommandBus`

**Query Bus** (`query.bus` in messenger.yaml)
- Handles read operations (no state changes)
- Interface: `App\Application\Query\QueryBusInterface`
- Implementation: `App\Infrastructure\Bus\QueryBus`

**Event Bus** (`event.bus` in messenger.yaml)
- Handles domain events
- Uses `allow_no_handlers` middleware
- Interface: `App\Application\Event\EventBusInterface`
- Implementation: `App\Infrastructure\Bus\EventBus`

### Layer Structure

**Domain Layer** (`src/Domain/`)
- `Entity/` - Domain entities
- `Event/` - Domain events implementing `EventInterface`
- `Repository/` - Repository interfaces
- `ValueObject/` - Value objects

**Application Layer** (`src/Application/`)
- `Command/` - Commands and command handlers
- `Query/` - Queries and query handlers
- `Event/` - Event handlers
- `UseCase/` - Use case orchestration

**Infrastructure Layer** (`src/Infrastructure/`)
- `Bus/` - Messenger bus implementations (CommandBus, QueryBus, EventBus)
- `Repository/` - Concrete repository implementations
- `Database/ORM/` - Doctrine mappings
- `Database/Migrations/` - Database migrations
- `Http/Controller/` - HTTP controllers

### Creating CQRS Handlers

For each new handler, create a directory in the appropriate location (Command, Query, or Event) containing both the message and handler:

**Command Example:**
```
src/Application/Command/AddProductFromCatalog/
├── AddProductFromCatalogCommand.php    # implements CommandInterface
└── AddProductFromCatalogHandler.php     # implements CommandHandlerInterface
```

**Query Example:**
```
src/Application/Query/GetProduct/
├── GetProductQuery.php                  # implements QueryInterface
└── GetProductHandler.php                # implements QueryHandlerInterface
```

**Event Example:**
Domain events are in `src/Domain/Event/`, handlers in `src/Application/Event/`:
```
src/Domain/Event/ProductAdded/
└── ProductAddedEvent.php                # implements EventInterface

src/Application/Event/ProductAdded/
└── ProductAddedHandler.php              # implements EventHandlerInterface
```

All handlers are auto-registered via Symfony's autoconfiguration (see `config/services.yaml`).

### Configuration

- `config/services.yaml` - Auto-wiring and auto-configuration for all services in `src/`
- `config/packages/messenger.yaml` - Bus configuration with three separate buses
- `config/packages/doctrine.yaml` - ORM and database configuration

## Testing

Tests are located in `tests/` and run via PHPUnit 9.5 with Symfony Bridge.

Run all tests: `make test`

PHPUnit configuration: `phpunit.xml.dist`
