<template>
    <div>
        <el-alert title="插件市场插件目前只支持本地安装，安装前，请一定做好代码和数据相关备份!!!" type="warning" center show-icon />

        <!-- 搜索和筛选工具栏 -->
        <div class="flex items-center gap-3 mt-5">
            <!-- 搜索框 -->
            <el-input
                v-model="searchKeyword"
                placeholder="搜索插件..."
                clearable
                class="!w-48"
                @input="handleSearch"
            >
                <template #prefix>
                    <Icon name="magnifying-glass" class="w-4 h-4" />
                </template>
            </el-input>

            <!-- 筛选器 -->
            <el-select v-model="selectedCategory" placeholder="分类" clearable class="!w-24" @change="handleSearch">
                <el-option label="全部" value="" />
                <el-option
                    v-for="category in categories"
                    :key="category.id"
                    :label="category.name"
                    :value="category.id"
                />
            </el-select>

            <el-select v-model="filterFree" placeholder="价格" clearable class="!w-24" @change="handleSearch">
                <el-option label="全部" value="" />
                <el-option label="免费" :value="true" />
                <el-option label="付费" :value="false" />
            </el-select>

            <el-select v-model="filterOfficial" placeholder="来源" clearable class="!w-24" @change="handleSearch">
                <el-option label="全部" value="" />
                <el-option label="官方" :value="true" />
                <el-option label="第三方" :value="false" />
            </el-select>

            <!-- 刷新按钮 -->
            <el-button :icon="Refresh" circle @click="handleRefresh" :loading="loading" />

            <!-- 分隔弹性空间 -->
            <div class="flex-1"></div>

            <!-- 账户信息 -->
            <div v-if="loggedInUser" class="flex items-center gap-3 pl-4 border-l border-gray-200 dark:border-gray-600">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center">
                        <Icon name="user" class="w-4 h-4 text-white" />
                    </div>
                    <span class="text-sm text-gray-600 dark:text-gray-300 max-w-[120px] truncate" :title="loggedInUser">
            {{ loggedInUser }}
          </span>
                </div>
                <el-button type="danger" size="small" plain @click="handleLogout">
                    退出登录
                </el-button>
            </div>
        </div>

        <!-- 插件列表 -->
        <div v-loading="loading" class="min-h-[400px] mt-5">
            <div v-if="plugins.length === 0" class="flex items-center justify-center h-64 text-gray-400">
                <div class="text-center">
                    <Icon name="shopping-bag" class="w-16 h-16 mx-auto mb-4 opacity-50" />
                    <p>暂无可用插件</p>
                </div>
            </div>
            <!-- 插件卡片网格 - 响应式6列布局 -->
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 items-stretch">
                <el-card v-for="plugin in plugins" :key="plugin.id" shadow="never" class="plugin-card overflow-hidden h-full">
                    <!-- 顶部标题栏 -->
                    <template #header>
                        <!-- 图片轮播 / 占位符 -->
                        <div class="plugin-carousel-wrapper -mx-5 -mt-5 mb-3">
                            <el-carousel
                                v-if="plugin.preview_urls?.length"
                                height="150px"
                                :autoplay="false"
                                indicator-position="none"
                                arrow="hover"
                                class="plugin-carousel"
                            >
                                <el-carousel-item v-for="(url, index) in plugin.preview_urls" :key="index">
                                    <img
                                        :src="url"
                                        :alt="`${plugin.title} 预览图 ${index + 1}`"
                                        class="w-full h-full object-cover cursor-pointer"
                                        @click="handlePreviewImage(plugin.preview_urls, index)"
                                    />
                                </el-carousel-item>
                            </el-carousel>
                            <!-- 无图片占位符 -->
                            <div v-else class="bg-gray-50 dark:bg-gray-800 flex items-center justify-center" style="height: 150px">
                                <Icon name="photo" class="w-10 h-10 text-gray-300 dark:text-gray-600" />
                            </div>
                        </div>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-semibold text-base truncate">{{ plugin.title }}</h3>
                                    <el-tag v-if="plugin.is_official" type="primary" size="small">官方</el-tag>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-gray-500">
                  <span class="flex items-center gap-1">
                    <Icon name="user" class="w-3 h-3" />
                    {{ plugin.author.name }}
                  </span>
                                    <el-tag v-if="plugin.author.is_founder" type="danger" size="small" effect="plain">创始人</el-tag>
                                    <el-tag v-if="plugin.author.is_pro_user" type="warning" size="small" effect="plain">专业版</el-tag>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <div v-if="plugin.is_free">
                                    <el-tag type="success" size="small">免费</el-tag>
                                </div>
                                <div v-else class="space-y-0.5">
                                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ plugin.formatted_price_yearly }}/年</div>
                                    <div class="text-xs font-semibold text-orange-600 dark:text-orange-400">{{ plugin.formatted_price_permanent }}/永久</div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- 描述信息 -->
                    <div class="py-2 min-h-[60px]">
                        <p class="text-xs text-gray-500 dark:text-gray-300 line-clamp-3 leading-relaxed">
                            {{ plugin.description }}
                        </p>
                    </div>

                    <!-- 关键词标签 - 固定高度保持卡片一致 -->
                    <div class="min-h-[28px] pb-3">
                        <div v-if="plugin.keywords && plugin.keywords.length > 0" class="flex flex-wrap gap-1">
                            <el-tag
                                v-for="(keyword, index) in plugin.keywords.slice(0, 3)"
                                :key="index"
                                size="small"
                                type="info"
                                effect="plain"
                            >
                                {{ keyword }}
                            </el-tag>
                            <el-tag
                                v-if="plugin.keywords.length > 3"
                                size="small"
                                type="primary"
                                effect="plain"
                            >
                                +{{ plugin.keywords.length - 3 }}
                            </el-tag>
                        </div>
                    </div>

                    <!-- 详细信息 -->
                    <div class="space-y-3 text-xs text-gray-500 dark:text-gray-400">
                        <div class="flex items-center justify-between">
              <span class="flex items-center gap-1">
                <Icon name="tag" class="w-3 h-3" />
                {{ plugin.category.name }}
              </span>
                            <span class="flex items-center gap-1">
                <Icon name="arrow-down-tray" class="w-3 h-3" />
                {{ plugin.downloads }} 次下载
              </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <Icon name="star" class="w-3 h-3 text-amber-400" />
                                <span>{{ plugin.star_rating.toFixed(1) }}</span>
                            </div>
                            <span v-if="plugin.versions && plugin.versions.length > 0" class="flex items-center gap-1">
                <Icon name="cube" class="w-3 h-3" />
                {{ plugin.versions.length }} 个版本
              </span>
                            <span v-else class="text-gray-400">暂无版本</span>
                        </div>
                    </div>

                    <!-- 底部操作栏 -->
                    <template #footer>
                        <div class="flex flex-col gap-2">
                            <!-- 已安装状态 -->
                            <div v-if="plugin.is_installed" class="flex items-center justify-between">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400 text-xs">
                  <Icon name="check-circle" class="w-3.5 h-3.5 mr-1" />
                  已安装 v{{ plugin.installed_version }}
                </span>
                                <span v-if="getLatestVersion(plugin)" class="text-xs text-gray-400">
                  最新 v{{ getLatestVersion(plugin) }}
                </span>
                            </div>

                            <!-- 操作按钮行 -->
                            <div class="flex items-center gap-2">
                                <!-- 未安装：版本选择 + 安装按钮 -->
                                <template v-if="!plugin.is_installed && plugin.versions && plugin.versions.length > 0">
                                    <el-select
                                        v-model="plugin.selected_version"
                                        size="small"
                                        class="!w-28"
                                        placeholder="版本"
                                    >
                                        <el-option
                                            v-for="ver in plugin.versions"
                                            :key="ver.id"
                                            :label="'v' + ver.name + (ver.is_free ? ' 免费' : '')"
                                            :value="ver.name"
                                        >
                                            <div class="flex items-center justify-between w-full gap-2">
                                                <span>v{{ ver.name }}</span>
                                                <span class="flex items-center gap-1">
                          <el-tag v-if="ver.is_free" type="success" size="small" effect="plain">免费</el-tag>
                          <el-tag v-else type="warning" size="small" effect="plain">付费</el-tag>
                          <span class="text-xs text-gray-400">{{ ver.size }}</span>
                        </span>
                                            </div>
                                        </el-option>
                                    </el-select>
                                    <el-button
                                        type="primary"
                                        size="small"
                                        :loading="plugin.installing"
                                        @click="handleInstall(plugin)"
                                    >
                                        <Icon name="arrow-down-tray" class="w-4 h-4 mr-1" v-if="!plugin.installing"/>
                                        安装
                                    </el-button>
                                </template>

                                <!-- 已安装：更新 + 卸载按钮 -->
                                <template v-if="plugin.is_installed">
                                    <!-- 更新按钮（有新版本时显示） -->
                                    <template v-if="hasUpdate(plugin)">
                                        <el-select
                                            v-model="plugin.selected_version"
                                            size="small"
                                            class="!w-28"
                                            placeholder="版本"
                                        >
                                            <el-option
                                                v-for="ver in getAvailableUpdates(plugin)"
                                                :key="ver.id"
                                                :label="'v' + ver.name + (ver.is_free ? ' 免费' : '')"
                                                :value="ver.name"
                                            >
                                                <div class="flex items-center justify-between w-full gap-2">
                                                    <span>v{{ ver.name }}</span>
                                                    <span class="flex items-center gap-1">
                            <el-tag v-if="ver.is_free" type="success" size="small" effect="plain">免费</el-tag>
                            <el-tag v-else type="warning" size="small" effect="plain">付费</el-tag>
                            <span class="text-xs text-gray-400">{{ ver.size }}</span>
                          </span>
                                                </div>
                                            </el-option>
                                        </el-select>
                                        <el-button
                                            type="warning"
                                            size="small"
                                            :loading="plugin.installing"
                                            @click="handleUpdate(plugin)"
                                        >
                                            <Icon name="arrow-path" class="w-4 h-4 mr-1" v-if="!plugin.installing"/>
                                            更新
                                        </el-button>
                                    </template>

                                    <!-- 卸载按钮 -->
                                    <el-button
                                        type="danger"
                                        size="small"
                                        text
                                        @click="handleUninstall(plugin)"
                                    >
                                        <Icon name="trash" class="w-3 h-3 mr-1"/>
                                        卸载
                                    </el-button>
                                </template>

                                <!-- 链接 -->
                                <el-link type="info" :href="plugin.detail_url" target="_blank" class="ml-auto">
                                    详情
                                </el-link>
                                <el-link
                                    v-if="plugin.document"
                                    type="danger"
                                    :href="plugin.document"
                                    target="_blank"
                                >
                                    文档
                                </el-link>
                            </div>
                        </div>
                    </template>
                </el-card>
            </div>
        </div>

        <!-- 分页 -->
        <div v-if="total > 0" class="flex justify-center mt-6">
            <el-pagination
                v-model:current-page="currentPage"
                :page-size="pageSize"
                :total="total"
                layout="total, prev, pager, next, jumper"
                background
                @current-change="handlePageChange"
            />
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted, h, render } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { ElImageViewer } from 'element-plus'
import http from '@/support/http'
import Message from '@/support/message'
import { PluginAuth } from './pluginAuth'
import { Plugin } from './type'

interface Emits {
    (e: 'needLogin', plugin: Plugin): void
    (e: 'install', plugin: Plugin): void
    (e: 'update', plugin: Plugin): void
    (e: 'uninstall', plugin: Plugin): void
}

const emit = defineEmits<Emits>()

const loading = ref(false)
const plugins = ref<Plugin[]>([])
const categories = ref<{ id: number; name: string }[]>([])
const searchKeyword = ref('')
const selectedCategory = ref<number | ''>('')
const filterFree = ref<boolean | ''>('')
const filterOfficial = ref<boolean | ''>('')
const loggedInUser = ref<string | null>(null)

// 分页相关
const currentPage = ref(1)
const pageSize = ref(15)
const total = ref(0)

// 获取分类列表
const fetchCategories = async () => {
    try {
        const token = PluginAuth.getToken()
        const params: any = {}
        if (token) params.token = token

        http.get('plugins/categories', { ...params }).then((response: any) => {
            if (response.data?.data) {
                categories.value = response.data.data.data
            }
        })
    } catch (error) {
        console.error('获取分类列表失败', error)
    }
}

// 获取插件列表
const fetchPlugins = async () => {
    loading.value = true
    try {
        const token = PluginAuth.getToken()

        const params: any = {
            page: currentPage.value,
            limit: pageSize.value,
            _t: Date.now()  // 防止缓存
        }
        if (token) params.token = token
        if (searchKeyword.value) params.title = searchKeyword.value
        if (selectedCategory.value) params.category_id = selectedCategory.value
        if (filterFree.value !== '') params.is_free = filterFree.value
        if (filterOfficial.value !== '') params.is_official = filterOfficial.value

        http.get('plugins', params).then((response: any) => {
            const data = response.data
            plugins.value = (data.data || []).map((plugin: any) => ({
                ...plugin,
                name: plugin.id || plugin.mark || plugin.name,
                installing: false,
                // 默认选中最新版本（第一个）
                selected_version: plugin.versions?.[0]?.name || ''
            }))
            total.value = data.total || 0
        })
    } catch (error: any) {
        if (error.response?.status === 401) {
            Message.error('登录已过期，请重新登录')
            PluginAuth.clearAuth()
            loggedInUser.value = null
        } else {
            Message.error('获取市场插件列表失败')
        }
    } finally {
        loading.value = false
    }
}

// 搜索
const handleSearch = () => {
    currentPage.value = 1
    fetchPlugins()
}

// 刷新列表
const handleRefresh = () => {
    fetchCategories()
    fetchPlugins()
}

// 分页变化
const handlePageChange = (page: number) => {
    currentPage.value = page
    fetchPlugins()
}

// 获取最新版本号
const getLatestVersion = (plugin: Plugin): string | null => {
    if (!plugin.versions || plugin.versions.length === 0) {
        return null
    }
    return plugin.versions[0].name
}

// 获取可更新的版本列表（比当前安装版本新的版本）
const getAvailableUpdates = (plugin: Plugin) => {
    if (!plugin.versions || !plugin.installed_version) {
        return []
    }
    // 返回所有比当前版本新的版本（在数组中位置靠前的）
    const installedIndex = plugin.versions.findIndex(v => v.name === plugin.installed_version)
    if (installedIndex <= 0) {
        return plugin.versions.slice(0, 1) // 只返回最新版本
    }
    return plugin.versions.slice(0, installedIndex)
}

// 检查是否有更新
const hasUpdate = (plugin: Plugin): boolean => {
    if (!plugin.is_installed || !plugin.installed_version) {
        return false
    }
    if (!plugin.versions || plugin.versions.length === 0) {
        return false
    }
    const latestVersion = plugin.versions[0]?.name
    return latestVersion !== plugin.installed_version
}

// 安装插件
const handleInstall = (plugin: Plugin) => {
    if (!PluginAuth.isLoggedIn()) {
        emit('needLogin', plugin)
        return
    }
    emit('install', plugin)
}

// 更新插件
const handleUpdate = (plugin: Plugin) => {
    if (!PluginAuth.isLoggedIn()) {
        emit('needLogin', plugin)
        return
    }
    emit('update', plugin)
}

// 卸载插件
const handleUninstall = (plugin: Plugin) => {
    Message.confirm('确定要卸载此插件吗？卸载后数据可能会丢失。', () => {
        emit('uninstall', plugin)
    }).catch(() => {})
}

// 退出登录
const handleLogout = () => {
    Message.confirm('确定要退出登录吗？', () => {
        PluginAuth.clearAuth()
        loggedInUser.value = null
        Message.success('已退出登录')
    }).catch(() => {})
}

// 图片预览
const handlePreviewImage = (urls: string[], initialIndex: number = 0) => {
    const container = document.createElement('div')
    document.body.appendChild(container)

    const vnode = h(ElImageViewer, {
        urlList: urls,
        initialIndex,
        zIndex: 3000,
        onClose: () => {
            render(null, container)
            document.body.removeChild(container)
        }
    })

    render(vnode, container)
}

// 初始化登录状态
const initAuth = () => {
    const username = PluginAuth.getUsername()
    if (username) {
        loggedInUser.value = username
    }
}

// 更新登录用户
const updateLoggedInUser = (username: string | null) => {
    loggedInUser.value = username
}

// 暴露方法给父组件
defineExpose({
    updateLoggedInUser,
    fetchPlugins
})

onMounted(() => {
    initAuth()
    fetchCategories()
    fetchPlugins()
})
</script>

<style scoped>
/* 让 el-card 高度一致 */
:deep(.el-card) {
    display: flex;
    flex-direction: column;
    height: 100%;
}

:deep(.el-card__body) {
    flex: 1;
    display: flex;
    flex-direction: column;
}

:deep(.el-card__footer) {
    margin-top: auto;
}

/* 走马灯包装器样式 */
.plugin-carousel-wrapper {
    margin-left: calc(-1 * var(--el-card-padding, 20px));
    margin-right: calc(-1 * var(--el-card-padding, 20px));
    margin-top: calc(-1 * var(--el-card-padding, 20px));
}

/* 走马灯样式 */
.plugin-carousel :deep(.el-carousel__arrow) {
    width: 24px;
    height: 24px;
    font-size: 10px;
    background-color: rgba(0, 0, 0, 0.4);
}

.plugin-carousel :deep(.el-carousel__arrow:hover) {
    background-color: rgba(0, 0, 0, 0.6);
}

</style>
