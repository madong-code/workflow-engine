<?php
/**
 * workflow demo · 演示用户服务（jeeflow 风格多用户切换，name 即 operator）
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use madong\workflow\demo\model\Seeds;

final class UserService
{
    /** @return array<int, array{name: string, display_name: string}> */
    public static function list(): array
    {
        $list = [];
        foreach (Seeds::users() as $name => $displayName) {
            $list[] = ['name' => $name, 'display_name' => $displayName];
        }
        return $list;
    }
}
