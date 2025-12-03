<?php

namespace Catch\Plugin\Support;

use Illuminate\Support\Facades\Log;

/**
 * 插件 Hook 执行器
 * 
 * 支持两种 Hook 格式：
 * 1. 类格式：src/Hook.php（优先）
 * 2. 数组格式：hook.php（兼容）
 */
class PluginHookExecutor
{
    /**
     * 执行安装前钩子
     *
     * @param string $pluginPath 插件目录路径
     * @param array $context 上下文信息
     * @return bool 返回 false 将阻止安装
     */
    public function executeBefore(string $pluginPath, array $context = []): bool
    {
        return $this->executeHook($pluginPath, 'before', $context);
    }

    /**
     * 执行安装后钩子
     *
     * @param string $pluginPath 插件目录路径
     * @param array $context 上下文信息
     * @return void
     */
    public function executeAfter(string $pluginPath, array $context = []): void
    {
        $this->executeHook($pluginPath, 'after', $context);
    }

    /**
     * 执行卸载前钩子
     *
     * @param string $pluginPath 插件目录路径
     * @param array $context 上下文信息
     * @return bool 返回 false 将阻止卸载
     */
    public function executeBeforeUninstall(string $pluginPath, array $context = []): bool
    {
        return $this->executeHook($pluginPath, 'beforeUninstall', $context);
    }

    /**
     * 执行卸载后钩子
     *
     * @param string $pluginPath 插件目录路径
     * @param array $context 上下文信息
     * @return void
     */
    public function executeAfterUninstall(string $pluginPath, array $context = []): void
    {
        $this->executeHook($pluginPath, 'afterUninstall', $context);
    }

    /**
     * 执行指定的钩子
     *
     * @param string $pluginPath 插件目录路径
     * @param string $hookName 钩子名称
     * @param array $context 上下文信息
     * @return bool|void
     */
    protected function executeHook(string $pluginPath, string $hookName, array $context = []): mixed
    {
        // 构建上下文信息
        $context = array_merge([
            'plugin_path' => $pluginPath,
        ], $context);

        // 优先使用类格式（src/Hook.php）
        $classResult = $this->executeClassHook($pluginPath, $hookName, $context);
        if ($classResult !== null) {
            return $classResult;
        }

        // 回退到数组格式（hook.php）
        return $this->executeArrayHook($pluginPath, $hookName, $context);
    }

    /**
     * 执行类格式的 Hook（src/Hook.php）
     *
     * @param string $pluginPath 插件目录路径
     * @param string $hookName 钩子名称
     * @param array $context 上下文信息
     * @return mixed|null 返回 null 表示未找到类格式 Hook
     */
    protected function executeClassHook(string $pluginPath, string $hookName, array $context): mixed
    {
        // 获取 Hook 类名
        $hookClass = $this->getHookClass($pluginPath);

        if ($hookClass === null) {
            return null;
        }

        // 如果类不存在，尝试手动加载
        if (!class_exists($hookClass)) {
            $hookFile = rtrim($pluginPath, '/\\') . '/src/Hook.php';
            if (file_exists($hookFile)) {
                require_once $hookFile;
            }
        }

        if (!class_exists($hookClass)) {
            return null; // 仍然不存在，回退到数组格式
        }

        // 映射钩子名称到方法名
        $methodMap = [
            'before' => 'beforeInstall',
            'after' => 'afterInstall',
            'beforeUpdate' => 'beforeUpdate',
            'afterUpdate' => 'afterUpdate',
            'beforeUninstall' => 'beforeUninstall',
            'afterUninstall' => 'afterUninstall',
        ];

        $methodName = $methodMap[$hookName] ?? $hookName;

        if (!method_exists($hookClass, $methodName)) {
            Log::info("插件 Hook 类方法不存在: {$hookClass}::{$methodName}");
            return $hookName === 'before' || $hookName === 'beforeUninstall' ? true : null;
        }

        try {
            $result = $hookClass::$methodName($context);

            Log::info("插件 Hook [{$hookName}] 执行完成（类格式）", [
                'plugin_path' => $pluginPath,
                'hook_class' => $hookClass,
                'result' => $result,
            ]);

            if ($hookName === 'before' || $hookName === 'beforeUninstall') {
                return $result !== false;
            }

            return true; // 标记已执行
        } catch (\Throwable $e) {
            Log::error("插件 Hook [{$hookName}] 执行异常（类格式）", [
                'plugin_path' => $pluginPath,
                'hook_class' => $hookClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 执行数组格式的 Hook（hook.php）
     *
     * @param string $pluginPath 插件目录路径
     * @param string $hookName 钩子名称
     * @param array $context 上下文信息
     * @return mixed
     */
    protected function executeArrayHook(string $pluginPath, string $hookName, array $context): mixed
    {
        $hookFile = rtrim($pluginPath, '/\\') . '/hook.php';

        if (!file_exists($hookFile)) {
            Log::info("插件 Hook 文件不存在，跳过执行: {$hookFile}");
            return $hookName === 'before' || $hookName === 'beforeUninstall' ? true : null;
        }

        try {
            $hooks = require $hookFile;

            if (!is_array($hooks)) {
                Log::warning("插件 Hook 文件格式错误，应返回数组: {$hookFile}");
                return $hookName === 'before' || $hookName === 'beforeUninstall' ? true : null;
            }

            if (!isset($hooks[$hookName]) || !is_callable($hooks[$hookName])) {
                Log::info("插件 Hook [{$hookName}] 未定义或不可调用: {$hookFile}");
                return $hookName === 'before' || $hookName === 'beforeUninstall' ? true : null;
            }

            $result = call_user_func($hooks[$hookName], $context);

            Log::info("插件 Hook [{$hookName}] 执行完成（数组格式）", [
                'plugin_path' => $pluginPath,
                'result' => $result,
            ]);

            if ($hookName === 'before' || $hookName === 'beforeUninstall') {
                return $result !== false;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("插件 Hook [{$hookName}] 执行异常（数组格式）", [
                'plugin_path' => $pluginPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 从 composer.json 获取 Hook 类名
     *
     * @param string $pluginPath 插件目录路径
     * @return string|null
     */
    protected function getHookClass(string $pluginPath): ?string
    {
        $composerData = $this->getComposerData($pluginPath);

        if ($composerData === null) {
            return null;
        }

        // 从 extra.hook 获取类名
        return $composerData['extra']['hook'] ?? null;
    }

    /**
     * 获取插件的 composer.json 数据
     *
     * @param string $pluginPath 插件目录路径
     * @return array|null
     */
    public function getComposerData(string $pluginPath): ?array
    {
        $composerFile = rtrim($pluginPath, '/\\') . '/composer.json';

        if (!file_exists($composerFile)) {
            return null;
        }

        $content = file_get_contents($composerFile);
        return json_decode($content, true);
    }
}
