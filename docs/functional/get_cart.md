# Получение корзины

## Описание

1) Корзина пользователя получается посредством HTTP GET запроса `/api/v1/cart` с параметром user_id и region.
2) Корзина запрашивается через `App\Order\Application\Query\GetCart\GetCartQuery`.
3) Если у пользователя нет активной корзины, отправляется сообщение в очередь `cart.command.create`, пользователю возвращаем статус 202 с пустым телом.
4) Если корзина есть, то она передается в ассемблер `App\Order\Application\Assembler\CartAssembler::toResponse`.
