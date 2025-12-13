<?php

namespace Catch\Plugin\Commands;

use Catch\Plugin\Support\Plugin;
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
    protected $signature = 'catch:plugin-install --view';

    /**
     * @return void
     * @throws \JsonException
     */
    public function handle(): void
    {
        if ($this->hasOption('view')) {
            $this->publishView();
        }

        $this->addLocalPathRepository();

        $this->addMens();

        $this->info('🎉 插件系统已安装，现在可以访问后台 /plugins 插件页面安装插件啦');
    }

    protected function publishView()
    {
        $this->callSilently('vendor:publish', [
            '--provider' => 'Catch\Plugin\PluginServiceProvider',
            '--tag' => 'plugin-view',
            '--force' => true,
        ]);
    }

    /**
     * @throws \JsonException
     */
    protected function addLocalPathRepository(): void
    {
        $composer = app(Composer::class)->setWorkingPath(base_path());

        $composer->modify(function ($composer){
            $repositories = $composer['repositories'] ?? [];

            $addRepository = [
                'type' => 'composer',
                'url' => config('plugin.plugin_host') . '/' . 'plugin'
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


    protected function addMens(): void
    {
        Plugin::createMenus([
            Plugin::createMenu('插件管理', '/plugin', 'Catch\Plugin', children: [
                Plugin::createMenu('插件列表', '/index', 'Catch\Plugin',
                    controller: 'Plugin', controllerMethod: 'index',type: 2,
                    component: Plugin::view('plugin', 'index.vue')
                )
            ])
        ]);
    }
}
