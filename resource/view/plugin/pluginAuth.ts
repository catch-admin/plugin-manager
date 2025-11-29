/**
 * 插件市场认证管理
 * 独立管理插件市场的登录状态和 token
 */

const STORAGE_KEY = 'plugin_market_auth'

interface MarketAuthData {
  token: string
  username: string
  expiresAt: number // 时间戳
}

export class PluginAuth {
  /**
   * 保存认证信息
   */
  static setAuth(token: string, username: string, expiresIn: number = 86400): void {
    const expiresAt = Date.now() + expiresIn * 1000 // 默认24小时
    const authData: MarketAuthData = {
      token,
      username,
      expiresAt
    }
    localStorage.setItem(STORAGE_KEY, JSON.stringify(authData))
  }

  /**
   * 获取认证信息
   */
  static getAuth(): MarketAuthData | null {
    try {
      const data = localStorage.getItem(STORAGE_KEY)
      if (!data) return null

      const authData: MarketAuthData = JSON.parse(data)
      
      // 检查是否过期
      if (authData.expiresAt && Date.now() > authData.expiresAt) {
        this.clearAuth()
        return null
      }

      return authData
    } catch (error) {
      console.error('Failed to parse auth data:', error)
      this.clearAuth()
      return null
    }
  }

  /**
   * 获取 token
   */
  static getToken(): string | null {
    const auth = this.getAuth()
    return auth ? auth.token : null
  }

  /**
   * 检查是否已登录
   */
  static isLoggedIn(): boolean {
    return this.getAuth() !== null
  }

  /**
   * 获取用户名
   */
  static getUsername(): string | null {
    const auth = this.getAuth()
    return auth ? auth.username : null
  }

  /**
   * 清除认证信息
   */
  static clearAuth(): void {
    localStorage.removeItem(STORAGE_KEY)
  }

  /**
   * 获取认证请求头
   */
  static getAuthHeaders(): Record<string, string> {
    const token = this.getToken()
    return token ? { 'X-Plugin-Market-Token': token } : {}
  }
}
