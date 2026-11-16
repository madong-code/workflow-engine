<?php
/**
 * workflow demo · 前端控制器（front-controller）
 *
 * 文档根目录设为 packages/workflow 根，因此：
 *   - /ui/*            设计器静态站点（ui/ 目录下的真实文件）
 *   - /                渲染宿主页 views/host.php
 *   - /api/*           转发到 src/api.php
 *
 * 为兼容 PHP 内置服务器（指定 router 后目录请求不会自动回退到静态文件），
 * 此处对“真实存在的静态文件”做安全 readfile；php-fpm 下通常由 Web 服务器直接托管，
 * 不会进入本脚本，故不影响性能。
 *
 * 运行方式见 demo/README.md：
 *   - 内置服务器：php -S 127.0.0.1:8100 -t . demo/public/index.php
 *   - php-fpm + nginx：root 指向 packages/workflow，try_files 转发到 demo/public/index.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$uri = \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// --- 静态资源（仅对确实存在的文件，且限定在包根内、禁止 .php 源码直出） ---
$root = \realpath(__DIR__ . '/../../');
$rel  = \ltrim($uri, '/');
$cand = $root . '/' . $rel;
if (\is_dir($cand)) {
    $cand .= '/index.html';
}
if (\is_file($cand) && \strpos(\realpath($cand), $root) === 0) {
    $ext = \strtolower(\pathinfo($cand, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        // views/host.php 经由下方路由渲染，其余 .php 一律拒绝直出
        if ($uri === '/demo/views/host.php') {
            require $cand;
            exit;
        }
        \http_response_code(403);
        exit;
    }
    $types = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'ico'  => 'image/x-icon',
    ];
    \header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    // 开发演示环境：JS/CSS/HTML 禁用启发式缓存，避免资源更新后 iframe 使用陈旧代码
    if (\in_array($ext, ['html', 'css', 'js'], true)) {
        \header('Cache-Control: no-cache, must-revalidate');
    }
    \readfile($cand);
    exit;
}

if (\str_starts_with($uri, '/api/')) {
    require __DIR__ . '/../src/api.php';
    exit;
}

if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/../views/host.php';
    exit;
}

\http_response_code(404);
echo 'Not Found';
