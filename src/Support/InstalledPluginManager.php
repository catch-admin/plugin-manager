<?php

namespace Catch\Plugin\Support;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

/**
 * 已安装插件管理器
 *
 * 使用 JSON 文件记录已安装的插件信息
 * 注意：此类需要在 Composer 环境中工作，因此使用原生 PHP 文件操作
 */
class InstalledPluginManager
{
    /**
     * 存储文件路径
     */
    protected string $storagePath;

    /**
     * 缓存的插件数据
     */
    protected ?array $cache = null;

    public function __construct(?string $storagePath = null)
    {
        if ($storagePath) {
            $this->storagePath = $storagePath;
        } elseif (function_exists('config')) {
            $this->storagePath = config('plugin.installed_file');
        } else {
            // Composer 环境下使用默认路径
            $this->storagePath = getcwd() . '/storage/packages/plugins.json';
        }
    }

    /**
     * 检查插件是否已安装
     *
     * @param string $name 包名，如 catchadmin/plugin
     * @return bool
     * @throws FileNotFoundException
     */
    public function isInstalled(string $name): bool
    {
        $plugins = $this->getAll();
        return isset($plugins[$name]);
    }

    /**
     * 通过插件 ID 检查是否已安装
     *
     * @param string $pluginId API 返回的插件 ID
     * @return bool
     * @throws FileNotFoundException
     */
    public function isInstalledById(string $pluginId): bool
    {
        $plugins = $this->getAll();
        foreach ($plugins as $plugin) {
            if (($plugin['plugin_id'] ?? '') === $pluginId) {
                return true;
            }
        }
        return false;
    }

    /**
     * 添加已安装插件记录
     *
     * @param array $data 插件信息
     *   - name: 包名 (必需)
     *   - plugin_id: API 插件 ID
     *   - version: 版本号
     *   - path: 安装路径
     * @return bool
     * @throws FileNotFoundException
     */
    public function add(array $data): bool
    {
        if (empty($data['name'])) {
            return false;
        }

        $plugins = $this->getAll();

        $plugins[$data['name']] = [
            'plugin_id' => $data['plugin_id'] ?? '',
            'version' => $data['version'] ?? '',
            'type' => $data['type'] ?? 'library',
            'path' => $data['path'] ?? '',
            'installed_at' => date('Y-m-d H:i:s'),
        ];

        return $this->save($plugins);
    }

    /**
     * 移除已安装插件记录
     *
     * @param string $name 包名
     * @return bool
     * @throws FileNotFoundException
     */
    public function remove(string $name): bool
    {
        $plugins = $this->getAll();

        if (!isset($plugins[$name])) {
            return true; // 不存在视为成功
        }

        unset($plugins[$name]);
        return $this->save($plugins);
    }

    /**
     * 获取所有已安装插件
     *
     * @return array
     * @throws FileNotFoundException
     */
    public function getAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!file_exists($this->storagePath)) {
            $this->cache = [];
            return $this->cache;
        }

        $content = file_get_contents($this->storagePath);
        $data = json_decode($content, true);

        $this->cache = is_array($data) ? $data : [];
        return $this->cache;
    }

    /**
     * 获取单个插件信息
     *
     * @param string $name 包名
     * @return array|null
     * @throws FileNotFoundException
     */
    public function get(string $name): ?array
    {
        $plugins = $this->getAll();
        return $plugins[$name] ?? null;
    }

    /**
     * 通过插件 ID 获取插件信息
     *
     * @param string $pluginId API 插件 ID
     * @return array|null
     * @throws FileNotFoundException
     */
    public function getById(string $pluginId): ?array
    {
        $plugins = $this->getAll();
        foreach ($plugins as $name => $plugin) {
            if (($plugin['plugin_id'] ?? '') === $pluginId) {
                return array_merge(['name' => $name], $plugin);
            }
        }
        return null;
    }

    /**
     * 更新插件信息
     *
     * @param string $name 包名
     * @param array $data 要更新的数据
     * @return bool
     * @throws FileNotFoundException
     */
    public function update(string $name, array $data): bool
    {
        $plugins = $this->getAll();

        if (!isset($plugins[$name])) {
            return false;
        }

        $plugins[$name] = array_merge($plugins[$name], $data);
        $plugins[$name]['updated_at'] = date('Y-m-d H:i:s');

        return $this->save($plugins);
    }

    /**
     * 保存数据到文件
     *
     * @param array $plugins
     * @return bool
     */
    protected function save(array $plugins): bool
    {
        $this->cache = $plugins;

        // 确保目录存在
        $directory = dirname($this->storagePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $content = json_encode($plugins, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return file_put_contents($this->storagePath, $content) !== false;
    }

    /**
     * 清空缓存
     */
    public function clearCache(): void
    {
        $this->cache = null;
    }
}
