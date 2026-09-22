import axios from 'axios'

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'

const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
})

// Add token to requests if available
api.interceptors.request.use((config) => {
  const token = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle 401 errors by redirecting to login.
//
// The login request is exempt. A 401 from /login means "those credentials are
// wrong", not "your session expired" — redirecting there hard-reloaded the page
// via window.location and destroyed the error toast before React could paint
// it, so a failed login looked like nothing had happened at all.
const isAuthEndpoint = (url = '') => /\/login\/?$/.test(url.split('?')[0])

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !isAuthEndpoint(error.config?.url)) {
      if (typeof window !== 'undefined') {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('admin_user')
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export const apiCall = async (method, endpoint, data = null) => {
  try {
    const config = { method, url: endpoint }
    // DELETE is included so a body can accompany a delete request (the
    // storage orphans endpoint takes its path list that way).
    if (data && ['post', 'put', 'delete'].includes(method.toLowerCase())) {
      config.data = data
    }
    const response = await api(config)
    return {
      success: true,
      data: response.data.data,
      message: response.data.message,
      errorType: null,
    }
  } catch (error) {
    let errorType = 'UNKNOWN'
    
    // Network error: fetch threw or no response received
    if (!error.response) {
      errorType = 'NETWORK'
    }
    // Server error: 5xx status
    else if (error.response.status >= 500) {
      errorType = 'SERVER'
    }
    // Validation error: 4xx with validation errors
    else if (error.response.status === 422 || error.response?.data?.errors) {
      errorType = 'VALIDATION'
    }
    // Other 4xx errors
    else if (error.response.status >= 400) {
      errorType = 'VALIDATION'
    }
    
    return {
      success: false,
      // Preserved rather than nulled: an endpoint that committed its write and
      // then failed on a side effect answers non-2xx but still returns the saved
      // record, which is how a caller tells "nothing happened" from "the write
      // landed, the delivery did not". Null for ordinary errors.
      data: error.response?.data?.data ?? null,
      message: error.response?.data?.message || error.message,
      errors: error.response?.data?.errors || {},
      errorType,
    }
  }
}

export default api
