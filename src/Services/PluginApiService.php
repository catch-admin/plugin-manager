<?php

namespace Catch\Plugin\Services;

use Catch\Exceptions\FailedException;
use Catch\Plugin\Support\ComposerAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PluginApiService
{
    private string $baseUrl;
    private int $timeout = 300;

    protected array $options = [
        'verify' => false,
        'proxy' => false
    ];

    public function __construct()
    {
        $this->baseUrl = config('plugin.plugin_host') . '/api';
    }

    /**
     * @param string $email
     * @param string $password
     * @return array|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function login(string $email, string $password): ?array
    {
        $response = Http::timeout($this->timeout)
            ->withOptions($this->options)
            ->post("{$this->baseUrl}/plugins/auth/login", [
                'email' => $email,
                'password' => $password,
            ]);

        if ($response->json('success')) {
            $composerAuth = new ComposerAuth();
            $composerAuth->token(config('plugin.plugin_host'), $response->json('data')['token']);
        }

        return $response->json();
    }

    /**
     * 登出
     */
    public function logout(string $token): ?array
    {
        $token = base64_decode($token);

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions($this->options)
                ->withToken($token)
                ->post("{$this->baseUrl}/plugins/auth/logout");

            return $response->json();

        } catch (\Exception $e) {
            Log::error('插件 API 登出异常', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 获取当前用户信息
     */
    public function getUser(string $token): ?array
    {
        $token = base64_decode($token);

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions($this->options)
                ->withToken($token)
                ->get("{$this->baseUrl}/plugins/auth/user");

            return $response->json();

        } catch (\Exception $e) {
            Log::error('获取用户信息异常', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 获取分类列表
     */
    public function getCategories(string $token): ?array
    {
        $token = base64_decode($token);

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions($this->options)
                ->withToken($token)
                ->get("{$this->baseUrl}/plugins/categories");

            return $response->json();

        } catch (\Exception $e) {
            Log::error('获取分类列表异常', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 获取插件列表
     */
    public function getPlugins(string $token, array $filters = []): ?array
    {
        $token = base64_decode($token);

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions($this->options)
                ->withToken($token)
                ->get("{$this->baseUrl}/plugins", $filters);

            return $response->json();

        } catch (\Exception $e) {
            Log::error('获取插件列表异常', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 下载插件
     *
     * @param string $token
     * @param string $pluginId
     * @param $destination
     * @param string|null $version
     * @return mixed
     */
    public function downloadPlugin(string $token, string $pluginId, $destination, ?string $version = null): mixed
    {
        $token = base64_decode($token);

        try {
            // 构建请求 URL
            $url = "{$this->baseUrl}/plugins/{$pluginId}/download";
            $queryParams = $version ? ['version' => $version] : [];

            // 使用 Guzzle 直接发送请求，避开 Laravel HTTP Client 事件系统
            $client = new Client([
                'timeout' => $this->timeout,
                'read_timeout' => $this->timeout,
                'verify' => $this->options['verify'] ?? false,
                'proxy' => $this->options['proxy'] ?? false,
            ]);

            $response = $client->request('GET', $url, [
                'query' => $queryParams,
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ],
                'stream' => true,  // 启用流式传输
                'decode_content' => false,  // 禁用自动解码
            ]);

            // 检查状态码
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                Log::error('插件下载失败', [
                    'plugin_id' => $pluginId,
                    'status' => $statusCode,
                ]);
                return false;
            }

            // 获取响应流
            $body = $response->getBody();

            // 打开临时文件进行写入
            $fileHandle = fopen($destination, 'wb');
            if ($fileHandle === false) {
                throw new FailedException("无法创建临时文件: {$destination}");
            }

            // 读取流并写入文件
            $downloadedSize = 0;
            while (!$body->eof()) {
                $chunk = $body->read(8192); // 每次读取 8KB
                $written = fwrite($fileHandle, $chunk);

                if ($written === false) {
                    fclose($fileHandle);
                    throw new FailedException('写入文件失败');
                }

                $downloadedSize += $written;
            }

            fclose($fileHandle);
            $body->close();

            // 验证文件是否下载完整
            if ($downloadedSize === 0) {
                throw new FailedException('下载的文件大小为 0');
            }

            return $destination;
        } catch(BadResponseException $e){
            $content = json_decode($e->getResponse()->getBody()->getContents(), true);

            throw new FailedException($content['message']);
        } catch (\Throwable $e) {
            Log::error('插件下载异常', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * 校验插件版本权限
     *
     * @param string $token
     * @param string $pluginId
     * @param $versionId
     * @return bool
     * @throws ConnectionException
     */
    public function checkPermission(string $token, string $pluginId, $version): bool
    {
        $token = base64_decode($token);

        $response = Http::withToken($token)
            ->timeout(30)
            ->withOptions($this->options)
            ->get("{$this->baseUrl}/plugins/{$pluginId}/verify/{$version}");

        if (! $response->successful()) {
            return false;
        }

        return (bool) $response->json('success');
    }
}
