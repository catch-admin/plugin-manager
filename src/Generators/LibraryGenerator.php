<?php

namespace Catch\Plugin\Generators;

use Illuminate\Support\Composer;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

/**
 * Composer 包生成器
 */
class LibraryGenerator extends AbstractGenerator
{
    protected bool $needServiceProvider = false;

    protected bool $needHook = false;

    protected function collectExtraInfo(): void
    {
        $this->needServiceProvider = confirm('是否需要 Laravel 服务提供者？');
        $this->needHook = confirm('是否需要生成 Hook 文件？');
    }

    protected function generateExtraFiles(): void
    {
        if ($this->needServiceProvider) {
            $this->generateServiceProvider();
        }

        if ($this->needHook) {
            $this->generateHookFile();
        }
    }

    protected function getComposerExtra(): array
    {
        $extra = [];

        if ($this->needHook) {
            $extra['hook'] = $this->getNamespace() . '\\Hook';
        }

        if ($this->needServiceProvider) {
            $extra['laravel']['providers'] = [
                $this->getNamespace() . '\\ServiceProvider',
            ];
        }

        return $extra;
    }

    protected function extendComposerJson(array $composer): array
    {
        $namespace = $this->getNamespace();

        $composer['require'] = [
            'php' => config('plugin.php_version'),
        ];

        $composer['autoload'] = [
            'psr-4' => [
                $namespace . '\\' => 'src/',
            ],
        ];

        return $composer;
    }

    protected function afterGenerate(): void
    {
        // 更新根目录 composer.json
        $this->updateRootComposer();

        // 运行 composer dump-autoload
        spin(
            callback: fn () => app(Composer::class)->setWorkingPath(base_path())->dumpAutoloads(),
            message: '正在更新自动加载...'
        );
    }

    protected function updateRootComposer(): void
    {
        $pluginDir = config('plugin.directory');
        $composer = app(Composer::class)->setWorkingPath(base_path());
        $namespace = $this->getNamespace() . '\\';
        $relativePath = $pluginDir . '/' . $this->data['name'] . '/src/';

        $composer->modify(function ($composerJson) use ($namespace, $relativePath) {
            if (!isset($composerJson['autoload']['psr-4'][$namespace])) {
                $composerJson['autoload']['psr-4'][$namespace] = $relativePath;
            }
            return $composerJson;
        });

        $this->command->line('  ✓ 更新根目录 composer.json');
    }

    protected function getResultRows(): array
    {
        return [
            ['命名空间', $this->getNamespace()],
            ['ServiceProvider', $this->needServiceProvider ? '是' : '否'],
            ['Hook', $this->needHook ? '是' : '否'],
        ];
    }
}
