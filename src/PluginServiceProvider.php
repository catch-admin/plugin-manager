<?php

namespace Catch\Plugin;

use Catch\Plugin\Commands\InstallCommand;
use Catch\Plugin\Commands\PluginClearCommand;
use Catch\Plugin\Commands\PluginInitCommand;
use Catch\Plugin\Commands\PluginOptimizeCommand;
use Catch\Plugin\Commands\PluginPackCommand;
use Catch\Plugin\Support\Plugin;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/plugin.php',
            'plugin'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 加载路由
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // 加载插件路由（仅在未缓存时）
        $this->loadPluginRoutes();

        if ($this->app->runningInConsole()) {
            // 发布前端 view 文件
            $viewPath = base_path('web' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'views');

            $this->publishes([
               $this->getPluginView() => $viewPath . DIRECTORY_SEPARATOR . 'plugin',
            ], 'plugin-view');
        }

        $this->commands([
            InstallCommand::class,
            PluginInitCommand::class,
            PluginPackCommand::class,
            PluginOptimizeCommand::class,
            PluginClearCommand::class,
        ]);
    }

    /**
     * 加载插件路由
     *
     * 如果路由已缓存则跳过，否则从 plugins.json 或插件目录加载
     */
    protected function loadPluginRoutes(): void
    {
        // 路由已缓存，跳过动态加载
        if ($this->app->routesAreCached()) {
            return;
        }

        foreach (Plugin::allRoutes() as $routeFile) {
            $this->loadRoutesFrom($routeFile);
        }
    }

    /**
     * 获取 plugin 视图层
     *
     * @return string
     */
    protected function getPluginView(): string
    {
        return dirname(__DIR__) .
            DIRECTORY_SEPARATOR . 'resource' .
            DIRECTORY_SEPARATOR . 'view' .
            DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR;
    }
}
