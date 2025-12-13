<template>
    <el-dialog
        v-model="visible"
        :title="dialogTitle"
        width="800px"
        :close-on-click-modal="false"
        :close-on-press-escape="false"
        :show-close="!sse.isProcessing.value"
        @close="handleClose"
    >
        <!-- 进度条 -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium">{{ currentStepText }}</span>
                <span class="text-sm text-gray-500">{{ sse.progressPercent.value }}%</span>
            </div>
            <el-progress
                :percentage="sse.progressPercent.value"
                :status="progressStatus"
                :stroke-width="15"
                :striped="sse.isProcessing.value"
                :striped-flow="sse.isProcessing.value"
                :duration="20"
            />
        </div>

        <!-- 日志输出 - 使用 Terminal 组件 -->
        <Terminal
            :logs="sse.logs.value"
            :loading="sse.isProcessing.value && !sse.error.value"
            :loading-text="waitingText"
            height="256px"
        />

        <!-- 底部按钮 -->
        <template #footer>
            <div class="flex items-center justify-between">
                <span v-if="sse.error.value" class="text-red-500 text-sm">{{ sse.error.value }}</span>
                <span v-else-if="sse.isSuccess.value" class="text-green-500 text-sm">{{ successText }}</span>
                <span v-else class="text-gray-500 text-sm">{{ sse.isProcessing.value ? processingText : '' }}</span>

                <div>
                    <el-button v-if="!sse.isProcessing.value" @click="handleClose">关闭</el-button>
                    <el-button v-if="sse.isProcessing.value" type="danger" @click="handleCancel">取消</el-button>
                </div>
            </div>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { computed, watch } from 'vue'
import { useSse } from '@/composables/useSse'
import Terminal from '@/components/admin/terminal/index.vue'
import Message from '@/support/message'
import { PluginAuth } from './pluginAuth.ts'
import type { Plugin } from './type.ts'

// Props
const props = defineProps<{
    plugin: Plugin | null
    mode?: 'install' | 'update' | 'uninstall'
}>()

// 使用 defineModel 实现 v-model
const visible = defineModel<boolean>({ default: false })

// Emits
const emit = defineEmits<{
    success: [plugin: Plugin]
    error: [message: string]
}>()

// 使用 SSE composable
const sse = useSse()

// 当前模式
const currentMode = computed(() => props.mode || 'install')
const isInstallOrUpdate = computed(() => currentMode.value === 'install' || currentMode.value === 'update')
const isUpdate = computed(() => currentMode.value === 'update')

// 动态文本
const dialogTitle = computed(() => {
    if (currentMode.value === 'install') return '插件安装'
    if (currentMode.value === 'update') return '插件更新'
    return '插件卸载'
})
const successText = computed(() => {
    if (currentMode.value === 'install') return '安装完成'
    if (currentMode.value === 'update') return '更新完成'
    return '卸载完成'
})
const processingText = computed(() => {
    if (currentMode.value === 'install') return '正在安装...'
    if (currentMode.value === 'update') return '正在更新...'
    return '正在卸载...'
})
const waitingText = computed(() => {
    if (currentMode.value === 'install') return '处理中，请稍候...'
    if (currentMode.value === 'update') return '更新中，请稍候...'
    return '卸载中，请稍候...'
})

// 步骤文本映射
const installStepTextMap: Record<string, string> = {
    download: '下载插件',
    extract: '解压插件',
    resolve: '解析插件',
    hook: '执行安装 Hook',
    composer: 'Composer 安装',
    npm: 'NPM 安装',
}

const uninstallStepTextMap: Record<string, string> = {
    hook: '执行卸载 Hook',
    composer: 'Composer 卸载',
    cleanup: '清理文件',
}

// 安装/卸载步骤顺序（根据类型动态调整）
const libraryInstallSteps = ['composer', 'npm']
const downloadInstallSteps = ['download', 'extract', 'resolve', 'hook', 'npm']
const uninstallSteps = ['hook', 'composer', 'cleanup']

// 获取插件类型字符串
const getPluginTypeString = (plugin: Plugin | null): string => {
    if (!plugin) return 'library'
    return plugin.type || 'library'
}

// 根据插件类型获取安装步骤
const getInstallSteps = (plugin: Plugin | null): string[] => {
    const typeStr = getPluginTypeString(plugin)
    return typeStr === 'library' ? libraryInstallSteps : downloadInstallSteps
}

const currentStepText = computed(() => {
    if (sse.isSuccess.value) return successText.value
    if (sse.error.value) {
        if (currentMode.value === 'install') return '安装失败'
        if (currentMode.value === 'update') return '更新失败'
        return '卸载失败'
    }
    const stepMap = isInstallOrUpdate.value ? installStepTextMap : uninstallStepTextMap
    return stepMap[sse.progress.value.step] || '准备中...'
})

const progressStatus = computed(() => {
    if (sse.isSuccess.value) return 'success'
    if (sse.error.value) return 'exception'
    return undefined
})

// 开始安装
const startInstall = () => {
    if (!props.plugin) return

    const token = PluginAuth.getToken()
    if (!token) {
        sse.error.value = '请先登录'
        return
    }

    // 获取插件类型
    const pluginType = getPluginTypeString(props.plugin)
    const steps = getInstallSteps(props.plugin)

    // 使用用户选择的版本，如果没有则使用最新版本
    const selectedVersion = props.plugin.selected_version || props.plugin.versions?.[0]?.name || ''

    const params = new URLSearchParams({
        token,
        id: props.plugin.id,
        name: props.plugin.mark || '',
        version: selectedVersion,
        type: pluginType
    })

    const url = `/api/plugins/install-stream?${params.toString()}`

    sse.connect(url, {
        steps: steps,
        handlers: {
            onComplete: () => {
                sse.addLog(successText.value + '！', 'success')
                emit('success', props.plugin!)
            }
        }
    })
}

// 开始卸载
const startUninstall = () => {
    if (!props.plugin) return

    // 卸载不需要 token，使用包名
    const name = props.plugin.mark || ''
    if (!name) {
        sse.error.value = '无法获取插件包名'
        return
    }

    const params = new URLSearchParams({ name })
    const url = `/api/plugins/uninstall-stream?${params.toString()}`

    sse.connect(url, {
        steps: uninstallSteps,
        handlers: {
            onComplete: () => {
                sse.addLog(successText.value + '！', 'success')
                emit('success', props.plugin!)
            }
        }
    })
}

// 取消操作确认
const handleCancel = () => {
    let message = '取消操作不会中断后台进程，已执行的操作将保留。确定要取消吗？'
    let cancelLog = '操作已取消'

    if (currentMode.value === 'install') {
        message = '取消安装不会中断后台进程，已执行的操作将保留。确定要取消吗？'
        cancelLog = '安装已取消'
    } else if (currentMode.value === 'update') {
        message = '取消更新不会中断后台进程，已执行的操作将保留。确定要取消吗？'
        cancelLog = '更新已取消'
    } else {
        message = '取消卸载不会中断后台进程，已执行的操作将保留。确定要取消吗？'
        cancelLog = '卸载已取消'
    }

    Message.confirm(message, () => {
        // 用户确认取消
        sse.disconnect()
        sse.error.value = '已取消'
        sse.addLog(cancelLog, 'error')
        // 关闭弹窗
        visible.value = false
    }).catch(() => {
        // 用户选择继续等待，不做任何操作
    })
}

// 关闭弹窗
const handleClose = () => {
    if (sse.isProcessing.value) return
    sse.reset()
    visible.value = false
}

// 开始执行
const start = () => {
    sse.reset()
    if (isInstallOrUpdate.value) {
        startInstall()
    } else {
        startUninstall()
    }
}

// 监听显示状态
watch(visible, (val: boolean) => {
    if (val && props.plugin) {
        start()
    }
})

// 暴露方法
defineExpose({ start })
</script>
