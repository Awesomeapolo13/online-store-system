# CQRS

## Интерфейсы

Команды - `App\Shared\Application\Command\CommandInterface`
Обработчики команд - `App\Shared\Application\Command\CommandHandlerInterface`
Шина команд - `App\Shared\Application\Command\CommandBusInterface`

Запросы - `App\Shared\Application\Query\QueryInterface`
Обработчики запросов - `App\Shared\Application\Query\QueryHandlerInterface`
Шина запросов - `App\Shared\Application\Query\QueryBusInterface`

События - `App\Shared\Domain\Event\EventInterface`
Обработка событий - `App\Shared\Application\Event\EventHandlerInterface`
Шина событий - `App\Shared\Application\Event\EventBusInterface`

Реализация шин находится на инфраструктурном уровне в неймспейсе `App\Shared\Infrastructure\Bus`.

## Создание обработчика

Классы конкретных команды и обработчиков создаются в соответствующих модулях `App\Order\*`, `App\Notification\*` и прочих.

Для каждого нового обработчика в соответствующей директории (Command, Query или Event) создается директория с именем команды, запроса или события. 
В этой директории создается файл команды и файл обработчика. Например, команда добавления продукта в корзину будет 
лежать в директории AddProductFromCatalog вместе с обработчиком:
- команда - `App\Order\Application\Command\AddProductFromCatalog\AddProductFromCatalogCommand`;
- обработчик - `App\Order\Application\Command\AddProductFromCatalog\AddProductFromCatalogHandler`.

Для доменных событий есть исключение, они находятся в неймспейсе `App\Order\Domain\Event\EventInterface`, но в остальном
 логика та же самая.

## Транспорты

Для реализации асинхронной обработки следует использовать транспорт с AMQP_CONSUME_DSN symfony/messengers.
 Для каждой новой очереди создавать новый транспорт, чтобы на каждую очередь был свой потребитель.

Там где не требуется асинхронная обработка, отдельный транспорт создавать не нужно.
 Можно использовать шины команд и событий для их обработки.