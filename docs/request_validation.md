# Валидация входных запросов

Используется валидация symfony/validator. Правила валидации указываются в `config/validator/validation.yaml`.

Самописные ограничения реализуются в `App\ModuleName\Infrastructure\Service\Validation\Constraint\ParameterName\`. 
Там создаем классы реализации `Symfony\Component\Validator\ConstraintValidator` и `Symfony\Component\Validator\Constraint`.
