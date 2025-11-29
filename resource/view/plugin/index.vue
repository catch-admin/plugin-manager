<template>
  <div class="p-4 bg-white dark:bg-regal-dark">
    <el-tabs v-model="activeTab" class="bg-white dark:bg-transparent p-4 rounded-lg">
      <!-- 本地插件
      <el-tab-pane label="本地插件" name="local">
        <LocalPlugins />
      </el-tab-pane>
    -->
      <!-- 插件市场 -->
      <el-tab-pane label="插件市场" name="market">
        <MarketPlugins 
          ref="marketPluginsRef" 
          @needLogin="handleNeedLogin"
          @uninstall="handleUninstall" 
        />
      </el-tab-pane>
    </el-tabs>

    <!-- 登录弹窗 -->
    <Login
      v-model="loginDialogVisible"
      :loading="loginLoading"
      @submit="handleMarketLogin"
    />

    <!-- 操作进度弹窗（安装/卸载）-->
    <PluginProgress
      v-model="progressDialogVisible"
      :plugin="pendingPlugin"
      :mode="progressMode"
      @success="handleProgressSuccess"
      @error="handleProgressError"
    />
  </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import http from '@/support/http'
import Message from '@/support/message'
import { PluginAuth } from './pluginAuth'
import Login from './Login.vue'
import LocalPlugins from './LocalPlugins.vue'
import MarketPlugins from './MarketPlugins.vue'
import PluginProgress from './PluginProgress.vue'
import type { Plugin } from './type'

const activeTab = ref('market')
const loginDialogVisible = ref(false)
const loginLoading = ref(false)
const progressDialogVisible = ref(false)
const progressMode = ref<'install' | 'uninstall'>('install')
const marketPluginsRef = ref<InstanceType<typeof MarketPlugins>>()
const pendingPlugin = ref<Plugin | null>(null)

// 处理需要登录的情况（安装）
const handleNeedLogin = (plugin: Plugin) => {
  pendingPlugin.value = plugin
  if (!PluginAuth.isLoggedIn()) {
    loginDialogVisible.value = true
  } else {
    doInstallPlugin(plugin)
  }
}

// 处理卸载
const handleUninstall = (plugin: Plugin) => {
  pendingPlugin.value = plugin
  progressMode.value = 'uninstall'
  progressDialogVisible.value = true
}

// 安装插件（使用 SSE 流式安装）
const doInstallPlugin = (plugin: Plugin) => {
  pendingPlugin.value = plugin
  progressMode.value = 'install'
  progressDialogVisible.value = true
}

// 操作成功回调
const handleProgressSuccess = (plugin: Plugin) => {
  const msg = progressMode.value === 'install' ? '插件安装成功' : '插件卸载成功'
  Message.success(msg)
  marketPluginsRef.value?.fetchPlugins()
}

// 操作失败回调
const handleProgressError = (message: string) => {
  const defaultMsg = progressMode.value === 'install' ? '安装失败' : '卸载失败'
  Message.error(message || defaultMsg)
}

// 处理市场登录
const handleMarketLogin = async (form: { email: string; password: string }) => {
  loginLoading.value = true
  try {
    http.post('plugins/auth/login', {
      email: form.email,
      password: form.password
    }).then((response: any) => {
        const { token } = response.data.data
        // 保存认证信息，使用 email 作为 username
        PluginAuth.setAuth(token, form.email, 86400 * 7)

        marketPluginsRef.value?.updateLoggedInUser(form.email)

        Message.success('登录成功')
        loginDialogVisible.value = false

        // 登录成功后，如果有待安装的插件，继续安装
        if (pendingPlugin.value) {
          doInstallPlugin(pendingPlugin.value)
        }
    })
  } catch (error: any) {
    Message.error(error.response?.data?.message || '登录失败')
  } finally {
    loginLoading.value = false
  }
}
</script>
