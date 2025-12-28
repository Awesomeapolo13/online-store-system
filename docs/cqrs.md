# CQRS

## Интерфейсы

Команды - App\Application\Command\CommandInterface
Обработчики команд - App\Application\Command\CommandHandlerInterface
Шина команд - App\Application\Command\CommandBusInterface


Запросы - App\Application\Query\QueryInterface
Обработчики запросов - App\Application\Command\CommandHandlerInterface
Шина запросов - App\Application\Command\CommandBusInterface

События - App\Domain\Event\EventInterface
Обработка событий - App\Application\Event\EventHandlerInterface
Шина событий - App\Application\Event\EventBusInterface

Реализация шин находится на инфраструктурном уровне в неймспейсе `App\Infrastructure\Bus`.

## Создание обработчика

Для каждого нового обработчика в соответствующей директории (Command, Query или Event) создается директория с именем. 
В этой директории создается файл команды и файл обработчика. Например, команда добавления продукта в корзину будет 
лежать в директории AddProductFromCatalog вместе с обработчиком:
- команда - `App\Application\Command\AddProductFromCatalog\AddProductFromCatalogCommand`;
- обработчик - `App\Application\Command\AddProductFromCatalog\AddProductFromCatalogHandler`.

Для доменных событий есть исключение, они находятся в неймспейсе `App\Domain\Event\EventInterface`, но в остальном
 логика та же самая.
