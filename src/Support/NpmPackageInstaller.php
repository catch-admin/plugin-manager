<?php

namespace Catch\Plugin\Support;

use Catch\Plugin\Exceptions\NpmPackageException;
use Catch\Support\Terminal;

/**
 * NPM 包安装器
 *
 * 使用 yarn 来安装和管理插件的前端依赖
 */
class NpmPackageInstaller
{
    /**
     * 安装单个 NPM 包
     *
     * @param string $packageName 包名
     * @param string $version 版本号
     * @param bool $dev 是否作为开发依赖
     * @param callable|null $callback 输出回调（可选）
     * @return bool
     * @throws NpmPackageException
     */
    public function install(string $packageName, string $version = '', bool $dev = false, ?callable $callback = null): bool
    {
        $package = $version ? "{$packageName}@{$version}" : $packageName;
        $devFlag = $dev ? ' --dev' : '';

        $command = "yarn add {$package}{$devFlag}";

        return $this->runYarnCommand($command, $callback);
    }

    /**
     * 批量安装 NPM 包
     *
     * @param array $packages 包列表 ['package1' => 'version1', 'package2' => 'version2']
     * @param bool $dev 是否作为开发依赖
     * @param callable|null $callback 输出回调（可选）
     * @return bool
     * @throws NpmPackageException
     */
    public function installMultiple(array $packages, bool $dev = false, ?callable $callback = null): bool
    {
        if (empty($packages)) {
            return true;
        }

        $packageStrings = [];
        foreach ($packages as $name => $version) {
            // 清理版本号（移除 ^ ~ 等前缀用于精确安装）
            $packageStrings[] = "{$name}@{$version}";
        }

        $devFlag = $dev ? ' --dev' : '';
        $packagesArg = implode(' ', $packageStrings);

        $command = "yarn add {$packagesArg}{$devFlag}";

        return $this->runYarnCommand($command, $callback);
    }

    /**
     * 从 package.json 安装所有依赖
     *
     * @param string $packageJsonPath package.json 文件路径
     * @param callable|null $callback 输出回调（可选）
     * @return bool
     * @throws NpmPackageException
     */
    public function installFromPackageJson(string $packageJsonPath, ?callable $callback = null): bool
    {
        $data = $this->parsePackageJson($packageJsonPath);

        $dependencies = $data['dependencies'] ?? [];
        $devDependencies = $data['devDependencies'] ?? [];

        $hasChanges = false;

        // 安装生产依赖
        if (!empty($dependencies)) {
            if ($callback) {
                $callback("正在安装 dependencies: " . implode(', ', array_keys($dependencies)), 'stdout');
            }
            $this->installMultiple($dependencies, false, $callback);
            $hasChanges = true;
        }

        // 安装开发依赖
        if (!empty($devDependencies)) {
            if ($callback) {
                $callback("正在安装 devDependencies: " . implode(', ', array_keys($devDependencies)), 'stdout');
            }
            $this->installMultiple($devDependencies, true, $callback);
            $hasChanges = true;
        }

        if (!$hasChanges && $callback) {
            $callback("package.json 中没有需要安装的依赖", 'stdout');
        }

        return true;
    }

    /**
     * 卸载 NPM 包
     *
     * @param string $packageName 包名
     * @param callable|null $callback 输出回调（可选）
     * @return bool
     * @throws NpmPackageException
     */
    public function uninstall(string $packageName, ?callable $callback = null): bool
    {
        $command = "yarn remove {$packageName}";

        return $this->runYarnCommand($command, $callback);
    }

    /**
     * 检查包是否已安装
     *
     * @param string $packageName 包名
     * @return bool
     */
    public function isInstalled(string $packageName): bool
    {
        $webPackageJsonPath = base_path('web/package.json');

        if (!file_exists($webPackageJsonPath)) {
            return false;
        }

        try {
            $data = $this->parsePackageJson($webPackageJsonPath);
            $dependencies = $data['dependencies'] ?? [];
            $devDependencies = $data['devDependencies'] ?? [];

            return isset($dependencies[$packageName]) || isset($devDependencies[$packageName]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 解析 package.json 文件
     *
     * @param string $path package.json 文件路径
     * @return array
     * @throws NpmPackageException
     */
    protected function parsePackageJson(string $path): array
    {
        if (!file_exists($path)) {
            throw new NpmPackageException("package.json 文件不存在: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new NpmPackageException("无法读取 package.json 文件: {$path}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new NpmPackageException("package.json 解析失败: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * 验证 package.json 数据
     *
     * @param array $data package.json 解析后的数据
     * @return void
     * @throws NpmPackageException
     */
    protected function validatePackageJsonData(array $data): void
    {
        // package.json 的 dependencies 和 devDependencies 都是可选的
        // 只检查如果存在则必须是数组类型
        if (isset($data['dependencies']) && !is_array($data['dependencies'])) {
            throw new NpmPackageException('package.json 的 dependencies 字段必须是对象');
        }

        if (isset($data['devDependencies']) && !is_array($data['devDependencies'])) {
            throw new NpmPackageException('package.json 的 devDependencies 字段必须是对象');
        }
    }

    /**
     * 执行 yarn 命令（核心方法）
     *
     * @param string $command yarn 命令
     * @param callable|null $callback 输出回调
     * @return bool
     * @throws NpmPackageException
     */
    protected function runYarnCommand(string $command, ?callable $callback = null): bool
    {
        try {
            $result = Terminal::command($command)->runInWeb($callback);

            if (!$result->successful()) {
                throw new \RuntimeException("{$command} 执行失败");
            }

            return true;
        } catch (\Throwable $e) {
            throw new NpmPackageException("{$command} 执行失败，原因: " . $e->getMessage());
        }
    }

    /**
     * 获取 yarn 命令字符串
     *
     * @return string
     */
    protected function findYarn(): string
    {
        return 'yarn';
    }
}
