import axios, { type AxiosInstance } from 'axios'

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000'

export const apiClient: AxiosInstance = axios.create({
  baseURL: `${BASE_URL}/api`,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// ── Request interceptor: attach token + X-Farm-Id ────────────────────────────
apiClient.interceptors.request.use((config) => {
  // Token from localStorage (client-side only)
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    const farmId = localStorage.getItem('current_farm_id')
    if (farmId) {
      config.headers['X-Farm-Id'] = farmId
    }
  }
  return config
})

// ── Response interceptor: global 401 handling ────────────────────────────────
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && typeof window !== 'undefined') {
      // Clear stored credentials and redirect to login
      localStorage.removeItem('auth_token')
      localStorage.removeItem('current_farm_id')
      document.cookie = 'auth_session=; path=/; max-age=0'
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)
