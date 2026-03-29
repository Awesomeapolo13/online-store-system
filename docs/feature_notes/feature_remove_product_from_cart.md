# Remove Product from Cart

The following functionality was implemented in the current task:

1. Added `POST /api/v1/cart/remove` HTTP endpoint (`RemoveProductFromCartController`) accepting `user_id`, `region`, and `sup_code` in the request body.
2. Created `RemoveProductFromCartRequest` DTO with serializer mapping (`sup_code` → `supCode`, `user_id` → `userId`) and validation rules (all fields required; `region` must be a valid `RegionCodeEnum` value; `sup_code` must be a 15-digit string).
3. Implemented `RemoveProductFromCartUseCase` orchestrating the operation: if no active cart exists, a new cart creation command is dispatched and a 400 error is returned asking to retry; otherwise the remove command is dispatched and the updated cart is returned.
4. Created `RemoveProductFromCartCommand` and `RemoveProductFromCartHandler` (CQRS command/handler pair): the handler finds the cart, applies an optimistic lock, finds the item by `sup_code`, removes it, recalculates the total cost, and saves the cart.
5. Added `lockOptimistic(Cart $cart): void` method to `CartRepositoryInterface` and implemented it in `DoctrineCartRepository` using `LockMode::OPTIMISTIC` from Doctrine DBAL.
6. Added functional tests (`RemoveProductFromCartControllerTest`) and unit tests (`RemoveProductFromCartHandlerTest`) covering the new functionality, with database cleanup after test execution.
7. Added ORM documentation (`docs/orm.md`) describing entity/value object mappings and migrations conventions.
8. Updated `docs/tests.md` to note that tests creating DB records must clean them up afterwards.
