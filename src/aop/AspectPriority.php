<?php

namespace madong\workflow\aop;

final class AspectPriority
{
    const HIGHEST = 0;
    const HIGH    = 10;
    const NORMAL  = 50;
    const LOW     = 100;
    const LOWEST  = 200;

    private function __construct() {}
}
