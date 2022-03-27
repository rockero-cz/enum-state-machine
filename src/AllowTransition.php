<?php

namespace Rockero\EnumStateMachine;

use Attribute;
use BackedEnum;
use Illuminate\Support\Arr;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AllowTransition
{
    public function __construct(
        public BackedEnum | array $from,
        public BackedEnum $to,
        public ?string $transitionClass = null,
    ) {
    }

    public function transitionKeys(): array
    {
        return array_map(fn ($from) => "{$from->value}-{$this->to->value}", Arr::wrap($this->from));
    }
}
