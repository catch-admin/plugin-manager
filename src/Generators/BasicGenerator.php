<?php

namespace Catch\Plugin\Generators;

/**
 * 基础生成器
 */
class BasicGenerator extends AbstractGenerator
{
    protected function collectExtraInfo(): void
    {
    }

    protected function generateExtraFiles(): void
    {
        $this->generateHookFile();
    }

    protected function getComposerExtra(): array
    {
        return [
            'hook' => $this->getNamespace() . '\\Hook',
        ];
    }

    protected function getResultRows(): array
    {
        return [
            ['Hook', '是'],
        ];
    }
}
