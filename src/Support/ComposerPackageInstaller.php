<?php

namespace Catch\Plugin\Support;

use Catch\Plugin\Exceptions\ComposerException;
use Catch\Support\Terminal;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;

/**
 * Composer 包安装器
 *
 * 使用 Laravel 内置的 Composer 类来安装和卸载插件包
 */
class ComposerPackageInstaller
{
    protected Composer $composer;

    protected ?string $token = null;
    public function __construct(?Composer $composer = null)
    {
        $this->composer = $composer ?? app(Composer::class);

        $this->composer->setWorkingPath(base_path());
    }

    /**
     * 设置 token
     *
     * @param string $token
     * @return $this
     */
    public function token(string $token): static
    {
        $this->token = $token;

        return $this;
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

        $devFlag = $dev ? ' --dev' : '';
        $command = $this->findComposer() . " require {$package}{$devFlag} --ignore-platform-reqs --no-interaction --no-ansi --no-cache";

        try {
            return $this->runComposerCommand($command, $callback);
        } catch (ComposerException $e) {
            $this->composer->modify(function ($composerJson) use ($packageName, $version){
                if (isset($composerJson['require'][$packageName])) {
                    if ($composerJson['require'][$packageName] == $version) {
                        unset($composerJson['require'][$packageName]);
                    }
                }

                return $composerJson;
            });
            throw new ComposerException($e->getMessage());
        }
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
        $env = [];

        // 默认设置
        if ($this->token) {
            $env['COMPOSER_AUTH'] = json_encode([
                'bearer' => [
                    parse_url(config('plugin.plugin_host'), PHP_URL_HOST) => $this->token,
                ]
            ]);
        }

        $result = Terminal::command($command)
            ->setProcessEnv($env)
            ->run($callback);

        if (! $result->successful()) {
            if (Str::of($result->errorOutput())->contains('HTTP 401')) {
                throw new ComposerException("没有权限安装该扩展，请确认是否已购买?");
            }

            if (Str::of($result->errorOutput())->contains('HTTP 404')) {
                throw new ComposerException("该扩展资源未找到，请联系官方");
            }

            throw new ComposerException("扩展安装失败");
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
