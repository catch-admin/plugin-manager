<?php

namespace Catch\Plugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;


/**
 * 租户安装
 */
class InstallCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'catch:plugin-install';

    /**
     * @return void
     * @throws \JsonException
     */
    public function handle(): void
    {
        $this->callSilently('vendor:publish', [
            '--provider' => 'Catch\Plugin\PluginServiceProvider',
            '--tag' => 'plugin-view',
            '--force' => true,
        ]);

        $this->addLocalPathRepository();

        $this->info('🎉 插件系统已安装，现在可以访问后台 /plugins 插件页面安装插件啦');
    }

    /**
     * @throws \JsonException
     */
    protected function addLocalPathRepository(): void
    {
        $composer = app(Composer::class)->setWorkingPath(base_path());

        $composer->modify(function ($composer){
            $repositories = $composer['repositories'];

            $addRepository = [
                'type' => 'path',
                'url' => 'storage/plugins/*/*'
            ];

            $isExist = false;
            foreach ($repositories as $repository) {
                if ($addRepository == $repository) {
                    $isExist = true;
                }
            }

            if (! $isExist) {
                $composer['repositories'][] = $addRepository;
            }

            return $composer;
        });
    }
}
