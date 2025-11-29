/**
 * 插件系统路由配置
 * 集中管理插件相关的所有路由
 */
import type { RouteRecordRaw } from 'vue-router'

export const pluginRoutes: RouteRecordRaw[] = [
  {
    path: '/plugins',
    name: 'plugin',
    component: () => import('@/layout/index.vue'),
    meta: { title: '插件管理', icon: 'wrench-screwdriver' },
    children: [
      {
        path: '',
        name: 'plugins',
        meta: { title: '插件管理', keepalive: false },
        component: () => import('./index.vue')
      }
    ]
  }
]
