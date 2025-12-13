<?php
namespace Catch\Plugin\Support;

use Catch\CatchAdmin;
use Catch\Contracts\ModuleRepositoryInterface;
use Catch\Support\DB\SeedRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Permissions\Events\DeleteModuleMenusEvent;
use Modules\Permissions\Events\DisableModuleMenusEvent;
use Modules\Permissions\Events\EnableModuleMenusEvent;
use Throwable;

/**
 * 插件助手
 */
class Plugin
{
    /**
     * 发布
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function publish(string $from, string $to): bool
    {
        File::ensureDirectoryExists($from);

        return File::copyDirectory($from, $to);
    }

    /**
     * 发布试图层
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function publishView(string $from, string $to): bool
    {
        return self::publish($from, base_path(
            'web' . DIRECTORY_SEPARATOR .
                'src' . DIRECTORY_SEPARATOR .
                'views' . DIRECTORY_SEPARATOR . $to
        ));
    }

    /**
     * @param string $name
     * @return void
     */
    public static function deleteView(string $name): void
    {
        File::deleteDirectory(base_path(
            'web' . DIRECTORY_SEPARATOR .
            'src' . DIRECTORY_SEPARATOR .
            'views' . DIRECTORY_SEPARATOR . $name
        ));
    }

    /**
     * 发布模块
     *
     * @param string $from 源目录路径
     * @param string $moduleName 模块名称（如 ai, user-permission）
     * @return bool
     */
    public static function publishModule(string $from, string $moduleName): bool
    {
        // 转换为 StudlyCase（如 ai -> Ai, user-permission -> UserPermission）
        $studlyName = Str::studly($moduleName);

        return self::publish($from, base_path(
            'modules' . DIRECTORY_SEPARATOR . $studlyName
        ));
    }

    /**
     * 删除模块
     *
     * @param string $moduleName 模块名称
     * @return void
     */
    public static function deleteModule(string $moduleName): void
    {
        $studlyName = Str::studly($moduleName);

        File::deleteDirectory(base_path(
            'modules' . DIRECTORY_SEPARATOR . $studlyName
        ));
    }

    /**
     * 执行数据库迁移（路径方式）
     */
    public static function migrate(string $path): int
    {
        if (Str::of($path)->startsWith(base_path())) {
            $path = Str::of($path)->remove(base_path());
        }

        $path = trim($path, DIRECTORY_SEPARATOR);

        return Artisan::call('migrate', [
            '--path' => $path,
            '--force' => true
        ]);
    }

    /**
     * 迁移数据
     *
     * @param string $class
     * @return int
     */
    public static function seed(string $class): int
    {
        return Artisan::call('db:seed', [
            'class' => $class
        ]);
    }

    /**
     * 执行模块数据库迁移
     */
    public static function migrateModule(string $moduleName): int
    {
        return self::migrate(CatchAdmin::getModuleMigrationPath($moduleName));
    }

    /**
     * 执行模块数据库填充
     * @throws Throwable
     */
    public static function seedModule(string $moduleName, $seeder = null): bool
    {
        return SeedRun::run($moduleName, $seeder);
    }

    /**
     * 注册模块
     */
    public static function registerModule(string $moduleName, array $info = []): void
    {
        $repository = app(ModuleRepositoryInterface::class);
        $studlyName = Str::studly($moduleName);

        $defaultInfo = [
            'name' => $studlyName,
            'title' => $info['title'] ?? $studlyName,
            'description' => $info['description'] ?? '',
            'keywords' => $info['keywords'] ?? '',
            'order' => $info['order'] ?? 0,
            'enable' => true,
        ];

        $repository->create(array_merge($defaultInfo, $info));
    }

    /**
     * 注销模块
     */
    public static function unregisterModule(string $moduleName): void
    {
        $repository = app(ModuleRepositoryInterface::class);
        $repository->delete(Str::studly($moduleName));
        # 删除菜单
        self::deleteAdminModuleMenu($moduleName);
    }

    /**
     * @param string $module
     * @param string|array $permissionMark
     * @return void
     */
    public static function disableAdminModuleMenu(string $module, string|array $permissionMark): void
    {
        DisableModuleMenusEvent::dispatch($module, $permissionMark);
    }

    /**
     * @param string $module
     * @param string|array $permissionMark
     * @return void
     */
    public static function enableAdminModuleMenu(string $module, string|array $permissionMark): void
    {
        EnableModuleMenusEvent::dispatch($module, $permissionMark);
    }

    /**
     * @param string $module
     * @return void
     */
    public static function deleteAdminModuleMenu(string $module): void
    {
        DeleteModuleMenusEvent::dispatch($module);
    }

    /**
     * 动态渲染 vue 页面
     *
     * @param string $basePath 插件 view 目录
     * @param string $filename 插件 vue 文件名称，.vue 结尾
     * @return array
     * @throws \Exception
     */
    public static function renderView(string $basePath, string $filename): array
    {
        $filePath = $basePath . DIRECTORY_SEPARATOR. $filename;

        if (! file_exists($filePath)) {
            throw new \Exception('页面未找到');
        }

        return [
            'entry' => '/' . $filename,
            'files' => CollectVueDepsFile::collectFilesWithDeps($filePath, $basePath)
        ];
    }

    /**
     * 获取所有插件路由文件
     *
     * 优先从 plugins.json 加载，否则扫描插件目录
     *
     * @return array 路由文件路径数组
     */
    public static function allRoutes(): array
    {
        $routes = [];
        $plugins = self::all();

        foreach ($plugins as $plugin) {
            $routesPath = ($plugin['path'] ?? '') . '/routes';

            if (!File::isDirectory($routesPath)) {
                continue;
            }

            foreach (File::files($routesPath) as $file) {
                if ($file->getExtension() === 'php') {
                    $routes[] = $file->getPathname();
                }
            }
        }

        return $routes;
    }

    /**
     * 获取所有已安装插件
     *
     * 优先从 plugins.json 加载，否则扫描插件目录
     *
     * @return array
     */
    public static function all(): array
    {
        $installedFile = config('plugin.installed_file');

        // 优先从 plugins.json 加载
        if (File::exists($installedFile)) {
            $content = File::get($installedFile);
            $plugins = json_decode($content, true);
            if (is_array($plugins) && !empty($plugins)) {
                return $plugins;
            }
        }

        // 扫描插件安装目录
        $installPath = config('plugin.install_path');

        if (!File::isDirectory($installPath)) {
            return [];
        }

        $plugins = [];

        // 使用 glob 查找一级和二级目录下的 composer.json
        $composerFiles = array_merge(
            File::glob($installPath . '/*/composer.json'),
            File::glob($installPath . '/*/*/composer.json')
        );

        foreach ($composerFiles as $composerFile) {
            $composerData = json_decode(File::get($composerFile), true);
            if ($composerData && !empty($composerData['name'])) {
                $plugins[$composerData['name']] = [
                    'path' => dirname($composerFile),
                ];
            }
        }

        return $plugins;
    }

    /**
     * @param array $data
     * @param string $pid
     * @param string $primaryKey
     * @return void
     */
    public static function createMenus(array $data, string $pid = 'parent_id', string $primaryKey = 'id'): void
    {
        $class = '\Modules\Common\Support\ImportPermissions';

        if (class_exists($class)) {
            $importPermission = new $class;

            $importPermission->import($data, $pid, $primaryKey);
        }
    }

    /**
     * 创建菜单
     *
     * @param string $name 菜单名称
     * @param string $frontRoute 前端路由
     * @param string $icon 菜单 Icon
     * @param string $module 模块，根命名
     * @param string $controller 控制
     * @param string $controllerMethod 控制器方法
     * @param string $component 组件
     * @param int $type 类型
     * @param array $children 子菜单
     * @param string $activeMenu 激活菜单
     * @param array $extra 额外数据
     * @return array
     */
    public static function createMenu(string $name, string $frontRoute,
                                      string $module, string $icon = '',
                                      string $controller = '', string $controllerMethod = '',
                                      int $type = 1, string $component = '', string $activeMenu = '',
                                      array $children = [],
                                      array $extra = []
    ): array
    {
        return array_merge([
            'permission_name' => $name,
            'route' => $frontRoute,
            'icon' => $icon,
            'module' => $module,
            'permission_mark' => $controller ? $controller . '@' . $controllerMethod : '',
            'component' => $component,
            'type' => $type,
            'active_menu' => $activeMenu,
            'children' => $children,
        ], $extra);
    }

    /**
     * 插件试图
     *
     * @param string $plugin
     * @param string $entry
     * @return string
     */
    public static function view(string $plugin, string $entry): string
    {
        return url("api/plugins/$plugin/$entry");
    }
}
