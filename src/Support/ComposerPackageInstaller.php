<?php

namespace Catch\Plugin\Support;

use Catch\Plugin\Exceptions\ComposerException;
use Catch\Support\Terminal;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Log;

/**
 * Composer 包安装器
 *
 * 使用 Laravel 内置的 Composer 类来安装和卸载插件包
 */
class ComposerPackageInstaller
{
    protected Composer $composer;

    public function __construct(?Composer $composer = null)
    {
        $this->composer = $composer ?? app(Composer::class);

        $this->composer->setWorkingPath(base_path());
    }

    /**
     * 安装 Composer 包
     *
     * @param string $packageName 包名
     * @param string $version 版本号
     * @param bool $dev 是否作为开发依赖
     * @param callable|null $callback 输出回调（可选）
     * @return bool
     * @throws ComposerException
     */
    public function install(string $packageName, string $version, bool $dev = false, ?callable $callback = null): bool
    {
        $package = "{$packageName}:{$version}";

        $command = $this->findComposer() . " require {$package} --ignore-platform-reqs --no-interaction --no-ansi";

        return $this->runComposerCommand($command, $callback);
    }

    /**
     * 卸载 Composer 包
     *
     * @param string $packageName 包名
     * @param callable|null $callback 输出回调（可选）
     * @return bool
     * @throws ComposerException
     */
    public function uninstall(string $packageName, ?callable $callback = null): bool
    {
        $command = $this->findComposer() . " remove {$packageName} --ignore-platform-reqs --no-interaction --no-ansi";

        return $this->runComposerCommand($command, $callback);
    }

    /**
     * 从 composer.json 数据中安装包
     *
     * @param array $composerData composer.json 解析后的数据
     * @param bool $dev 是否作为开发依赖
     * @param callable|null $callback 输出回调（可选）
     * @return bool
     * @throws ComposerException
     */
    public function installFromComposerData(array $composerData, bool $dev = false, ?callable $callback = null): bool
    {
        $this->validateComposerData($composerData);

        return $this->install($composerData['name'], $composerData['version'], $dev, $callback);
    }

    /**
     * 检查包是否已安装
     */
    public function isInstalled(string $packageName): bool
    {
        return $this->composer->hasPackage($packageName);
    }

    /**
     * 执行 Composer 命令（核心方法）
     * @throws ComposerException
     */
    protected function runComposerCommand(string $command, ?callable $callback = null): bool
    {
        Log::info(json_encode(json_decode(file_get_contents(base_path('auth.json')), true)));
        $result = Terminal::command($command)
                    ->setProcessEnv([
                        'COMPOSER_AUTH' => json_encode(json_decode(file_get_contents(base_path('auth.json')), true))
                    ])
                    ->run($callback);

        if (! $result->successful()) {
            throw new ComposerException("{$command} 执行失败");
        }

        return true;
    }

    /**
     * 验证 composer.json 数据
     * @throws ComposerException
     */
    protected function validateComposerData(array $data): void
    {
        if (empty($data['name'])) {
            throw new ComposerException('composer.json 缺少 name 字段');
        }
        if (empty($data['version'])) {
            throw new ComposerException('composer.json 缺少 version 字段');
        }
    }

    /**
     * 获取 Composer 命令字符串
     */
    protected function findComposer(): string
    {
        return 'composer';
    }
}
