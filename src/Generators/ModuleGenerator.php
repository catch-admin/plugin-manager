<?php

namespace Catch\Plugin\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * 模块生成器
 */
class ModuleGenerator extends AbstractGenerator
{
    protected string $moduleName = '';

    protected ?string $moduleDir = null;

    protected bool $moduleExists = false;

    public function generate(): void
    {
        // 先提取模块名并检查是否存在
        $this->moduleName = $this->getModuleName();
        $this->moduleDir = $this->findModule($this->moduleName);
        $this->moduleExists = $this->moduleDir !== null;

        if ($this->moduleExists) {
            $this->command->info("  ✓ 找到模块: {$this->moduleDir}");
            // 模块存在，使用模块生成逻辑
            $this->generateModulePackage();
        } else {
            $this->command->warn("  ⚠ 未找到模块 '{$this->moduleName}'，将创建基础架子");
            // 模块不存在，委托给基础生成器
            $basicGenerator = new BasicGenerator($this->data, $this->command);
            $basicGenerator->generate();
        }
    }

    protected function generateModulePackage(): void
    {
        // 创建基础目录
        File::ensureDirectoryExists($this->path);
        File::ensureDirectoryExists($this->path . '/src');

        // 生成通用文件
        $this->generateComposerJson();
        $this->generateReadme();

        // 生成 Hook 和打包模块
        $this->generateExtraFiles();

        // 显示结果
        $this->displayResult();
    }

    protected function collectExtraInfo(): void
    {
    }

    protected function generateExtraFiles(): void
    {
        $this->generateModuleHookFile();
        $this->packageModule();
    }

    protected function generateModuleHookFile(): void
    {
        $namespace = $this->getNamespace();
        $stubPath = dirname(__DIR__, 2) . '/stubs/hook.module.php.stub';
        $content = File::get($stubPath);

        $content = str_replace('{{namespace}}', $namespace, $content);
        $content = str_replace('{{module}}', $this->moduleName, $content);

        File::put($this->path . '/src/Hook.php', $content);
        $this->command->info('  ✓ src/Hook.php (模块专用)');
    }

    protected function getComposerExtra(): array
    {
        return [
            'module' => $this->moduleName,
            'hook' => $this->getNamespace() . '\\Hook',
        ];
    }

    protected function getResultRows(): array
    {
        $rows = [
            ['模块', $this->moduleName],
            ['模块状态', '已打包'],
            ['Hook', '是'],
            ['后端资源', 'resource/' . $this->moduleName . '/'],
        ];

        $viewSourcePath = base_path('web/src/views/' . $this->moduleName);
        if (File::isDirectory($viewSourcePath)) {
            $rows[] = ['前端视图', 'resource/view/' . $this->moduleName . '/'];
        }

        return $rows;
    }

    protected function getModuleName(): string
    {
        $parts = explode('/', $this->data['name']);
        return $parts[1] ?? '';
    }

    protected function findModule(string $moduleName): ?string
    {
        $studlyName = Str::studly($moduleName);
        $modulePath = base_path('modules/' . $studlyName);

        if (File::isDirectory($modulePath)) {
            return $studlyName;
        }

        return null;
    }

    protected function packageModule(): void
    {
        if (!$this->moduleDir) {
            return;
        }

        $moduleSourcePath = base_path('modules/' . $this->moduleDir);
        $viewSourcePath = base_path('web/src/views/' . $this->moduleName);
        $resourcePath = $this->path . '/resource/' . $this->moduleName;
        $viewTargetPath = $this->path . '/resource/view/' . $this->moduleName;
        if (File::isDirectory($moduleSourcePath)) {
            File::ensureDirectoryExists($resourcePath);

            $dirs = File::directories($moduleSourcePath);
            foreach ($dirs as $dir) {
                $dirName = basename($dir);
                File::copyDirectory($dir, $resourcePath . '/' . $dirName);
            }

            $files = File::files($moduleSourcePath);
            foreach ($files as $file) {
                $fileName = $file->getFilename();
                if ($fileName !== 'Installer.php') {
                    File::copy($file->getPathname(), $resourcePath . '/' . $fileName);
                }
            }

            $this->command->info('  ✓ resource/' . $this->moduleName . '/');
        }

        if (File::isDirectory($viewSourcePath)) {
            File::ensureDirectoryExists($viewTargetPath);
            File::copyDirectory($viewSourcePath, $viewTargetPath);
            $this->command->info('  ✓ resource/view/' . $this->moduleName . '/');
        }
    }
}
