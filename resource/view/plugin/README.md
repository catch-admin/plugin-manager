# 插件系统前端实现文档

## 概述

插件管理系统的前端实现，支持本地插件管理和插件市场浏览、安装功能。

## 文件结构

```
web/src/views/plugins/
├── index.vue          # 插件管理主页面
├── pluginAuth.ts      # 插件市场认证管理（独立模块）
└── README.md          # 本文档
```

## 核心功能

### 1. 本地插件管理
- 插件列表展示（卡片式）
- 启用/禁用插件
- 卸载插件
- 插件配置（预留接口）

### 2. 插件市场
- 远端插件浏览
- 搜索和分类筛选
- 插件安装
- 市场账号登录认证

### 3. 认证管理
- localStorage 存储 token
- 自动过期检查（默认24小时）
- 退出登录

## 认证管理类 (pluginAuth.ts)

### 使用方法

```typescript
import { PluginAuth } from './pluginAuth'

// 保存认证信息
PluginAuth.setAuth(token, username, expiresIn)

// 获取 token
const token = PluginAuth.getToken()

// 检查是否登录
const isLoggedIn = PluginAuth.isLoggedIn()

// 获取用户名
const username = PluginAuth.getUsername()

// 清除认证
PluginAuth.clearAuth()

// 获取请求头
const headers = PluginAuth.getAuthHeaders()
```

### 数据存储

认证数据存储在 `localStorage`，key 为 `plugin_market_auth`：

```json
{
  "token": "market_token_xxx",
  "username": "user@example.com",
  "expiresAt": 1700000000000
}
```

## Mock 数据模式

### 启用/禁用 Mock

```typescript
// 在 index.vue 顶部修改
const USE_MOCK = true  // 使用 Mock 数据
const USE_MOCK = false // 使用真实 API
```

### Mock 数据说明

**本地插件示例**（4个）:
- 支付宝支付
- 阿里云短信
- 阿里云OSS
- 微信公众号

**市场插件示例**（8个）:
- 微信支付（免费）
- 优惠券营销（¥299）
- 数据分析面板（¥499）
- Excel导出增强（免费）
- 消息推送中心（¥199）
- 会员等级系统（¥399）
- 直播带货（¥999）
- AI智能客服（¥799）

## 后端 API 接口规范

### 1. 获取本地插件列表

```http
GET /api/system/plugin/local
```

**响应示例**:
```json
{
  "code": 10000,
  "data": [
    {
      "name": "payment-alipay",
      "title": "支付宝支付",
      "version": "1.2.0",
      "author": "CatchAdmin",
      "description": "支付宝支付插件",
      "icon": "credit-card",
      "enabled": true,
      "hasConfig": true
    }
  ]
}
```

### 2. 获取市场插件列表

```http
GET /api/system/plugin/market?keyword=支付&category=payment
```

**请求参数**:
- `keyword` (可选): 搜索关键词
- `category` (可选): 分类筛选

**请求头**:
- `X-Plugin-Market-Token`: 市场认证 token（如已登录）

**响应示例**:
```json
{
  "code": 10000,
  "data": [
    {
      "name": "payment-wechat",
      "title": "微信支付",
      "version": "2.0.0",
      "author": "CatchAdmin",
      "description": "微信支付插件",
      "icon": "currency-dollar",
      "price": 0,
      "category": "payment",
      "installed": false,
      "download_url": "https://plugins.catchadmin.com/payment-wechat-v2.0.0.zip"
    }
  ]
}
```

### 3. 检查市场登录状态

```http
GET /api/system/plugin/market-auth
```

**请求头**:
- `X-Plugin-Market-Token`: 市场认证 token

**响应示例**:
```json
{
  "code": 10000,
  "data": {
    "logged_in": true,
    "username": "user@example.com"
  }
}
```

### 4. 市场登录

```http
POST /api/system/plugin/market-login
Content-Type: application/json

{
  "username": "user@example.com",
  "password": "password123"
}
```

**响应示例**:
```json
{
  "code": 10000,
  "message": "登录成功",
  "data": {
    "token": "market_token_xxx",
    "username": "user@example.com",
    "expires_in": 86400
  }
}
```

### 5. 安装插件

```http
POST /api/system/plugin/install
Content-Type: application/json

{
  "name": "payment-wechat",
  "download_url": "https://plugins.catchadmin.com/payment-wechat-v2.0.0.zip"
}
```

**请求头**:
- `X-Plugin-Market-Token`: 市场认证 token（如需要）

**响应示例**:
```json
{
  "code": 10000,
  "message": "插件安装成功"
}
```

### 6. 切换插件状态

```http
PUT /api/system/plugin/{name}/toggle
```

**响应示例**:
```json
{
  "code": 10000,
  "message": "操作成功"
}
```

### 7. 卸载插件

```http
DELETE /api/system/plugin/{name}
```

**响应示例**:
```json
{
  "code": 10000,
  "message": "卸载成功"
}
```

## 数据类型定义

### Plugin 接口

```typescript
interface Plugin {
  name: string          // 插件唯一标识
  title: string         // 插件名称
  version: string       // 版本号
  author: string        // 作者
  description: string   // 描述
  icon?: string         // 图标名称（Heroicons）
  enabled?: boolean     // 是否启用（本地插件）
  installed?: boolean   // 是否已安装（市场插件）
  price?: number        // 价格（0为免费）
  category?: string     // 分类（tool/payment/marketing/other）
  hasConfig?: boolean   // 是否有配置页面
  download_url?: string // 下载地址（市场插件）
  
  // 内部状态（不由后端返回）
  switching?: boolean   // 切换中状态
  installing?: boolean  // 安装中状态
  uninstalling?: boolean // 卸载中状态
}
```

## 访问路由

```
http://127.0.0.1:8000/#/plugins
```

## 技术栈

- Vue 3 (Composition API)
- TypeScript
- Element Plus
- TailwindCSS
- Heroicons

## 注意事项

1. **认证隔离**: 插件市场认证数据独立存储在 `localStorage`，与系统主认证（JWT）分离
2. **Token 过期**: 自动检查 token 是否过期，过期自动清除
3. **Mock 模式**: 方便前端独立开发和调试
4. **无依赖设计**: `pluginAuth.ts` 不依赖其他模块，可独立使用

## 开发建议

### 后端开发者
1. 参考本文档的 API 接口规范实现后端接口
2. 确保响应格式与文档一致（`code: 10000` 表示成功）
3. 支持 `X-Plugin-Market-Token` 请求头认证

### 前端对接
1. 将 `USE_MOCK` 设置为 `false`
2. 确保后端 API 路径正确
3. 测试所有功能流程

## 后续扩展

- [ ] 插件详情页
- [ ] 插件更新检查
- [ ] 插件评论和评分
- [ ] 插件依赖管理
- [ ] 批量操作（批量启用/禁用）
