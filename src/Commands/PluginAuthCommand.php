<?php

namespace Catch\Plugin\Commands;

use Catch\Plugin\Services\PluginApiService;
use Catch\Plugin\Support\ComposerAuth;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class PluginAuthCommand extends Command
{
    protected $signature = 'catch:plugin-auth';

    protected $description = '生成插件认证凭据';

    public function handle(): void
    {
        $email = text('邮箱', required: true, validate: 'email');

        $password = text('密码', required: true);

        $pluginApi = new PluginApiService();
        $result = $pluginApi->login(
            $email,
            $password
        );

        if (! $result['success']) {
            $this->error('登录失败，请检查邮箱和密码');
        } else {
            $composerAuth = new ComposerAuth();
            $result['data']['token'] = base64_encode($result['data']['token']);

            $composerAuth->token(config('plugin.plugin_host'), $result['data']['token']);

            $this->info('凭据已生成，可以直接使用 composer 安装插件');
        }
    }
}
