<?php

namespace Catch\Plugin\Http\Controllers;

use Catch\Base\CatchController as Controller;
use Catch\Exceptions\FailedException;
use Catch\Plugin\Services\PluginApiService;
use Catch\Plugin\Services\PluginInstallService;
use Catch\Plugin\Support\InstalledPluginManager;
use Catch\Support\SseResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PluginController extends Controller
{
    public function __construct(
        protected PluginApiService $pluginApi,
        protected PluginInstallService $installService
    ) {}

    /**
     * 登录获取 Token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $result = $this->pluginApi->login(
            $request->get('email'),
            $request->get('password')
        );

        if (! $result['success']) {
            throw new FailedException('登录失败');
        }

        $result['data']['token'] = base64_encode($result['data']['token']);

        return $result['data'];
    }

    /**
     * 登出
     */
    public function logout(Request $request)
    {
        $token = $request->get('token');

        if (!$token) {
            throw new FailedException('Token 不能为空');
        }

        $result = $this->pluginApi->logout($token);

        if (!$result) {
            throw new FailedException('登出失败');
        }

        return $result;
    }

    /**
     * 获取当前用户信息
     */
    public function user(Request $request)
    {
        $token = $request->get('token');

        if (!$token) {
            throw new FailedException('Token 不能为空');
        }

        $result = $this->pluginApi->getUser($token);

        if (!$result) {
            throw new FailedException('获取用户信息失败');
        }

        return $result;
    }

    /**
     * 获取分类列表（token 可选）
     */
    public function categories(Request $request)
    {
        $token = $request->get('token', '');

        $result = $this->pluginApi->getCategories($token);

        if (!$result) {
            throw new FailedException('获取分类列表失败');
        }

        return $result;
    }

    /**
     * 获取插件列表（token 可选）
     */
    public function index(Request $request)
    {
        $token = $request->get('token', '');

         $filters = $request->only([
            'title',
            'category_id',
            'is_free',
            'is_official',
            'per_page',
            'page'
        ]);

        $result = $this->pluginApi->getPlugins($token, $filters);

        if ($result['success']) {
            $data = collect($result['data']['data'])->map(function ($plugin) {
                $pluginManager = new InstalledPluginManager();
                // 通过 plugin_id 检查是否已安装
                $plugin['is_installed'] = $pluginManager->isInstalledById((string) $plugin['id']);

                // 如果已安装，添加本地安装信息
                if ($plugin['is_installed']) {
                    $localInfo = $pluginManager->getById((string) $plugin['id']);
                    $plugin['installed_version'] = $localInfo['version'] ?? null;
                    $plugin['installed_at'] = $localInfo['installed_at'] ?? null;
                    $plugin['composer_name'] = $localInfo['name'] ?? null; // Composer 包名
                }
                $plugin['detail_url'] = config('plugin.plugin_host') . '/plugins/s/' . $plugin['id'];

                return $plugin;
            })->toArray();

            return new LengthAwarePaginator($data, $result['data']['total'], $result['data']['per_page'], $result['data']['current_page']);
        }

        throw new FailedException('获取插件列表失败');
    }

    /**
     * 获取已安装插件列表
     */
    public function installed()
    {
        $pluginManager = new InstalledPluginManager();
        return $pluginManager->getAll();
    }

    /**
     * 检查插件是否已安装
     */
    public function checkInstalled(Request $request)
    {
        $pluginId = $request->get('plugin_id');
        $name = $request->get('name');

        $pluginManager = new InstalledPluginManager();

        if ($pluginId) {
            return ['installed' => $pluginManager->isInstalledById($pluginId)];
        }

        if ($name) {
            return ['installed' => $pluginManager->isInstalled($name)];
        }

        return ['installed' => false];
    }

    /**
     * SSE 流式安装插件
     */
    public function installStream(Request $request)
    {
        $token = $request->get('token');
        $id = $request->get('id');
        $version = $request->get('version');
        $name = $request->get('name'); // Composer 包名
        $type = $request->get('type', 'library'); // 插件类型

        return SseResponse::create(function (SseResponse $sse) use ($token, $id, $name, $version, $type) {
            if (!$token) {
                $sse->error('认证信息丢失');
                return;
            }

            if (!$name) {
                $sse->error('缺少包名信息');
                return;
            }

            if (!$this->pluginApi->checkPermission($token, $id, $version)) {
                $sse->error('😭暂无安装权限, 请到官网购买该插件之后再来安装');
                return;
            }

            $sse->log('开始安装插件...');

            $result = $this->installService->install(
                $name,       // Composer 包名
                $version,    // 版本
                $id,         // 插件 ID（用于记录）
                fn($step, $percent, $message) => $sse->progress($step, $percent, $message),
                fn($message, $type) => $sse->log($message, $type),
                $type,       // 插件类型
                $token      // 认证 Token（下载时需要）
            );

            $sse->complete($result);
        });
    }

    /**
     * SSE 流式卸载插件
     */
    public function uninstallStream(Request $request)
    {
        $name = $request->get('name');

        return SseResponse::create(function (SseResponse $sse) use ($name) {
            if (!$name) {
                $sse->error('包名不能为空');
                return;
            }

            $sse->log('开始卸载插件...');

            $result = $this->installService->uninstall(
                $name,
                fn($step, $percent, $message) => $sse->progress($step, $percent, $message),
                fn($message, $type) => $sse->log($message, $type)
            );

            $sse->complete($result);
        });
    }
}
