# CatchAdmin Plugin 开发文档

本文档详细介绍如何为 CatchAdmin 开发插件，包括插件结构、开发流程、生命周期钩子、发布与安装等内容。

---

## 目录

1. [概述](#概述)
2. [快速开始](#快速开始)
3. [插件结构](#插件结构)
4. [composer.json 配置](#composerjson-配置)
5. [生命周期钩子](#生命周期钩子)
6. [Laravel 服务提供者](#laravel-服务提供者)
7. [插件打包](#插件打包)
8. [插件安装与卸载](#插件安装与卸载)
9. [最佳实践](#最佳实践)
10. [常见问题](#常见问题)

---

## 概述

CatchAdmin Plugin 是一套基于 Composer 的插件系统，允许开发者创建独立的功能模块，通过插件市场或本地安装的方式集成到 CatchAdmin 项目中。

### 特性

- 基于 Composer 标准，支持 PSR-4 自动加载
- 支持 Laravel 服务提供者自动注册
- 完整的生命周期钩子（安装前/后、卸载前/后）
- 流式安装/卸载进度显示
- 命令行工具支持
- 支持两种插件类型：`library` 和 `self-plugin`

### 插件类型

CatchAdmin 支持两种类型的插件：

| 类型 | 说明 | 适用场景 |
|------|------|----------|
| `library` | Composer 包，通过 `composer require` 安装 | 需要 PHP 类、服务提供者的功能插件 |
| `self-plugin` | 自发布插件，仅执行 Hook，不需要 Composer | 模板、配置、静态资源等发布类插件 |

---

## 快速开始

### 创建插件

使用 `plugin:init` 命令交互式创建插件：

```bash
php artisan plugin:init
```

按提示输入：

1. **插件标题** - 插件的显示名称
2. **包名** - 格式 `vendor/package`，如 `catch/user-center`
3. **描述** - 插件功能描述
4. **版本号** - 语义化版本，如 `1.0.0`
5. **作者邮箱** - 可选
6. **作者名称** - 可选
7. **插件类型** - `library`（Composer 包）或 `self-plugin`（自发布插件）
8. **是否需要 Laravel 服务提供者** - 仅 `library` 类型，推荐选择 `是`
9. **是否需要生成 Hook 文件** - 如需自定义安装/卸载逻辑，选择 `是`

创建完成后，插件目录结构如下：

```
plugins/
└── catch/
    └── user-center/
        ├── composer.json
        ├── README.md
        ├── hook.php
        └── src/
            └── ServiceProvider.php
```

### 目录约定

插件固定存放在项目根目录的 `plugins/` 文件夹下，目录结构遵循包名：

```
plugins/{vendor}/{package}/
```

例如包名 `catch/user-center` 对应目录 `plugins/catch/user-center/`。

---

## 插件结构

### 标准目录结构

```
plugins/vendor/package/
├── composer.json          # 必需，包配置文件
├── README.md              # 推荐，说明文档
├── hook.php               # 可选，生命周期钩子
├── src/                   # 必需，PHP 源代码
│   ├── ServiceProvider.php
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Support/
├── config/                # 可选，配置文件
├── database/              # 可选，数据库文件
│   ├── migrations/
│   └── seeders/
├── resources/             # 可选，资源文件
│   ├── views/
│   └── lang/
├── routes/                # 可选，路由文件
│   ├── web.php
│   └── api.php
└── tests/                 # 推荐，测试文件
```

### 文件说明

| 文件/目录 | 必需 | 说明 |
|----------|------|------|
| `composer.json` | ✅ | Composer 配置，定义包名、依赖、自动加载等 |
| `src/` | ✅ | PHP 源代码目录 |
| `src/ServiceProvider.php` | 推荐 | Laravel 服务提供者，用于注册服务、路由等 |
| `hook.php` | 可选 | 生命周期钩子，处理安装/卸载逻辑 |
| `README.md` | 推荐 | 插件说明文档 |
| `config/` | 可选 | 配置文件，可通过服务提供者发布 |
| `database/migrations/` | 可选 | 数据库迁移文件 |
| `routes/` | 可选 | 路由定义文件 |
| `resources/` | 可选 | 视图、语言包等资源文件 |

---

## composer.json 配置

### 完整示例

```json
{
    "name": "catch/user-center",
    "title": "用户中心",
    "description": "用户中心功能模块，包含用户管理、角色权限等功能",
    "version": "1.0.0",
    "type": "library",
    "license": "proprietary",
    "authors": [
        {
            "name": "Your Name",
            "email": "your@email.com"
        }
    ],
    "require": {
        "php": ">=8.1"
    },
    "autoload": {
        "psr-4": {
            "Catch\\UserCenter\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Catch\\UserCenter\\ServiceProvider"
            ]
        }
    }
}
```

### 字段说明

| 字段 | 必需 | 说明 |
|------|------|------|
| `name` | ✅ | 包名，格式 `vendor/package`，全小写，可用 `-` 分隔 |
| `title` | ✅ | 插件标题，用于显示 |
| `description` | 推荐 | 插件描述 |
| `version` | ✅ | 版本号，语义化版本格式 `x.y.z` |
| `type` | ✅ | 固定为 `library` |
| `license` | ✅ | 许可证，商业插件使用 `proprietary` |
| `authors` | 可选 | 作者信息 |
| `require` | 推荐 | 依赖声明，至少声明 PHP 版本要求 |
| `autoload.psr-4` | ✅ | PSR-4 自动加载配置 |
| `extra.laravel.providers` | 推荐 | Laravel 服务提供者自动发现 |

### 命名空间约定

命名空间应与包名对应，使用 StudlyCase：

- 包名: `catch/user-center`
- 命名空间: `Catch\UserCenter`

---

## 生命周期钩子

### hook.php 文件

`hook.php` 文件定义插件在安装和卸载时执行的自定义逻辑。

```php
<?php

/**
 * 插件生命周期钩子
 */
return [
    /**
     * 安装前执行
     * 
     * @param array $context 上下文信息
     * @return bool 返回 false 将阻止安装
     */
    'before' => function (array $context): bool {
        // 检查系统要求
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            throw new \RuntimeException('需要 PHP 8.1 或更高版本');
        }
        
        // 检查依赖
        if (!extension_loaded('bcmath')) {
            throw new \RuntimeException('需要安装 bcmath 扩展');
        }
        
        return true;
    },

    /**
     * 安装后执行
     * 
     * @param array $context 上下文信息
     */
    'after' => function (array $context): void {
        // 发布配置文件
        \Artisan::call('vendor:publish', [
            '--provider' => 'Catch\\UserCenter\\ServiceProvider',
            '--tag' => 'config'
        ]);
        
        // 运行数据库迁移
        \Artisan::call('migrate', [
            '--path' => $context['plugin_path'] . '/database/migrations'
        ]);
        
        // 运行数据填充
        \Artisan::call('db:seed', [
            '--class' => 'Catch\\UserCenter\\Database\\Seeders\\DatabaseSeeder'
        ]);
    },

    /**
     * 卸载前执行
     * 
     * @param array $context 上下文信息
     * @return bool 返回 false 将阻止卸载
     */
    'beforeUninstall' => function (array $context): bool {
        // 检查是否可以安全卸载
        // 例如：检查是否有用户正在使用该功能
        
        return true;
    },

    /**
     * 卸载后执行
     * 
     * @param array $context 上下文信息
     */
    'afterUninstall' => function (array $context): void {
        // 清理配置文件
        $configPath = config_path('user-center.php');
        if (file_exists($configPath)) {
            unlink($configPath);
        }
        
        // 回滚数据库迁移（谨慎使用）
        // \Artisan::call('migrate:rollback', [
        //     '--path' => $context['plugin_path'] . '/database/migrations'
        // ]);
    },
];
```

### 上下文信息

钩子函数接收的 `$context` 数组包含以下信息：

| 键 | 说明 |
|---|------|
| `plugin_path` | 插件安装路径 |
| `composer_data` | composer.json 解析后的数据 |
| `version` | 插件版本号 |
| `plugin_id` | 插件市场 ID（从市场安装时） |

### 注意事项

1. **异常处理**: 钩子中抛出异常会中断安装/卸载流程，异常消息会显示给用户
2. **返回值**: `before` 和 `beforeUninstall` 钩子返回 `false` 会阻止操作继续
3. **幂等性**: 钩子代码应该是幂等的，多次执行不应产生副作用
4. **错误恢复**: 建议在 `after` 钩子中处理可能失败的操作，并在失败时抛出异常

---

## Laravel 服务提供者

### 基础结构

```php
<?php

namespace Catch\UserCenter;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 合并配置
        $this->mergeConfigFrom(
            __DIR__ . '/../config/user-center.php',
            'user-center'
        );
        
        // 注册单例
        $this->app->singleton(UserService::class, function ($app) {
            return new UserService($app['config']['user-center']);
        });
    }

    /**
     * 引导服务
     */
    public function boot(): void
    {
        // 加载路由
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        
        // 加载视图
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'user-center');
        
        // 加载迁移
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // 加载语言包
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'user-center');
        
        // 发布配置
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/user-center.php' => config_path('user-center.php'),
            ], 'config');
            
            // 发布视图
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/user-center'),
            ], 'views');
            
            // 注册命令
            $this->commands([
                Commands\SyncUsersCommand::class,
            ]);
        }
    }
}
```

### 常用方法

| 方法 | 说明 |
|------|------|
| `mergeConfigFrom()` | 合并配置文件 |
| `loadRoutesFrom()` | 加载路由文件 |
| `loadViewsFrom()` | 加载视图目录 |
| `loadMigrationsFrom()` | 加载迁移文件 |
| `loadTranslationsFrom()` | 加载语言包 |
| `publishes()` | 发布资源文件 |
| `commands()` | 注册 Artisan 命令 |

---

## 插件打包

### 使用命令打包

```bash
php artisan plugin:pack
```

选择要打包的插件后，将在 `plugins/.dist/` 目录生成 zip 文件。

### 打包内容

打包会自动排除以下目录和文件：

- `vendor/` - Composer 依赖
- `node_modules/` - NPM 依赖
- `.git/` - Git 版本控制
- `.idea/` - IDE 配置
- `.vscode/` - VS Code 配置
- `.DS_Store` - macOS 系统文件
- `Thumbs.db` - Windows 系统文件

### 输出格式

```
{vendor}-{package}-{version}.zip

例如: catch-user-center-1.0.0.zip
```

### zip 文件结构

```
user-center-1.0.0.zip
├── composer.json
├── README.md
├── hook.php
├── src/
│   └── ServiceProvider.php
├── config/
├── database/
├── resources/
└── routes/
```

---

## 插件安装与卸载

### 从插件市场安装

1. 进入后台插件市场页面
2. 浏览或搜索需要的插件
3. 点击「安装」按钮
4. 等待安装完成

安装过程会显示实时进度和日志：

- 下载插件包
- 解压文件
- 解析 composer.json
- 执行 `before` 钩子
- 运行 `composer require`
- 执行 `after` 钩子
- 记录安装信息

### 从本地安装

1. 将插件 zip 文件上传到服务器
2. 解压到 `plugins/{vendor}/{package}/` 目录
3. 运行 `composer dump-autoload` 更新自动加载
4. 手动执行 `after` 钩子中的操作（如迁移、发布配置等）

### 卸载插件

1. 进入后台已安装插件列表
2. 点击「卸载」按钮
3. 确认卸载
4. 等待卸载完成

卸载过程：

- 执行 `beforeUninstall` 钩子
- 运行 `composer remove`
- 执行 `afterUninstall` 钩子
- 删除安装记录

---

## 最佳实践

### 1. 配置管理

```php
// config/user-center.php
return [
    'enabled' => env('USER_CENTER_ENABLED', true),
    'cache_ttl' => env('USER_CENTER_CACHE_TTL', 3600),
];
```

使用环境变量使配置可在部署时调整，避免修改代码。

### 2. 数据库迁移

```php
// database/migrations/2024_01_01_000001_create_user_profiles_table.php
Schema::create('user_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('avatar')->nullable();
    $table->timestamps();
});
```

- 使用有意义的文件名
- 添加适当的索引和外键
- 考虑数据回滚策略

### 3. 路由定义

```php
// routes/api.php
Route::prefix('api/user-center')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
    });
```

- 使用统一的前缀避免路由冲突
- 添加适当的中间件
- 遵循 RESTful 规范

### 4. 服务封装

```php
// src/Services/UserService.php
class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly CacheManager $cache
    ) {}
    
    public function getProfile(int $userId): ?UserProfile
    {
        return $this->cache->remember(
            "user_profile:{$userId}",
            3600,
            fn () => $this->repository->findProfile($userId)
        );
    }
}
```

- 使用依赖注入
- 分离业务逻辑和数据访问
- 合理使用缓存

### 5. 异常处理

```php
// src/Exceptions/UserNotFoundException.php
class UserNotFoundException extends \Exception
{
    public function __construct(int $userId)
    {
        parent::__construct("User not found: {$userId}");
    }
}
```

定义业务异常类，提供清晰的错误信息。

### 6. 测试覆盖

```php
// tests/Feature/ProfileTest.php
class ProfileTest extends TestCase
{
    public function test_can_get_profile()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->getJson('/api/user-center/profile');
        
        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'avatar']]);
    }
}
```

编写单元测试和功能测试，确保插件质量。

---

## 常见问题

### Q: 如何处理插件之间的依赖？

在 `composer.json` 的 `require` 中声明依赖：

```json
{
    "require": {
        "catch/common": "^1.0"
    }
}
```

### Q: 如何在安装时执行数据库迁移？

在 `hook.php` 的 `after` 钩子中调用：

```php
'after' => function (array $context): void {
    \Artisan::call('migrate', [
        '--path' => 'plugins/catch/user-center/database/migrations'
    ]);
}
```

### Q: 如何处理插件升级？

目前需要手动处理：

1. 卸载旧版本
2. 安装新版本
3. 运行迁移更新数据库

### Q: 如何调试插件安装问题？

1. 查看 Laravel 日志：`storage/logs/laravel.log`
2. 安装时观察终端输出的 Composer 日志
3. 检查 `hook.php` 中的逻辑是否正确

### Q: 插件卸载后数据如何处理？

建议在 `afterUninstall` 钩子中：

- **保守策略**: 只清理配置文件，保留数据库数据
- **激进策略**: 回滚迁移，删除所有相关数据

根据插件性质选择合适的策略。

### Q: 如何支持多语言？

使用 Laravel 语言包：

```php
// ServiceProvider
$this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'user-center');

// 使用
__('user-center::messages.welcome')
```

### Q: self-plugin 和 library 有什么区别？

| 特性 | library | self-plugin |
|------|---------|-------------|
| 安装方式 | `composer require` | 仅执行 Hook |
| PSR-4 自动加载 | ✅ 支持 | ❌ 不需要 |
| 服务提供者 | ✅ 支持 | ❌ 不需要 |
| 卸载方式 | `composer remove` | 删除插件目录 |
| 适用场景 | PHP 功能代码 | 模板、配置、资源发布 |

### Q: 什么时候使用 self-plugin？

适合以下场景：

1. **模板插件** - 提供 Blade/Vue 模板文件
2. **配置插件** - 提供预设配置文件
3. **静态资源** - 提供 CSS/JS/图片等资源
4. **数据初始化** - 提供数据库 Seeder

### Q: self-plugin 的 hook.php 如何编写？

```php
<?php

use Illuminate\Support\Facades\File;

return [
    'after' => function (array $context): void {
        $pluginPath = $context['plugin_path'];

        // 发布配置文件
        File::copy(
            $pluginPath . '/config/example.php',
            config_path('example.php')
        );

        // 发布视图
        File::copyDirectory(
            $pluginPath . '/resources/views',
            resource_path('views/vendor/example')
        );

        // 执行迁移
        \Artisan::call('migrate', [
            '--path' => $pluginPath . '/database/migrations'
        ]);
    },

    'afterUninstall' => function (array $context): void {
        // 清理发布的文件
        File::delete(config_path('example.php'));
        File::deleteDirectory(resource_path('views/vendor/example'));
    },
];
```

---

## 附录

### 命令参考

| 命令 | 说明 |
|------|------|
| `php artisan plugin:init` | 交互式创建新插件 |
| `php artisan plugin:pack` | 打包插件为 zip 文件 |

### 相关资源

- [Composer 文档](https://getcomposer.org/doc/)
- [Laravel 服务提供者](https://laravel.com/docs/providers)
- [Laravel 包开发](https://laravel.com/docs/packages)
