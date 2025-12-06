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
}
