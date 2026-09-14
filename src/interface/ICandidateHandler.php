<?php

namespace madong\workflow\interface;

use madong\workflow\context\ExecutionContext;

interface ICandidateHandler
{
    /**
     * @return string[] 候选人ID列表
     */
    public function getCandidates(ExecutionContext $ctx): array;
}
