<?php

namespace madong\workflow\parser;

use madong\workflow\parser\lf\LfModel;

/**
 * 设计器解析器接口
 *
 * 定义解析器的基本结构
 */
interface DesignerParserInterface
{
    /**
     * 获取解析器名称
     */
    public function getName(): string;

    /**
     * 判断是否支持此格式
     */
    public function supports(array $data): bool;

    /**
     * 解析设计器图形数据
     *
     * @param array $data 设计器导出的原始数据
     * @return LfModel 统一的数据模型
     */
    public function parse(array $data): LfModel;
}
