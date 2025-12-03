<?php

namespace Catch\Plugin\Services;

use Catch\Exceptions\FailedException;
use Catch\Plugin\Enums\PluginType;
use Catch\Plugin\Exceptions\ComposerException;
use Catch\Plugin\Exceptions\InstallFailedException;
use Catch\Plugin\Exceptions\NpmPackageException;
use Catch\Plugin\Support\ComposerPackageInstaller;
use Catch\Plugin\Support\InstalledPluginManager;
use Catch\Plugin\Support\NpmPackageInstaller;
use Catch\Plugin\Support\PluginHookExecutor;
use Catch\Plugin\Exceptions\UnInstallFailedException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use ZipArchive;
use Catch\Plugin\Exceptions\DownloadFailedException;

/**
 * 插件安装服务
 *
 * 负责插件的安装、卸载等核心业务逻辑
 * Hook 由 catchadmin/plugin-hook 在 Composer 安装过程中自动执行
 */
class PluginInstallService
{
    protected ComposerPackageInstaller $installer;
    protected NpmPackageInstaller $npmInstaller;
    protected InstalledPluginManager $pluginManager;
    protected PluginApiService $pluginApi;
    protected PluginHookExecutor $hookExecutor;

    public function __construct(
        ?ComposerPackageInstaller $installer = null,
        ?NpmPackageInstaller $npmInstaller = null,
        ?InstalledPluginManager $pluginManager = null,
        ?PluginApiService $pluginApi = null,
        ?PluginHookExecutor $hookExecutor = null
    ) {
        $this->installer = $installer ?? new ComposerPackageInstaller();
        $this->npmInstaller = $npmInstaller ?? new NpmPackageInstaller();
        $this->pluginManager = $pluginManager ?? new InstalledPluginManager();
        $this->pluginApi = $pluginApi ?? new PluginApiService();
        $this->hookExecutor = $hookExecutor ?? new PluginHookExecutor();
    }

    /**
     * 安装插件
     *
     * @param string $packageName 包名 (vendor/package)
     * @param string $version 版本号
     * @param string $pluginId 插件 ID（用于记录）
     * @param callable $onProgress 进度回调 fn(step, percent, message)
     * @param callable $onLog 日志回调 fn(message, type)
     * @param string $type 插件类型（library, plugin, module, project）
     * @param string $token 认证 Token（非 Library 类型需要下载时使用）
     * @return array 安装结果
     * @throws InstallFailedException
     */
    public function install(
        string $packageName,
        string $version,
        string $pluginId,
        callable $onProgress,
        callable $onLog,
        string $type = 'library',
        string $token = ''
    ): array {
        try {
            // 根据插件类型选择安装方式
            $pluginType = PluginType::tryFrom($type) ?? PluginType::Library;

            if ($pluginType->isLibrary()) {
                return $this->installByComposer($packageName, $version, $pluginId, $onProgress, $onLog);
            }

            // 非 Library 类型通过下载解压安装
            return $this->installByDownload($packageName, $version, $pluginId, $onProgress, $onLog, $token, $type);
        } catch (\Throwable $e) {
            throw new InstallFailedException($e->getMessage());
        }
    }

    /**
     * 通过 Composer 安装插件（Library 类型）
     *
     * @param string $packageName 包名
     * @param string $version 版本号
     * @param string $pluginId 插件 ID
     * @param callable $onProgress 进度回调
     * @param callable $onLog 日志回调
     * @return array
     * @throws ComposerException
     * @throws NpmPackageException
     * @throws FileNotFoundException
     */
    protected function installByComposer(
        string $packageName,
        string $version,
        string $pluginId,
        callable $onProgress,
        callable $onLog
    ): array {
        // Composer 安装（Hook 由 composer-plugin 自动触发）
        $onProgress('composer', rand(10, 35), '正在执行 Composer 安装...');
        $this->installer->install($packageName, $version, false, $onLog);
        $onProgress('composer', 100, 'Composer 安装完成');

        // NPM 安装（如果插件有 package.json）
        $packageJsonPath = base_path("vendor/{$packageName}/package.json");
        if (file_exists($packageJsonPath)) {
            $onProgress('npm', rand(10, 35), '正在执行 NPM 依赖安装...');
            $this->npmInstaller->installFromPackageJson($packageJsonPath, $onLog);
            $onProgress('npm', 100, 'NPM 依赖安装完成');
        }

        // 记录已安装插件
        $this->pluginManager->add([
            'name' => $packageName,
            'plugin_id' => $pluginId,
            'version' => $version,
            'type' => PluginType::Library->value,
        ]);

        return [
            'success' => true,
            'package' => $packageName,
            'version' => $version,
        ];
    }

    /**
     * 通过下载解压安装插件（Plugin/Module/Project 类型）
     *
     * @param string $packageName
     * @param string $version
     * @param string $pluginId
     * @param callable $onProgress
     * @param callable $onLog
     * @param string $token
     * @param string $type
     * @return array
     * @throws DownloadFailedException
     * @throws NpmPackageException
     * @throws FileNotFoundException
     */
    protected function installByDownload(
        string $packageName,
        string $version,
        string $pluginId,
        callable $onProgress,
        callable $onLog,
        string $token,
        string $type
    ): array {
        // 步骤 1: 下载插件
        $onProgress('download', 0, '正在下载插件...');
        $onLog('开始下载插件: ' . $packageName, 'info');

        $tempDir = config('plugin.temp_directory');
        $tempFile = $tempDir . '/' . $pluginId . '_' . time() . '.zip';

        // 确保临时目录存在
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }
        $onProgress('download', 20, '正在下载插件...');

        $downloadResult = $this->pluginApi->downloadPlugin($token, $pluginId, $tempFile, $version);

        if (!$downloadResult) {
            throw new DownloadFailedException('插件下载失败，请检查网络连接或稍后重试');
        }

        $onProgress('download', 100, '下载完成');
        $onLog('插件下载完成: ' . $tempFile, 'success');

        // 步骤 2: 解压插件
        $onProgress('extract', 0, '正在解压插件...');
        $onLog('开始解压插件...', 'info');

        // 创建日期目录
        $dateDir = date('Y-m-d');
        $extractBase = config('plugin.path') . '/' . $dateDir;

        if (!File::exists($extractBase)) {
            File::makeDirectory($extractBase, 0755, true);
        }

        // 解压到临时目录先
        $tempExtractDir = $tempDir . '/extract_' . time();
        $extractResult = $this->extractZip($tempFile, $tempExtractDir);

        if (!$extractResult) {
            // 清理临时文件
            @unlink($tempFile);
            throw new DownloadFailedException('插件解压失败，请检查磁盘空间是否充足或联系管理员');
        }

        // 查找实际的插件目录（可能在 zip 内有一层目录）
        $pluginSourceDir = $this->findPluginDirectory($tempExtractDir);

        // 目标目录使用包名
        $targetDir = $extractBase . '/' . str_replace('/', '-', $packageName);

        // 如果目标目录已存在，先删除
        if (File::exists($targetDir)) {
            File::deleteDirectory($targetDir);
        }

        // 移动到目标目录
        File::moveDirectory($pluginSourceDir, $targetDir);

        $onProgress('extract', 100, '解压完成');
        $onLog('插件解压到: ' . $targetDir, 'success');

        // 步骤 3: 解析插件信息
        $onProgress('resolve', 0, '正在解析插件信息...');

        $composerData = $this->hookExecutor->getComposerData($targetDir);

        if (!$composerData) {
            $onLog('警告: 未找到 composer.json 文件', 'warning');
        } else {
            $onLog('插件名称: ' . ($composerData['title'] ?? $composerData['name'] ?? '未知'), 'info');
            $onLog('插件版本: ' . ($composerData['version'] ?? $version), 'info');
        }

        $onProgress('resolve', 100, '解析完成');

        // 执行安装 Hook
        $context = [
            'plugin_path' => $targetDir,
            'composer_data' => $composerData,
            'version' => $version,
            'plugin_id' => $pluginId,
            'type' => $type,
            'module' => $composerData['extra']['module'] ?? null,
        ];

        $beforeResult = $this->hookExecutor->executeBefore($targetDir, $context);
        if (!$beforeResult) {
            File::deleteDirectory($targetDir);
            throw new DownloadFailedException('插件安装检查未通过');
        }

        $this->hookExecutor->executeAfter($targetDir, $context);

        // Composer 依赖安装
        $this->installComposerDependencies($composerData, $onProgress, $onLog);

        // NPM 安装（如果插件有 package.json）
        $packageJsonPath = $targetDir . '/package.json';
        if (file_exists($packageJsonPath)) {
            $onProgress('npm', 0, '正在执行 NPM 依赖安装...');
            $onLog('检测到 package.json，开始安装 NPM 依赖...', 'info');
            $this->npmInstaller->installFromPackageJson($packageJsonPath, $onLog);
            $onProgress('npm', 100, 'NPM 依赖安装完成');
        }

        // 静默清理临时文件和目录
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }
        if (File::exists($tempExtractDir)) {
            File::deleteDirectory($tempExtractDir);
        }

        // 记录已安装插件
        $this->pluginManager->add([
            'name' => $packageName,
            'plugin_id' => $pluginId,
            'version' => $version,
            'type' => $type,
            'path' => $targetDir,
        ]);

        return [
            'success' => true,
            'package' => $packageName,
            'version' => $version,
            'path' => $targetDir,
        ];
    }

    /**
     * 安装 Composer 依赖（require 和 require-dev）
     */
    protected function installComposerDependencies(array $composerData, callable $onProgress, callable $onLog): void
    {
        $require = $composerData['require'] ?? [];
        $requireDev = $composerData['require-dev'] ?? [];

        // 过滤掉 php 和 ext-* 依赖
        $filterDependencies = function (array $deps): array {
            return array_filter($deps, function ($key) {
                return !str_starts_with($key, 'php') && !str_starts_with($key, 'ext-');
            }, ARRAY_FILTER_USE_KEY);
        };

        $require = $filterDependencies($require);
        $requireDev = $filterDependencies($requireDev);

        $total = count($require) + count($requireDev);
        if ($total === 0) {
            return;
        }

        $onProgress('composer', 0, '正在安装 Composer 依赖...');
        $installed = 0;

        // 安装 require 依赖
        foreach ($require as $package => $version) {
            $onLog("安装依赖: {$package}", 'info');
            try {
                $this->installer->install($package, $version, false, $onLog);
            } catch (\Throwable $e) {
                $onLog("依赖 {$package} 安装失败: " . $e->getMessage(), 'warning');
            }
            $installed++;
            $onProgress('composer', (int)(($installed / $total) * 100), "已安装 {$installed}/{$total}");
        }

        // 安装 require-dev 依赖
        foreach ($requireDev as $package => $version) {
            $onLog("安装开发依赖: {$package}", 'info');
            try {
                $this->installer->install($package, $version, true, $onLog);
            } catch (\Throwable $e) {
                $onLog("开发依赖 {$package} 安装失败: " . $e->getMessage(), 'warning');
            }
            $installed++;
            $onProgress('composer', (int)(($installed / $total) * 100), "已安装 {$installed}/{$total}");
        }

        $onProgress('composer', 100, 'Composer 依赖安装完成');
    }

    /**
     * 解压 ZIP 文件
     */
    protected function extractZip(string $zipFile, string $extractTo): bool
    {
        $zip = new ZipArchive();

        if ($zip->open($zipFile) !== true) {
            return false;
        }

        // 确保目标目录存在
        if (!File::exists($extractTo)) {
            File::makeDirectory($extractTo, 0755, true);
        }

        $result = $zip->extractTo($extractTo);
        $zip->close();

        return $result;
    }

    /**
     * 查找插件目录（处理 zip 内可能有一层目录的情况）
     *
     * @param string $extractDir 解压目录
     * @return string
     */
    protected function findPluginDirectory(string $extractDir): string
    {
        $items = File::directories($extractDir);

        // 如果解压后只有一个目录，且该目录包含 composer.json，则认为是插件目录
        if (count($items) === 1) {
            $subDir = $items[0];
            if (File::exists($subDir . '/composer.json') || File::exists($subDir . '/hook.php')) {
                return $subDir;
            }
        }

        // 如果根目录就有 composer.json 或 hook.php，则根目录就是插件目录
        if (File::exists($extractDir . '/composer.json') || File::exists($extractDir . '/hook.php')) {
            return $extractDir;
        }

        // 默认返回第一个子目录或根目录
        return count($items) > 0 ? $items[0] : $extractDir;
    }

    /**
     * 卸载插件
     *
     * @param string $name Composer 包名
     * @param callable $onProgress 进度回调
     * @param callable $onLog 日志回调
     * @return array 卸载结果
     * @throws UnInstallFailedException
     */
    public function uninstall(
        string $name,
        callable $onProgress,
        callable $onLog
    ): array {
        try {
            $onProgress('check', 0, '检查插件信息...');
            $pluginInfo = $this->pluginManager->get($name);

            if (!$pluginInfo) {
                throw new \Exception('插件未安装或已被卸载');
            }

            $onProgress('check', 100, '插件信息确认');
            $onLog('插件类型: ' . ($pluginInfo['type'] ?? 'library'), 'info');

            // 根据插件类型选择卸载方式
            $pluginType = $pluginInfo['type'] ?? PluginType::Library->value;

            if ($pluginType === PluginType::Library->value) {
                return $this->uninstallByComposer($name, $pluginInfo, $onProgress, $onLog);
            }

            // 非 Library 类型通过删除目录卸载
            return $this->uninstallByDelete($name, $pluginInfo, $onProgress, $onLog);
        } catch (\Throwable $e) {
            throw new UnInstallFailedException($e->getMessage());
        }
    }

    /**
     * 通过 Composer 卸载插件（Library 类型）
     *
     * @param string $name 包名
     * @param array $pluginInfo 插件信息
     * @param callable $onProgress 进度回调
     * @param callable $onLog 日志回调
     * @return array
     */
    protected function uninstallByComposer(
        string $name,
        array $pluginInfo,
        callable $onProgress,
        callable $onLog
    ): array {
        // Composer 卸载（Hook 由 composer-plugin 自动触发）
        $onProgress('composer', 0, '正在执行 Composer 卸载...');
        $this->installer->uninstall($name, $onLog);
        $onProgress('composer', 100, 'Composer 卸载完成');

        // 移除记录
        $this->pluginManager->remove($name);

        return [
            'success' => true,
            'message' => '插件卸载成功',
        ];
    }

    /**
     * 通过删除目录卸载插件（Plugin/Module/Project 类型）
     *
     * @param string $name 包名
     * @param array $pluginInfo 插件信息
     * @param callable $onProgress 进度回调
     * @param callable $onLog 日志回调
     * @return array
     * @throws \Exception
     */
    protected function uninstallByDelete(
        string $name,
        array $pluginInfo,
        callable $onProgress,
        callable $onLog
    ): array {
        $pluginPath = $pluginInfo['path'] ?? '';

        // 执行卸载 Hook
        if (!empty($pluginPath) && File::exists($pluginPath)) {
            $composerData = $this->hookExecutor->getComposerData($pluginPath);
            $context = [
                'plugin_path' => $pluginPath,
                'composer_data' => $composerData,
                'version' => $pluginInfo['version'] ?? '',
                'plugin_id' => $pluginInfo['plugin_id'] ?? '',
                'type' => $pluginInfo['type'] ?? PluginType::Plugin->value,
                'module' => $composerData['extra']['module'] ?? null,
            ];

            $beforeResult = $this->hookExecutor->executeBeforeUninstall($pluginPath, $context);
            if (!$beforeResult) {
                throw new \Exception('插件卸载检查未通过');
            }

            $this->hookExecutor->executeAfterUninstall($pluginPath, $context);
        }

        // 删除插件目录
        $onProgress('cleanup', 0, '正在清理...');

        if (!empty($pluginPath) && File::exists($pluginPath)) {
            File::deleteDirectory($pluginPath);
        }

        $onProgress('cleanup', 100, '清理完成');

        // 移除记录
        $this->pluginManager->remove($name);

        return [
            'success' => true,
            'message' => '插件卸载成功',
        ];
    }
}
