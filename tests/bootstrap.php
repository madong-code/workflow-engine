<?php
// 使用主项目的 autoload — packages/workflow 通过 composer path repository 链接进来
// 同时也加载 packages/ingenious 的接口定义（主项目已全量加载）
require_once __DIR__ . '/../../../vendor/autoload.php';

defined('TESTING') || define('TESTING', true);
