## Обработка ошибок

Фильтрация ошибок происходит в миддлваре `App\Shared\Infrastructure\Http\Middleware\ExceptionFormatterMiddleware::onKernelException`, 
который отлавливает событие `Symfony\Component\HttpKernel\Event\ExceptionEvent` и формирует структуру ответа для разного типа ошибок.
