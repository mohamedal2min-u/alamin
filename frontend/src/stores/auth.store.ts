import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import type { User } from '@/types/auth'
import { apiClient } from '@/lib/api/client'

interface AuthState {
  token: string | null
  user: User | null
  isAuthenticated: boolean

  setAuth: (token: string, user: User) => void
  setUser: (user: User) => void
  logout: () => void
  login: (login: string, password: string) => Promise<void>
}

// ── Cookie helper (SSR-safe) ──────────────────────────────────────────────────
function setAuthCookie(value: string) {
  if (typeof document === 'undefined') return
  // Max-age 7 days — middleware reads this for routing
  document.cookie = `auth_session=${value}; path=/; max-age=${7 * 24 * 3600}; SameSite=Lax`
}

function clearAuthCookie() {
  if (typeof document === 'undefined') return
  document.cookie = 'auth_session=; path=/; max-age=0'
}

// ── Store ─────────────────────────────────────────────────────────────────────
export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      isAuthenticated: false,

      setAuth: (token, user) => {
        localStorage.setItem('auth_token', token)
        setAuthCookie('1')  // Cookie value is just a presence flag
        set({ token, user, isAuthenticated: true })
      },

      setUser: (user) => set({ user }),

      logout: () => {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('current_farm_id')
        clearAuthCookie()
        set({ token: null, user: null, isAuthenticated: false })
      },

      login: async (login, password) => {
        const response = await apiClient.post<{ token: string; user: User }>('/auth/login', {
          login,
          password,
        })
        const { token, user } = response.data
        get().setAuth(token, user)
      },
    }),
    {
      name: 'alamin-auth',
      storage: createJSONStorage(() =>
        typeof window !== 'undefined' ? localStorage : ({} as Storage)
      ),
      partialize: (state) => ({
        token: state.token,
        user: state.user,
        isAuthenticated: state.isAuthenticated,
      }),
    }
  )
)
