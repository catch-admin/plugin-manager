<?php

namespace Catch\Plugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * 插件打包命令.
 */
class PluginPackCommand extends Command
{
    protected $signature = 'catch:plugin-pack';

    protected $description = '将插件打包为 zip 文件';

    public function handle(): int
    {
        $this->info('  📦 CatchAdmin 插件打包');
        // 选择插件
        $name = $this->selectPlugin();

        if (! $name) {
            $this->error('未找到可用插件');

            return self::FAILURE;
        }

        $pluginPath = config('plugin.develop') . DIRECTORY_SEPARATOR . $name;

        if (! File::isDirectory($pluginPath)) {
            $this->error("插件目录不存在: {$pluginPath}");

            return self::FAILURE;
        }

        // 读取 composer.json
        $composerPath = $pluginPath . DIRECTORY_SEPARATOR . 'composer.json';
        if (! File::exists($composerPath)) {
            $this->error('插件缺少 composer.json');

            return self::FAILURE;
        }

        $composerData = json_decode(File::get($composerPath), true);
        $version = $composerData['version'] ?? '1.0.0';
        $title = $composerData['title'] ?? $name;

        // 生成 zip 文件名
        $zipName = Str::slug($name, '-') . '-' . $version . '.zip';
        $outputDir = config('plugin.dist_directory');
        $zipPath = $outputDir . DIRECTORY_SEPARATOR . $zipName;

        // 规范化路径
        $zipPath = str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
        $pluginPath = str_replace('/', DIRECTORY_SEPARATOR, $pluginPath);

        // 确保输出目录存在
        File::ensureDirectoryExists($outputDir);

        // 删除旧文件
        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        // 创建 zip
        $fileCount = spin(
            callback: function () use ($zipPath, $pluginPath) {
                // 确保插件路径存在
                if (! is_dir($pluginPath)) {
                    throw new \RuntimeException("插件目录不存在: {$pluginPath}");
                }

                // 验证输出目录权限
                $outputDir = dirname($zipPath);
                if (! is_dir($outputDir)) {
                    if (! mkdir($outputDir, 0755, true)) {
                        throw new \RuntimeException("无法创建输出目录: {$outputDir}");
                    }
                }

                if (! is_writable($outputDir)) {
                    throw new \RuntimeException("输出目录不可写: {$outputDir}");
                }

                // 使用原生 ZipArchive
                $zip = new \ZipArchive();

                // 使用临时文件避免权限问题
                $tempFile = $zipPath . '.tmp.' . uniqid();

                // 创建 zip 文件
                $result = $zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                if ($result !== true) {
                    throw new \RuntimeException("无法创建临时 Zip 文件，错误代码: {$result}");
                }

                try {
                    // 设置排除的目录
                    $excludes = config('plugin.pack_excludes');

                    // 添加文件到 zip，直接打包文件到根目录
                    $count = $this->addDirectoryToZip($zip, $pluginPath, $excludes);

                    // 关闭 zip
                    $zip->close();

                    // 验证临时文件
                    clearstatcache();
                    if (! file_exists($tempFile) || filesize($tempFile) === 0) {
                        throw new \RuntimeException('临时 Zip 文件创建失败');
                    }

                    // 重命名为最终文件名
                    if (! rename($tempFile, $zipPath)) {
                        // 如果重命名失败，尝试复制
                        if (! copy($tempFile, $zipPath)) {
                            throw new \RuntimeException('无法将临时文件移动到目标位置');
                        }
                        unlink($tempFile);
                    }

                    // 验证最终文件
                    clearstatcache();
                    if (! file_exists($zipPath) || filesize($zipPath) === 0) {
                        throw new \RuntimeException(
                            "Zip 文件未被正确创建\n" .
                            "路径: {$zipPath}\n" .
                            '请检查: 1) 目录权限 2) 磁盘空间 3) 杀毒软件是否拦截'
                        );
                    }

                    return $count;
                } catch (\Exception $e) {
                    // 清理可能创建的临时文件
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                    throw $e;
                }
            },
            message: "正在打包 {$title}..."
        );

        $fileSize = Number::fileSize(File::size($zipPath), 2);

        $this->line("  插件: {$title}");
        $this->line("  版本: {$version}");
        $this->line("  文件: {$fileCount} 个");
        $this->line("  大小: {$fileSize}");
        $this->line("  输出: {$zipPath}");
        $this->info('');

        return self::SUCCESS;
    }

    /**
     * 选择插件.
     *
     * @throws FileNotFoundException
     */
    protected function selectPlugin(): ?string
    {
        // 验证配置是否加载
        if (! config()->has('plugin.develop')) {
            $this->error('插件配置未加载，请确保 PluginServiceProvider 已正确注册');

            return null;
        }

        $pluginsDir = config('plugin.develop');

        if (! File::isDirectory($pluginsDir)) {
            return null;
        }

        $plugins = [];

        foreach (File::directories($pluginsDir) as $vendorDir) {
            if (basename($vendorDir) === '.dist') {
                continue;
            }

            // 查找第一层目录
            if (File::exists($vendorDir . DIRECTORY_SEPARATOR . 'composer.json')) {
                $data = json_decode(File::get($vendorDir . DIRECTORY_SEPARATOR . 'composer.json'), true);
                $name = $data['name'] ?? basename($vendorDir) . DIRECTORY_SEPARATOR . basename($vendorDir);
                $title = $data['title'] ?? $name;
                $plugins[$name] = $title . ' (' . $name . ')';
            }

            // 查找第二层目录
            foreach (File::directories($vendorDir) as $packageDir) {
                $composerPath = $packageDir . DIRECTORY_SEPARATOR . 'composer.json';
                if (File::exists($composerPath)) {
                    $data = json_decode(File::get($composerPath), true);
                    $name = $data['name'] ?? basename($vendorDir) . DIRECTORY_SEPARATOR . basename($packageDir);
                    $title = $data['title'] ?? $name;
                    $plugins[$name] = $title . ' (' . $name . ')';
                }
            }
        }

        if (empty($plugins)) {
            return null;
        }

        return select(
            label: '选择要打包的插件',
            options: $plugins
        );
    }

    /**
     * 统计 zip 中的文件数量.
     */
    protected function countFilesInZip(Zipper $zipper): int
    {
        return $zipper->getRepository()->getArchive()->numFiles;
    }

    /**
     * 将目录添加到 Zip 文件中.
     */
    protected function addDirectoryToZip(\ZipArchive $zip, string $sourcePath, array $excludes = []): int
    {
        $fileCount = 0;
        $sourcePath = rtrim($sourcePath, DIRECTORY_SEPARATOR);

        // 遍历目录中的所有文件
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace($sourcePath, '', $filePath);

            // 转换路径分隔符
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            // 检查是否需要排除
            if ($this->shouldExclude($relativePath, $excludes)) {
                continue;
            }

            // 在 zip 中的路径，直接使用相对路径（移除开头的斜杠）
            $zipPath = ltrim($relativePath, '/');

            if ($file->isDir()) {
                // 添加目录
                $zip->addEmptyDir($zipPath);
            } elseif ($file->isFile()) {
                // 添加文件
                if (! $zip->addFile($filePath, $zipPath)) {
                    throw new \RuntimeException("无法添加文件到 Zip: {$filePath}");
                }
                $fileCount++;
            }
        }

        return $fileCount;
    }

    /**
     * 检查文件/目录是否应该被排除.
     */
    protected function shouldExclude(string $relativePath, array $excludes): bool
    {
        // 移除开头的斜杠
        $relativePath = ltrim($relativePath, '/');

        foreach ($excludes as $exclude) {
            // 转换排除路径为正斜杠格式
            $exclude = str_replace(DIRECTORY_SEPARATOR, '/', $exclude);

            // 检查是否匹配排除的目录或文件
            if ($relativePath === $exclude ||
                str_starts_with($relativePath, $exclude . '/') ||
                str_starts_with($relativePath, '/' . $exclude . '/')) {
                return true;
            }
        }

        return false;
    }
}
