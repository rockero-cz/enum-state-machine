<?php

namespace Rockero\EnumStateMachine;

use BackedEnum;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

abstract class StateMachine implements Castable
{
    protected static string $stateClass;
    protected BackedEnum $state;

    public function __construct(
        protected Model $model,
        protected string $attribute,
        BackedEnum|string $state,
    ) {
        $this->state = is_string($state) ? static::$stateClass::from($state) : $state;
    }

    public static function castUsing(array $arguments)
    {
        return new class(static::class) implements CastsAttributes
        {
            public function __construct(
                public string $class,
            ) {
            }

            public function get($model, $key, $value, $attributes)
            {
                return new $this->class($model, $key, $value);
            }
 
            public function set($model, $key, $value, $attributes)
            {
                return [$key => $value->value];
            }

            public function serialize($model, $key, $value, $attributes)
            {
                return $value->value;
            }
        };
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
