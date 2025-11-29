export interface Plugin {
  id: string
  name: string
  title: string
  previews: string[]
  description: string
  keywords: string[]
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
  type: number
  created_at: string
  updated_at: string
  preview_urls: string[]
  formatted_price_yearly: string
  formatted_price_permanent: string
  star_rating: number
  is_published: boolean
  category: {
    id: number
    name: string
  }
  author: {
    id: number
    name: string
    is_pro_user: boolean
    is_founder: boolean
  }
  latest_version: Array<{
    id: number
    name: string
    plugin_id: number | string
    description: string
    depend_on: string
    type: number
    size: string
    save_path: string
    created_at: string
    updated_at: string
  }>
  is_installed?: boolean
  installed_version?: string | null
  installed_at?: string | null
  installing?: boolean
  composer_name?: string  // Composer 包名，如 catchadmin/plugin
}