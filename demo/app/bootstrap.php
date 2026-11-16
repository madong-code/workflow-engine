<?php
/**
 * workflow demo · 极简引导文件
 *
 * - 提供 madong\workflow 的 PSR-4 自动加载（不依赖 composer install / vendor）
 * - 提供 demo 分层结构（app/engine、app/model、app/service、app/controller）的自动加载
 * - 提供装配好的引擎工厂（PDO 存储版，仿插件 EngineFactory）与建库入口
 *
 * 该文件被 public/index.php、app/controller/api.php、tests/reset-db.php 以 require_once 引入，幂等。
 */

declare(strict_types=1);

namespace madong\workflow\demo;

use madong\workflow\Engine;

if (!function_exists('madong\\workflow\\demo\\engine')) {

    // PSR-4 兜底加载：demo 分层目录 + 引擎包源码（vendor 已加载时引擎段静默跳过）
    spl_autoload_register(static function (string $class): void {
        $prefixes = [
            'madong\\workflow\\demo\\controller\\' => __DIR__ . '/controller/',
            'madong\\workflow\\demo\\service\\'    => __DIR__ . '/service/',
            'madong\\workflow\\demo\\engine\\'     => __DIR__ . '/engine/',
            'madong\\workflow\\demo\\model\\'      => __DIR__ . '/model/',
            // 引擎源码（packages/workflow/src）；放最后避免遮蔽 demo 分层
            'madong\\workflow\\'                   => __DIR__ . '/../../src/',
        ];
        foreach ($prefixes as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                    return;
                }
            }
        }
    }, true, true);

    /**
     * 引擎单例（每次进程复用）：装配细节见 engine/EngineFactory
     */
    function engine(): Engine
    {
        return \madong\workflow\demo\engine\EngineFactory::create();
    }

    /**
     * 确保库表与种子就绪（幂等，首个请求触发）
     */
    function ensureDatabase(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        \madong\workflow\demo\model\Database::ensureSchema();
        $done = true;
    }

    /**
     * 实体标准化：字符串 id + int 时间戳透传（bigint id 以字符串传输，避免 JS 精度丢失）
     */
    function norm_row(array $row): array
    {
        foreach ($row as $k => $v) {
            if (is_int($v) || (is_string($v) && preg_match('/^-?\d{10,}$/', $v) && str_contains($k, 'id'))) {
                $row[$k] = (string) $v;
            }
        }
        return $row;
    }
}
