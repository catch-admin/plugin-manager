<?php

namespace Catch\Plugin\Commands;

use Catch\Plugin\Enums\PluginType;
use Catch\Plugin\Generators\LibraryGenerator;
use Catch\Plugin\Generators\ModuleGenerator;
use Catch\Plugin\Generators\PluginGenerator;
use Catch\Plugin\Generators\ProjectGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * 插件初始化
 */
class PluginInitCommand extends Command
{
    protected $signature = 'catch:plugin-init';

    protected $description = '初始化一个新的插件项目';

    protected array $generators = [
        'library' => LibraryGenerator::class,
        'module'  => ModuleGenerator::class,
        'plugin'  => PluginGenerator::class,
        'project' => ProjectGenerator::class,
    ];

    public function handle(): int
    {
        // 默认使用 plugin 类型，其他类型暂时隐藏
        // $type = $this->selectType();
        $type = 'plugin';
        
        $data = $this->collectCommonInfo($type);
        $data['type'] = $type;

        $generatorClass = $this->generators[$type] ?? PluginGenerator::class;
        $generator = new $generatorClass($data, $this);
        $generator->generate();

        return self::SUCCESS;
    }

    protected function collectCommonInfo(string $type): array
    {
        $data = [];
        $pluginPath = config('plugin.develop');
        $isModule = $type === PluginType::Module->value;

        $data['title'] = text(
            label: '插件标题',
            placeholder: $isModule ? 'AI 模块' : '我的插件',
            required: true
        );

        $namePlaceholder = $isModule ? 'module/ai' : 'catch/my-plugin';
        $nameLabel = $isModule ? '包名 (module/模块名)' : '包名 (vendor/package)';

        $data['name'] = text(
            label: $nameLabel,
            placeholder: $namePlaceholder,
            required: true,
            validate: function ($v) use ($pluginPath, $isModule) {
                if (!preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $v)) {
                    return '格式: vendor/package';
                }
                if ($isModule && !str_starts_with($v, 'module/')) {
                    return 'module 类型包名必须以 module/ 开头';
                }
                if (File::exists($pluginPath . '/' . $v)) {
                    return '插件已存在';
                }
                return null;
            }
        );

        $data['description'] = text(
            label: '描述',
            placeholder: '插件功能描述',
            required: true
        );

        $data['version'] = text(
            label: '版本号',
            default: '1.0.0',
            validate: fn($v) => preg_match('/^\d+\.\d+\.\d+$/', $v) ? null : '格式: x.x.x'
        );

        $data['email'] = text(
            label: '作者邮箱',
            required: true
        );

        $data['author'] = text(
            label: '作者名称',
            required: true
        );

        return $data;
    }

    /**
     * 选择插件类型（暂时隐藏）
     */
    protected function selectType(): string
    {
        return select(
            label: '插件类型',
            options: [
                PluginType::Library->value => 'library - Composer 包（通过 composer require 安装）',
                PluginType::Module->value  => 'module - 自发布模块（从 modules 目录打包）',
                PluginType::Plugin->value  => 'plugin - 自发布插件（仅执行 Hook）',
                PluginType::Project->value => 'project - 自发布项目（仅执行 Hook）',
            ],
            default: PluginType::Library->value
        );
    }
}
