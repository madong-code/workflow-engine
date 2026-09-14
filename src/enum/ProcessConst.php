<?php

namespace madong\workflow\enum;

final class ProcessConst
{
    // 提交类型
    public const SUBMIT_TYPE = 'submitType';

    // 表单数据前缀
    public const FORM_DATA_PREFIX = 'f_';

    // 会签相关
    public const COUNTERSIGN_PREFIX = 'cs_';
    public const OPERATOR_LIST      = 'operatorList';
    public const LOOP_COUNTER       = 'loopCounter';
    public const NR_OF_COMPLETED    = 'nrOfCompleted';
    public const COUNTERSIGN_DISAGREE_FLAG = 'cs_disagree';

    private function __construct() {}
}
