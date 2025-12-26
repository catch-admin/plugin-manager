<?php

namespace Catch\Plugin\Generators;

use function Laravel\Prompts\confirm;

/**
 * 基础生成器.
 */
class BasicGenerator extends AbstractGenerator
{
    protected function collectExtraInfo(): void
    {
        $this->data['create_route'] = confirm(
            label: '是否创建路由文件?',
            default: true
        );
    }

    protected function generateExtraFiles(): void
    {
        $this->generateHookFile();
        if ($this->data['create_route'] ?? false) {
            $this->generateRouteFile();
        }
    }

    protected function getComposerExtra(): array
    {
        return [];
    }

    protected function getResultRows(): array
    {
        return [
            ['Hook', '是'],
        ];
    }
}
