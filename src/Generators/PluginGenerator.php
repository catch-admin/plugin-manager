<?php

namespace Catch\Plugin\Generators;

/**
 * 插件生成器
 * 
 * 生成 type=catchadmin-plugin 的插件包
 */
class PluginGenerator extends BasicGenerator
{
    /**
     * 扩展 composer.json 配置
     * 将 type 改为 catchadmin-plugin
     */
    protected function extendComposerJson(array $composer): array
    {
        $composer['type'] = 'catchadmin-plugin';
        return $composer;
    }
}
