// 插件版本信息
export interface PluginVersion {
  id: number
  name: string              // 版本号，如 "1.3.1"
  plugin_id: string         // 插件 ID
  is_free: boolean          // 该版本是否免费
  size: string              // 版本大小，如 "7 KB"
}

// 插件分类
export interface PluginCategory {
  id: number
  name: string
}

// 插件作者
export interface PluginAuthor {
  id: number
  name: string
  is_pro_user: boolean
  is_founder: boolean
}

// 插件信息
export interface Plugin {
  id: string
  name?: string
  title: string
  previews: string[]
  description: string
  keywords: string[]
  content: string
  is_free: boolean
  mark: string
  category_id: number
  downloads: number
  score: number
  user_id: number
  document: string | null
  published_at: number
  price_yearly: number
  price_permanent: number
  is_official: boolean
  type: string              // library | plugin | module | project
  created_at: string
  updated_at: string
  preview_urls: string[]
  formatted_price_yearly: string
  formatted_price_permanent: string
  star_rating: number
  is_published: boolean
  detail_url: string
  category: PluginCategory
  author: PluginAuthor
  versions: PluginVersion[] // 版本列表（已按倒序排列，第一个是最新版本）

  // 本地状态
  is_installed?: boolean
  installed_version?: string | null
  installed_at?: string | null
  installing?: boolean
  composer_name?: string
  selected_version?: string  // 用户选择的安装版本
}