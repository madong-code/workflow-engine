<?php
/**
 * workflow demo · 前端控制器（front-controller）
 *
 * 文档根目录设为 packages/workflow 根，约定路由：
 *   - /                    宿主壳 app/views/layout.html（顶栏 + 左侧菜单 + 按需加载栏目）
 *   - /views/{name}.html   栏目页片段 app/views/{name}.html（layout fetch 注入内容区）
 *   - /assets/*            demo 静态资源 demo/public/assets/*（common.css / app.js 等，
 *                          含设计器站点 /assets/ui/）
 *   - /api/*               REST API app/controller/api.php
 *
 * 为兼容 PHP 内置服务器（指定 router 后目录请求不会自动回退到静态文件），
 * 对“真实存在的静态文件”做安全 readfile；php-fpm 下通常由 Web 服务器直接托管，
 * 不会进入本脚本，故不影响性能。
 *
 * 运行方式见 demo/README.md：
 *   - 内置服务器：php -S 127.0.0.1:8100 -t . demo/public/index.php
 *   - php-fpm + nginx：root 指向 packages/workflow，动态请求转发到 demo/public/index.php
 */

declare(strict_types=1);

$uri = \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// --- 宿主壳（约定入口） ---
$viewsDir = \realpath(__DIR__ . '/../app/views');
if (($uri === '/' || $uri === '/index.php') && $viewsDir !== false) {
    \header('Content-Type: text/html; charset=utf-8');
    \header('Cache-Control: no-cache, must-revalidate');
    \readfile($viewsDir . '/layout.html');
    exit;
}

// --- 栏目页片段（layout 按需 fetch 注入；白名单目录，防路径穿越） ---
if ($viewsDir !== false && \preg_match('#^/views/([\w-]+)\.html$#', $uri, $m)) {
    $file = $viewsDir . '/' . $m[1] . '.html';
    if (!\is_file($file)) {
        \http_response_code(404);
        exit('Not Found');
    }
    \header('Content-Type: text/html; charset=utf-8');
    \header('Cache-Control: no-cache, must-revalidate');
    \readfile($file);
    exit;
}

// --- demo 静态资源（/assets/* → demo/public/assets/*，含设计器站点 /assets/ui/） ---
if (\preg_match('#^/assets/([\w./-]+)$#', $uri, $m)) {
    $assetsRoot = \realpath(__DIR__ . '/assets');
    $file       = \realpath(__DIR__ . '/assets/' . $m[1]);
    // realpath 解析后必须仍位于 assets 根内（防路径穿越），且禁 .php 直出
    if ($assetsRoot === false || $file === false || !\is_file($file)
        || !\str_starts_with($file, $assetsRoot . DIRECTORY_SEPARATOR)) {
        \http_response_code(404);
        exit('Not Found');
    }
    if (\strtolower(\pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
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
    $ext = \strtolower(\pathinfo($file, PATHINFO_EXTENSION));
    \header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    // 开发演示环境：JS/CSS/HTML 禁用启发式缓存，避免资源更新后 iframe 使用陈旧代码
    \header('Cache-Control: no-cache, must-revalidate');
    \readfile($file);
    exit;
}

// --- REST API ---
if (\str_starts_with($uri, '/api/')) {
    require __DIR__ . '/../app/controller/api.php';
    exit;
}

\http_response_code(404);
echo 'Not Found';
