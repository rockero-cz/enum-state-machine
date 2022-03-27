<?php

namespace Rockero\EnumStateMachine;

abstract class Transition
{
    public function isTransitionAllowed(): bool
    {
        return true;
    }
}
