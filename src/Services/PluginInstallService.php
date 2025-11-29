<?php

namespace Catch\Plugin\Services;

use Catch\Exceptions\FailedException;
use Catch\Plugin\Exceptions\ComposerException;
use Catch\Plugin\Exceptions\InstallFailedException;
use Catch\Plugin\Exceptions\NpmPackageException;
use Catch\Plugin\Support\ComposerPackageInstaller;
use Catch\Plugin\Support\InstalledPluginManager;
use Catch\Plugin\Support\NpmPackageInstaller;
use Catch\Plugin\Exceptions\UnInstallFailedException;

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

    public function __construct(
        ?ComposerPackageInstaller $installer = null,
        ?NpmPackageInstaller $npmInstaller = null,
        ?InstalledPluginManager $pluginManager = null
    ) {
        $this->installer = $installer ?? new ComposerPackageInstaller();
        $this->npmInstaller = $npmInstaller ?? new NpmPackageInstaller();
        $this->pluginManager = $pluginManager ?? new InstalledPluginManager();
    }

    /**
     * 安装插件
     *
     * @param string $packageName 包名 (vendor/package)
     * @param string $version 版本号
     * @param string $pluginId 插件 ID（用于记录）
     * @param callable $onProgress 进度回调 fn(step, percent, message)
     * @param callable $onLog 日志回调 fn(message, type)
     * @return array 安装结果
     * @throws InstallFailedException
     */
    public function install(
        string $packageName,
        string $version,
        string $pluginId,
        callable $onProgress,
        callable $onLog
    ): array {
        try {
            // Composer 安装（Hook 由 composer-plugin 自动触发）
            $onProgress('composer', 0, '正在执行 Composer 安装...');
            $this->installer->install($packageName, $version, false, $onLog);
            $onProgress('composer', 100, 'Composer 安装完成');

            // NPM 安装（如果插件有 package.json）
            $packageJsonPath = base_path("vendor/{$packageName}/package.json");
            if (file_exists($packageJsonPath)) {
                $onProgress('npm', 0, '正在执行 NPM 依赖安装...');
                $this->npmInstaller->installFromPackageJson($packageJsonPath, $onLog);
                $onProgress('npm', 100, 'NPM 依赖安装完成');
            }

            // 记录已安装插件
            $this->pluginManager->add([
                'name' => $packageName,
                'plugin_id' => $pluginId,
                'version' => $version,
            ]);

            return [
                'success' => true,
                'package' => $packageName,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            throw new InstallFailedException($e->getMessage());
        }
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
                throw new \Exception('插件未安装');
            }

            $onProgress('check', 100, '插件信息确认');

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
        } catch (\Throwable $e) {
            throw new UnInstallFailedException($e->getMessage());
        }
    }
}
