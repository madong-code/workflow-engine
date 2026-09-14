<?php

namespace madong\workflow\libs\utils;

class PropertyCopier
{
    public static function copy(object $source, object $target, array $properties): void
    {
        $refSource = new \ReflectionClass($source);
        $refTarget = new \ReflectionClass($target);

        foreach ($properties as $prop) {
            try {
                $srcProp = $refSource->getProperty($prop);
                $srcProp->setAccessible(true);
                $value = $srcProp->getValue($source);

                $dstProp = $refTarget->getProperty($prop);
                $dstProp->setAccessible(true);
                $dstProp->setValue($target, $value);
            } catch (\ReflectionException) {
                // 跳过不存在的属性
            }
        }
    }
}
