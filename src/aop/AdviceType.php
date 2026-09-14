<?php

namespace madong\workflow\aop;

enum AdviceType: string
{
    case BEFORE       = 'before';
    case AFTER        = 'after';
    case AFTER_RETURN = 'afterReturn';
    case AFTER_THROW  = 'afterThrow';
    case AROUND       = 'around';
}
