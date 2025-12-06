# CatchAdmin 插件管理

CatchAdmin 的插件管理系统，提供插件的安装、卸载、启用、禁用等功能。

## 安装

```bash
composer require catchadmin/plugin
```

## 功能

- 插件在线市场
- 本地插件管理
- 插件安装/卸载/启用/禁用
- 插件生命周期钩子（Hook）
- 插件初始化命令

## 命令

### 初始化插件

```bash
php artisan catch:plugin-init
```

交互式创建新插件，包括：
- 插件标题和包名
- 插件类型（library / plugin/ module/ project）
- ServiceProvider（可选）
- Hook 文件（可选）

### 其他命令

```bash
# 安装插件
php artisan catch:plugin-install

# 打包插件
php artisan catch:plugin-pack
```

## Hook 系统

插件可以定义生命周期钩子：

```php
// src/Hook.php
class Hook
{
    public static function afterInstall(array $pluginInfo): void
    {
        // 安装后执行
        Artisan::call('migrate', [
            '--path' => 'vendor/my/plugin/database/migrations',
        ]);
    }

    public static function beforeUninstall(array $pluginInfo): void
    {
        // 卸载前执行
    }
}
```

详细文档请参考 [catchadmin/plugin-hook](https://github.com/catch-admin/plugin-hook)。

## 许可证

MIT
