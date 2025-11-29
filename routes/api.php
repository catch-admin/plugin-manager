<?php

use Illuminate\Support\Facades\Route;
use Catch\Plugin\Http\Controllers\PluginController;
use Catch\Middleware\AuthMiddleware;

// 插件 API 路由
Route::prefix('api/plugins')->middleware(AuthMiddleware::class)->group(function () {
    // 无需认证的路由（使用插件市场自己的 token）
    Route::withoutMiddleware(AuthMiddleware::class)->group(function () {
        // 登录接口
        Route::post('auth/login', [PluginController::class, 'login']);

        // 分类和插件列表（可选认证）
        Route::get('categories', [PluginController::class, 'categories']);
        Route::get('/', [PluginController::class, 'index']);

        // 安装/卸载（流式 SSE）
        Route::get('install-stream', [PluginController::class, 'installStream']);
        Route::get('uninstall-stream', [PluginController::class, 'uninstallStream']);
        
        // 已安装插件相关
        Route::get('installed', [PluginController::class, 'installed']);
        Route::get('check-installed', [PluginController::class, 'checkInstalled']);
    });

    // 其他认证路由（需要后台认证）
    Route::prefix('auth')->group(function () {
        Route::post('logout', [PluginController::class, 'logout']);
        Route::get('user', [PluginController::class, 'user']);
    });
});
