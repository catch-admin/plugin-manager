<template>
  <div v-loading="loading" class="min-h-[400px]">
    <div v-if="plugins.length === 0" class="flex items-center justify-center h-64 text-gray-400">
      <div class="text-center">
        <Icon name="cube" class="w-16 h-16 mx-auto mb-4 opacity-50" />
        <p>暂无已安装插件</p>
      </div>
    </div>
    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      <el-card v-for="plugin in plugins" :key="plugin.name" shadow="never" class="transition-transform duration-200 flex flex-col hover:-translate-y-1">
        <template #header>
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
              <div v-if="plugin.icon" class="w-8 h-8 flex items-center justify-center bg-blue-100 rounded">
                <Icon :name="plugin.icon" class="w-5 h-5 text-blue-600" />
              </div>
              <div v-else class="w-8 h-8 flex items-center justify-center bg-gray-100 rounded">
                <Icon name="cube" class="w-5 h-5 text-gray-600" />
              </div>
              <span class="font-semibold">{{ plugin.title }}</span>
            </div>
            <el-tag :type="plugin.enabled ? 'success' : 'info'" size="small">
              {{ plugin.enabled ? '已启用' : '已禁用' }}
            </el-tag>
          </div>
        </template>
        <div class="h-[100px] py-2 flex flex-col">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-2 line-clamp-2">{{ plugin.description }}</p>
          <div class="text-xs text-gray-400 dark:text-gray-500 space-y-1">
            <div>版本: {{ plugin.version }}</div>
            <div>作者: {{ plugin.author }}</div>
          </div>
        </div>
        <template #footer>
          <div class="flex items-center justify-between h-10">
            <el-switch
              v-model="plugin.enabled"
              @change="handleToggle(plugin)"
              :loading="plugin.switching"
              active-text="启用"
              inactive-text="禁用"
            />
            <div class="space-x-2">
              <el-button
                v-if="plugin.hasConfig"
                type="primary"
                size="small"
                link
                @click="handleConfig(plugin)"
              >
                <Icon name="cog-6-tooth" class="w-4 h-4" />
              </el-button>
              <el-popconfirm
                title="确定要卸载此插件吗？"
                confirm-button-text="确定"
                cancel-button-text="取消"
                @confirm="handleUninstall(plugin)"
              >
                <template #reference>
                  <el-button type="danger" size="small" link :loading="plugin.uninstalling">
                    <Icon name="trash" class="w-4 h-4" />
                  </el-button>
                </template>
              </el-popconfirm>
            </div>
          </div>
        </template>
      </el-card>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import http from '@/support/http'
import Message from '@/support/message'
import Icon from '@/components/icon/index.vue'

interface Plugin {
  name: string
  title: string
  version: string
  author: string
  description: string
  icon?: string
  enabled?: boolean
  hasConfig?: boolean
  switching?: boolean
  uninstalling?: boolean
}

// Mock 数据开关
const USE_MOCK = true

// Mock 数据
const mockPlugins: Plugin[] = [
  {
    name: 'payment-alipay',
    title: '支付宝支付',
    version: '1.2.0',
    author: 'CatchAdmin',
    description: '支持支付宝扫码支付、APP支付、H5支付等多种支付方式，快速接入支付宝支付能力。',
    icon: 'credit-card',
    enabled: true,
    hasConfig: true
  },
  {
    name: 'sms-aliyun',
    title: '阿里云短信',
    version: '1.0.5',
    author: 'CatchAdmin',
    description: '集成阿里云短信服务，支持验证码、通知类短信发送，高并发稳定可靠。',
    icon: 'chat-bubble-left-right',
    enabled: true,
    hasConfig: true
  },
  {
    name: 'storage-oss',
    title: '阿里云OSS',
    version: '2.1.0',
    author: 'CatchAdmin Team',
    description: '对接阿里云对象存储OSS，支持图片、视频、文件上传，提供CDN加速能力。',
    icon: 'cloud-arrow-up',
    enabled: false,
    hasConfig: true
  },
  {
    name: 'wechat-official',
    title: '微信公众号',
    version: '1.5.2',
    author: 'JaguarJack',
    description: '微信公众号管理插件，支持自定义菜单、消息推送、用户管理等核心功能。',
    icon: 'chat-bubble-bottom-center',
    enabled: true,
    hasConfig: true
  }
]

const loading = ref(false)
const plugins = ref<Plugin[]>([])

// 获取插件列表
const fetchPlugins = async () => {
  loading.value = true
  try {
    if (USE_MOCK) {
      await new Promise(resolve => setTimeout(resolve, 500))
      plugins.value = mockPlugins.map(plugin => ({
        ...plugin,
        switching: false,
        uninstalling: false
      }))
    } else {
      const response = await http.get('api/system/plugin/local')
      if (response.data.code === 10000) {
        plugins.value = response.data.data.map((plugin: Plugin) => ({
          ...plugin,
          switching: false,
          uninstalling: false
        }))
      }
    }
  } catch (error) {
    Message.error('获取本地插件列表失败')
  } finally {
    loading.value = false
  }
}

// 切换插件状态
const handleToggle = async (plugin: Plugin) => {
  plugin.switching = true
  try {
    if (USE_MOCK) {
      await new Promise(resolve => setTimeout(resolve, 500))
      Message.success(plugin.enabled ? '插件已启用' : '插件已禁用')
    } else {
      const response = await http.put(`api/system/plugin/${plugin.name}/toggle`)
      if (response.data.code === 10000) {
        Message.success(plugin.enabled ? '插件已启用' : '插件已禁用')
      } else {
        plugin.enabled = !plugin.enabled
        Message.error(response.data.message || '操作失败')
      }
    }
  } catch (error) {
    plugin.enabled = !plugin.enabled
    Message.error('操作失败')
  } finally {
    plugin.switching = false
  }
}

// 卸载插件
const handleUninstall = async (plugin: Plugin) => {
  plugin.uninstalling = true
  try {
    if (USE_MOCK) {
      await new Promise(resolve => setTimeout(resolve, 800))
      Message.success('插件卸载成功')
      plugins.value = plugins.value.filter(p => p.name !== plugin.name)
    } else {
      const response = await http.delete(`api/system/plugin/${plugin.name}`)
      if (response.data.code === 10000) {
        Message.success('插件卸载成功')
        await fetchPlugins()
      } else {
        Message.error(response.data.message || '卸载失败')
      }
    }
  } catch (error) {
    Message.error('卸载失败')
  } finally {
    plugin.uninstalling = false
  }
}

// 打开插件配置
const handleConfig = (plugin: Plugin) => {
  Message.info('插件配置功能开发中...')
}

onMounted(() => {
  fetchPlugins()
})
</script>
