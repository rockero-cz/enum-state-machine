<?php

namespace Rockero\EnumStateMachine;

use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;

abstract class StateMachine
{
    public function __construct(
        protected Model $model,
        protected string $attribute,
        protected BackedEnum $state,
    ) {
    }

    public static function attribute(Model $model, string $attribute = 'state'): Attribute
    {
        return Attribute::make(fn ($state) => new static($model, $attribute, self::castEnum($state)))->withoutObjectCaching();
    }

    private static function castEnum(BackedEnum|string $enum): BackedEnum
    {
        return $enum instanceof BackedEnum ? $enum : static::$stateClass::from($enum);
    }

    /**
     * Execute a transition to the given state.
     */
    public function transitionTo(BackedEnum $newState): void
    {
        if (!$this->isTransitionAllowed($newState)) {
            throw new Exception('Cannot transition to state ' . $newState->name);
        }

        $this->handleTransition($newState);
    }

    /**
     * Determine whether the transition is allowed.
     */
    public function isTransitionAllowed(BackedEnum $newState): bool
    {
        $transition = $this->findTransition($newState);
        $transitionClass = $transition?->transitionClass;

        if (!$transition) {
            return false;
        }

        if ($transitionClass && !(new $transitionClass($this->model))->isTransitionAllowed()) {
            return false;
        }

        return true;
    }

    public function get(): BackedEnum
    {
        return $this->state;
    }

    public function equals(BackedEnum $state): bool
    {
        return $this->state === $state;
    }

    protected function handleTransition(BackedEnum $newState): void
    {
        if ($handler = $this->findTransition($newState)->transitionClass) {
            (new $handler)($this->model);

            return;
        }

        $this->model->{$this->attribute} = $newState;

        $this->model->save();
    }

    protected function findTransition(BackedEnum $newState): ?AllowTransition
    {
        return $this->allowedTransitions()->first(function (AllowTransition $allowedTransitions) use ($newState) {
            return in_array("{$this->value}-{$newState->value}", $allowedTransitions->transitionKeys());
        });
    }

    protected function allowedTransitions(): Collection
    {
        return collect((new ReflectionClass(static::class))->getAttributes(AllowTransition::class))->map->newInstance();
    }

    public function __get($key)
    {
        if ($key === 'value') {
            return $this->state->value;
        }

        if ($key === 'name') {
            return $this->state->name;
        }

        throw new Exception('Unable to access undefined property on '.__CLASS__.': '.$key);
    }
}
