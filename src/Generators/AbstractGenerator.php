<?php

namespace Catch\Plugin\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * 插件生成器抽象基类.
 */
abstract class AbstractGenerator
{
    /**
     * 插件数据.
     */
    protected array $data;

    /**
     * 插件路径.
     */
    protected string $path;

    /**
     * 命令实例（用于输出）.
     */
    protected Command $command;

    public function __construct(array $data, Command $command)
    {
        $this->data = $data;
        $this->command = $command;
        $this->path = config('plugin.develop') . '/' . $data['name'];
    }

    /**
     * 执行生成.
     */
    public function generate(): void
    {
        // 创建基础目录
        File::ensureDirectoryExists($this->path);
        File::ensureDirectoryExists($this->path . '/src');

        // 收集额外信息
        $this->collectExtraInfo();

        // 生成通用文件
        $this->generateComposerJson();
        $this->generateReadme();

        // 生成类型特有文件
        $this->generateExtraFiles();

        // 后置处理
        $this->afterGenerate();

        // 显示结果
        $this->displayResult();
    }

    /**
     * 收集类型特有的额外信息.
     */
    abstract protected function collectExtraInfo(): void;

    /**
     * 生成类型特有的文件.
     */
    abstract protected function generateExtraFiles(): void;

    /**
     * 获取 composer.json 的 extra 配置.
     */
    abstract protected function getComposerExtra(): array;

    /**
     * 获取结果表格行.
     */
    abstract protected function getResultRows(): array;

    /**
     * 后置处理.
     */
    protected function afterGenerate(): void
    {
        $this->ensurePathRepository();
    }

    /**
     * 确保主项目 composer.json 中存在 path repository 配置.
     */
    protected function ensurePathRepository(): void
    {
        $composerPath = base_path('composer.json');
        $composer = json_decode(File::get($composerPath), true);

        $developPath = config('plugin.develop');
        $relativePath = './' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $developPath);
        $relativePath = str_replace('\\', '/', $relativePath) . '/*';

        $repositories = $composer['repositories'] ?? [];
        foreach ($repositories as $repo) {
            if (($repo['type'] ?? '') === 'path' && ($repo['url'] ?? '') === $relativePath) {
                return;
            }
        }

        $composer['repositories'][] = [
            'type' => 'path',
            'url' => $relativePath,
        ];

        File::put($composerPath, json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        $this->command->info('  ✓ Added path repository to composer.json');
    }

    /**
     * 生成 composer.json.
     */
    protected function generateComposerJson(): void
    {
        $composer = [
            'name' => $this->data['name'],
            'title' => $this->data['title'],
            'description' => $this->data['description'] ?: $this->data['title'],
            'version' => $this->data['version'],
            'type' => $this->data['type'],
            'license' => config('plugin.license'),
            'extra' => array_merge([
                'title' => $this->data['title'],
                'version' => $this->data['version'],
            ], $this->getComposerExtra()),
        ];

        // 作者信息
        if ($this->data['author'] || $this->data['email']) {
            $composer['authors'] = [
                array_filter([
                    'name' => $this->data['author'] ?: null,
                    'email' => $this->data['email'] ?: null,
                ]),
            ];
        }

        if (! empty($this->data['autoload_path'])) {
            $composer['autoload'] = [
                'psr-4' => [
                    $this->getNamespace() . '\\' => $this->data['autoload_path'],
                ],
            ];
        }

        // 子类可添加额外配置
        $composer = $this->extendComposerJson($composer);

        File::put($this->path . '/composer.json', json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        $this->command->info('  ✓ composer.json');
    }

    /**
     * 扩展 composer.json 配置.
     */
    protected function extendComposerJson(array $composer): array
    {
        return $composer;
    }

    /**
     * 生成 README.md.
     */
    protected function generateReadme(): void
    {
        $content = "# {$this->data['title']}\n\n{$this->data['description']}\n\n## 安装\n\n```bash\ncomposer require {$this->data['name']}\n```\n";
        File::put($this->path . '/README.md', $content);
        $this->command->info('  ✓ README.md');
    }

    /**
     * 生成 Hook 文件（根目录 hook.php）.
     */
    protected function generateHookFile(): void
    {
        $stubPath = dirname(__DIR__, 2) . '/stubs/hook.php.stub';
        $content = File::get($stubPath);

        File::put($this->path . '/hook.php', $content);
        $this->command->info('  ✓ hook.php');
    }

    /**
     * 生成路由文件（routes/admin.php）.
     */
    protected function generateRouteFile(): void
    {
        File::ensureDirectoryExists($this->path . '/routes');

        $prefix = $this->getRoutePrefix();
        $content = <<<PHP
<?php

use Illuminate\Support\Facades\Route;

Route::prefix(config('catch.route.prefix') . '/{$prefix}')
    ->middleware(config('catch.route.middlewares', []))
    ->group(function () {
    // next
});
PHP;
        File::put($this->path . '/routes/admin.php', $content);
        $this->command->info('  ✓ routes/admin.php');
    }

    /**
     * 从包名获取路由前缀
     */
    protected function getRoutePrefix(): string
    {
        $parts = explode('/', $this->data['name']);

        return end($parts);
    }

    /**
     * 生成 ServiceProvider.
     */
    protected function generateServiceProvider(): void
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
        File::put($this->path . '/src/ServiceProvider.php', $content);
        $this->command->info('  ✓ src/ServiceProvider.php');
    }

    /**
     * 获取命名空间.
     */
    protected function getNamespace(): string
    {
        return collect(explode('/', $this->data['name']))
            ->map(fn ($p) => Str::studly($p))
            ->implode('\\');
    }

    /**
     * 获取插件路径.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 显示结果.
     */
    protected function displayResult(): void
    {
        $this->command->line('  ✅ 插件初始化完成！');
        $this->command->info('');

        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->path);

        $rows = array_merge([
            ['标题', $this->data['title']],
            ['包名', $this->data['name']],
            ['类型', $this->data['type']],
            ['版本', $this->data['version']],
            ['路径', $relativePath],
        ], $this->getResultRows());

        $this->command->table(['属性', '值'], $rows);
    }
}
