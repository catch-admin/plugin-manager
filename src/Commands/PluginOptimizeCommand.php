<?php

namespace Catch\Plugin\Commands;

use Catch\Plugin\Support\InstalledPluginManager;
use Catch\Plugin\Support\Plugin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 插件优化命令
 * 
 * 扫描已安装的插件目录，更新插件记录
 */
class PluginOptimizeCommand extends Command
{
    protected $signature = 'plugin:optimize';

    protected $description = '优化插件记录（扫描插件目录并更新记录）';

    public function handle(InstalledPluginManager $manager): int
    {
        $plugins = Plugin::all();
        
        if (empty($plugins)) {
            $this->components->info('暂无插件');
            return self::SUCCESS;
        }

        $count = 0;

        foreach ($plugins as $name => $plugin) {
            $directory = $plugin['path'] ?? '';
            $composerFile = $directory . '/composer.json';
            
            if (!File::exists($composerFile)) {
                continue;
            }

            $composerData = json_decode(File::get($composerFile), true);

            // 检查是否已记录
            if ($manager->isInstalled($name)) {
                // 更新记录
                $manager->update($name, [
                    'version' => $composerData['version'] ?? '',
                    'path' => $directory,
                ]);
            } else {
                // 添加新记录
                $manager->add([
                    'name' => $name,
                    'version' => $composerData['version'] ?? '',
                    'type' => $composerData['type'] ?? 'catchadmin-plugin',
                    'path' => $directory,
                ]);
            }
            
            $count++;
        }

        $this->components->info("优化完成，共 {$count} 个插件");

        return self::SUCCESS;
    }
}
