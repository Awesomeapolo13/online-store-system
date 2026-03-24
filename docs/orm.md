# ORM

В проекте используется Doctrine ORM.

## Сущности и объекты значения

Все сущности расположены в директории `App\Module\Domain\Entity` каждого конкретного модуля. 
Для маппинга используются `EntityName.orm.xml` файлы, размещенные на слое инфраструктуры, например `App\Order\Infrastructure\Database\ORM\Entity\Example.orm.xml`.

Объекты значения являются embeddable объектами и имеют свои собственный отдельные файлы маппинга из `App\Module\Domain\ValueObject\*` в `App\Order\Infrastructure\Database\ORM\ValueObject\*`

## Миграции

Миграции являются частью модуля Shared, расположены в `App\Shared\Infrastructure\DatabaseMigrations\*`.
Они представляют собой стандартные миграции Doctrine, созданные командой  `php bin/console doctrine:migrations:diff` и немного отредактированные по необходимости.
