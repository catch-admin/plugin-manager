<?php

namespace Catch\Plugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

/**
 * 插件初始化命令
 *
 * 类似 composer init，交互式创建插件项目
 */
class PluginInitCommand extends Command
{
    protected $signature = 'catch:plugin-init';

    protected $description = '初始化一个新的插件项目';

    protected array $data = [];

    public function handle(): int
    {
        // 1. 收集信息
        $this->data['title'] = text(
            label: '插件标题',
            placeholder: '我的插件',
            required: true
        );

        $pluginPath = config('plugin.develop');

        $this->data['name'] = text(
            label: '包名 (vendor/package)',
            placeholder: 'catch/my-plugin',
            required: true,
            validate: function ($v) use ($pluginPath) {
                if (!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $v)) {
                    return '格式: vendor/package';
                }
                if (File::exists($pluginPath . '/' . $v)) {
                    return '插件已存在';
                }
                return null;
            }
        );

        // 路径基于包名
        $path = $pluginPath . '/' . $this->data['name'];

        $this->data['description'] = text(
            label: '描述',
            placeholder: '插件功能描述',
            required: false
        );

        $this->data['version'] = text(
            label: '版本号',
            default: '1.0.0',
            validate: fn($v) => preg_match('/^\d+\.\d+\.\d+$/', $v) ? null : '格式: x.x.x'
        );

        $this->data['email'] = text(
            label: '作者邮箱',
            required: false
        );

        $this->data['author'] = text(
            label: '作者名称',
            required: false
        );

        // 2. 选择类型
        $this->data['type'] = select(
            label: '插件类型',
            options: [
                'library' => 'library - Composer 包（通过 composer require 安装）',
                'self-plugin' => 'self-plugin - 自发布插件（仅执行 Hook，不需要 Composer）',
            ],
            default: 'library'
        );

        $isSelfPlugin = $this->data['type'] === 'self-plugin';

        // 3. 询问功能
        $needLaravel = !$isSelfPlugin && confirm('是否需要 Laravel 服务提供者？', true);
        $needHook = confirm('是否需要生成 Hook 文件？', $isSelfPlugin ? true : true);

        // 4. 生成文件
        $this->info('');
        File::ensureDirectoryExists($path);
        File::ensureDirectoryExists($path . '/src');

        $this->generateComposerJson($path, $needLaravel, $needHook);
        $this->generateReadme($path);

        if ($needLaravel) {
            $this->generateServiceProvider($path);
        }

        if ($needHook) {
            $this->generateHookFile($path, $isSelfPlugin);
        }

        // 更新根目录 composer.json（仅 library 类型）
        if (!$isSelfPlugin) {
            $this->updateRootComposer();

            // 运行 composer dump-autoload
            spin(
                callback: fn () => app(Composer::class)->setWorkingPath(base_path())->dumpAutoloads(),
                message: '正在更新自动加载...'
            );
        }

        // 显示结果表格
        $this->displayResultTable($path, $needLaravel, $needHook, $isSelfPlugin);

        return self::SUCCESS;
    }

    /**
     * 更新根目录 composer.json
     */
    protected function updateRootComposer(): void
    {
        $pluginDir = config('plugin.directory'); // 相对路径
        $composer = app(Composer::class)->setWorkingPath(base_path());
        $namespace = $this->getNamespace() . '\\';
        $relativePath = $pluginDir . '/' . $this->data['name'] . '/src/';

        $composer->modify(function ($composerJson) use ($namespace, $relativePath) {
            // 添加 PSR-4 命名空间
            if (!isset($composerJson['autoload']['psr-4'][$namespace])) {
                $composerJson['autoload']['psr-4'][$namespace] = $relativePath;
            }
            return $composerJson;
        });

        $this->line('  ✓ 更新根目录 composer.json');
    }

    /**
     * 显示结果表格
     */
    protected function displayResultTable(string $path, bool $needLaravel, bool $needHook, bool $isSelfPlugin = false): void
    {
        $this->info('');
        $this->line('  ✅ 插件初始化完成！');
        $this->info('');

        $rows = [
            ['标题', $this->data['title']],
            ['包名', $this->data['name']],
            ['类型', $isSelfPlugin ? 'self-plugin' : 'library'],
            ['版本', $this->data['version']],
            ['路径', $path],
        ];

        if (!$isSelfPlugin) {
            $rows[] = ['命名空间', $this->getNamespace()];
            $rows[] = ['ServiceProvider', $needLaravel ? '是' : '否'];
        }

        $rows[] = ['Hook', $needHook ? '是' : '否'];

        $this->table(['属性', '值'], $rows);
        $this->info('');
    }

    protected function normalizePath(string $path): string
    {
        if (!Str::startsWith($path, ['/','\\']) && !preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }
        return rtrim($path, '/\\');
    }

    protected function getNamespace(): string
    {
        return collect(explode('/', $this->data['name']))
            ->map(fn($p) => Str::studly($p))
            ->implode('\\');
    }

    protected function generateComposerJson(string $path, bool $needLaravel, bool $needHook = false): void
    {
        $namespace = $this->getNamespace();
        $isSelfPlugin = ($this->data['type'] ?? 'library') === 'self-plugin';

        $composer = [
            'name' => $this->data['name'],
            'title' => $this->data['title'],
            'description' => $this->data['description'] ?: $this->data['title'],
            'version' => $this->data['version'],
            'type' => $this->data['type'] ?? config('plugin.default_type'),
            'license' => config('plugin.license'),
            'extra' => [
                'title' => $this->data['title'],
                'version' => $this->data['version'],
            ]
        ];

        // 添加 Hook 类引用
        if ($needHook && !$isSelfPlugin) {
            $composer['extra']['hook'] = $namespace . '\\Hook';
        }

        // library 类型需要 require 和 autoload
        if (!$isSelfPlugin) {
            $composer['require'] = [
                'php' => config('plugin.php_version'),
            ];
            $composer['autoload'] = [
                'psr-4' => [
                    $namespace . '\\' => 'src/',
                ],
            ];
        }

        // 作者信息
        if ($this->data['author'] || $this->data['email']) {
            $composer['authors'] = [
                array_filter([
                    'name' => $this->data['author'] ?: null,
                    'email' => $this->data['email'] ?: null,
                ])
            ];
        }

        // Laravel 服务提供者
        if ($needLaravel) {
            $composer['extra']['laravel']['providers'] = [
                $namespace . '\\ServiceProvider',
            ];
        }

        File::put($path . '/composer.json', json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        $this->info('  ✓ composer.json');
    }

    protected function generateReadme(string $path): void
    {
        $content = "# {$this->data['title']}\n\n{$this->data['description']}\n\n## 安装\n\n```bash\ncomposer require {$this->data['name']}\n```\n";
        File::put($path . '/README.md', $content);
        $this->info('  ✓ README.md');
    }

    protected function generateServiceProvider(string $path): void
    {
        $namespace = $this->getNamespace();
        $content = <<<PHP
<?php

namespace {$namespace};

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
PHP;
        File::put($path . '/src/ServiceProvider.php', $content);
        $this->info('  ✓ src/ServiceProvider.php');
    }

    protected function generateHookFile(string $path, bool $isSelfPlugin = false): void
    {
        $namespace = $this->getNamespace();
        $stubPath = dirname(__DIR__, 2) . '/stubs/hook.php.stub';
        $content = File::get($stubPath);

        // 替换占位符
        $content = str_replace('{{namespace}}', $namespace, $content);
        $content = str_replace('{{name}}', $this->data['name'], $content);

        File::put($path . '/src/Hook.php', $content);
        $this->info('  ✓ src/Hook.php');
    }
}
