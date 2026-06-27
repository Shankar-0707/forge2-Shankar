import axios from 'axios'

/**
 * Configuration
 * In development, Vite proxies /api and /sanctum to the Laravel backend.
 * In production, set VITE_BACKEND_URL to the Laravel origin.
 */
const API_URL = import.meta.env.VITE_API_URL || '/api'
const BACKEND_URL = import.meta.env.VITE_BACKEND_URL || ''

/**
 * Axios instance — Sanctum SPA + Bearer token hybrid.
 *
 * CSRF: withCredentials + withXSRFToken ensure the XSRF-TOKEN cookie
 * is read and sent as the X-XSRF-TOKEN header automatically.
 */
const client = axios.create({
  baseURL: API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

/**
 * Request interceptor — attach Bearer token + organization header.
 *
 * SECURITY: organization_id is NEVER taken from user input.
 * It is stored only from the authenticated login response.
 */
client.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    const orgId = localStorage.getItem('organization_id')
    if (orgId) {
      config.headers['X-Organization-Id'] = orgId
    }

    return config
  },
  (error) => Promise.reject(error),
)

/**
 * Response interceptor — global 401 handling.
 */
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearAuth()

      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
    }

    return Promise.reject(error)
  },
)

// ---------------------------------------------------------------------------
// Auth helpers
// ---------------------------------------------------------------------------

/**
 * Fetch the Sanctum CSRF cookie. Must be called before login/register.
 */
export async function getCSRFCookie() {
  await axios.get(`${BACKEND_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  })
}

/**
 * Persist auth token and organization context after a successful login.
 * The org ID comes from the server's authenticated user — never from input.
 */
export function setAuth(token, organizationId) {
  localStorage.setItem('auth_token', token)

  if (organizationId) {
    localStorage.setItem('organization_id', String(organizationId))
  }
}

/**
 * Clear all auth state.
 */
export function clearAuth() {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('organization_id')
}

/**
 * Check if a token exists in storage.
 */
export function isAuthenticated() {
  return !!localStorage.getItem('auth_token')
}

export default client
