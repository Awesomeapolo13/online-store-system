# Feature: Add Cart Setup Endpoint

The following functionality was implemented in the current task:

1. Added a new `POST /api/v1/cart/setup` endpoint via `CartSetUpController` that accepts cart configuration parameters (`user_id`, `region`, `order_date`, `is_delivery`, `shop_id`).
2. Created `CartSetUpRequest` DTO with serializer mapping (snake_case to camelCase) in `config/serializer/Order.Application.Request.CartSetUpRequest.yaml`.
3. Added validation rules for `CartSetUpRequest` in `config/validator/validation.yaml`: `user_id` (not blank, positive), `region` (valid `RegionCodeEnum` value), `order_date` (not blank), `is_delivery` (not null).
4. Implemented `CartSetUpUseCase` that: queries the cart by user and region; if not found, dispatches `CreateNewCartCommand` and throws a 400 error asking the client to retry; validates that `shop_id` is provided when `is_delivery` is false; dispatches `CartSetUpCommand`; returns the updated cart response.
5. Created `CartSetUpCommand` and `CartSetUpHandler` — the handler finds the active cart, applies an optimistic lock, updates the cart's `Type`, `OrderDate`, and `shopNum`, then saves it.
6. Updated `OrderDate` value object: changed validation logic so the order date must be at least 2 hours from now (previously it only required the date to be in the future without any minimum offset).
7. Added optimistic locking (`lockOptimistic`) to `AddProductToCartHandler` to prevent concurrent modification issues (brought in line with other cart mutation handlers).
8. Added a terms of reference document `docs/terms_of_references/add_cart_setup_endpoint.md` describing the feature requirements and acceptance criteria.
9. Implemented unit tests for `OrderDate` and `Type` value objects (`OrderDateTest`, `TypeTest`).
10. Implemented integration tests for `CartSetUpHandler` and updated `AddProductToCartHandlerTest` to reflect the new optimistic lock behavior.
11. Implemented functional tests for `CartSetUpController` covering success, cart-not-found (auto-create), missing `shop_id` for pickup orders, and validation error scenarios.
