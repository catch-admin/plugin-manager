<?php

namespace Catch\Plugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PluginClearCommand extends Command
{
    protected $signature = 'plugin:clear';

    protected $description = '清除插件记录文件';

    public function handle(): int
    {
        $storagePath = config('plugin.installed_file');

        if (File::exists($storagePath)) {
            File::delete($storagePath);
            $this->info('插件记录已清除');
        } else {
            $this->info('暂无插件记录');
        }

        return self::SUCCESS;
    }
}
