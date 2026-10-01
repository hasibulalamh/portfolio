'use client'

import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import { AlertCircle, Check, X } from 'lucide-react'

const ToastContext = createContext(null)

let toastSeq = 0

// Local toast state. Used directly by standalone screens (e.g. login) and
// internally by ToastProvider.
const useToastState = () => {
  const [toasts, setToasts] = useState([])
  const timers = useRef(new Map())

  const removeToast = useCallback((id) => {
    const timer = timers.current.get(id)
    if (timer) {
      clearTimeout(timer)
      timers.current.delete(id)
    }
    setToasts((prev) => prev.filter((toast) => toast.id !== id))
  }, [])

  const showToast = useCallback(
    (message, type = 'info', duration = 3000) => {
      // A counter, not Date.now(): two toasts fired in the same millisecond
      // would otherwise share an id and React key.
      toastSeq += 1
      const id = toastSeq
      setToasts((prev) => [...prev, { id, message, type }])

      if (duration > 0) {
        timers.current.set(
          id,
          setTimeout(() => removeToast(id), duration)
        )
      }

      return id
    },
    [removeToast]
  )

  useEffect(() => {
    const pending = timers.current
    return () => {
      pending.forEach((timer) => clearTimeout(timer))
      pending.clear()
    }
  }, [])

  return { toasts, showToast, removeToast }
}

export const ToastProvider = ({ children }) => {
  const value = useToastState()

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastContainer>
        {value.toasts.map((toast) => (
          <Toast
            key={toast.id}
            message={toast.message}
            type={toast.type}
            onClose={() => value.removeToast(toast.id)}
          />
        ))}
      </ToastContainer>
    </ToastContext.Provider>
  )
}

// Returns the shared toast queue when rendered under a ToastProvider, and a
// component-local queue otherwise. Both hooks run unconditionally to keep hook
// order stable.
export const useToast = () => {
  const shared = useContext(ToastContext)
  const local = useToastState()
  return shared || local
}

export const Toast = ({ message, type = 'info', onClose }) => {
  const bgColor = {
    success: 'bg-green-50 border-green-200 dark:bg-green-950/80 dark:border-green-800',
    error: 'bg-red-50 border-red-200 dark:bg-red-950/80 dark:border-red-800',
    info: 'bg-blue-50 border-blue-200 dark:bg-blue-950/80 dark:border-blue-800',
  }[type]

  const textColor = {
    success: 'text-green-900 dark:text-green-100',
    error: 'text-red-900 dark:text-red-100',
    info: 'text-blue-900 dark:text-blue-100',
  }[type]

  const icon = {
    success: <Check className="w-5 h-5 text-green-600 dark:text-green-400" />,
    error: <AlertCircle className="w-5 h-5 text-red-600 dark:text-red-400" />,
    info: <AlertCircle className="w-5 h-5 text-blue-600 dark:text-blue-400" />,
  }[type]

  return (
    <div
      role={type === 'error' ? 'alert' : 'status'}
      aria-live={type === 'error' ? 'assertive' : 'polite'}
      className={`${bgColor} ${textColor} border rounded-md p-4 mb-2 flex items-center gap-3 animate-in fade-in slide-in-from-top backdrop-blur-sm`}
    >
      {icon}
      <span className="flex-1">{message}</span>
      <button
        type="button"
        onClick={onClose}
        aria-label="Dismiss notification"
        className="opacity-70 hover:opacity-100 transition-opacity"
      >
        <X className="w-4 h-4" />
      </button>
    </div>
  )
}

export const ToastContainer = ({ children }) => {
  return (
    // z-[60]: fixed with no backdrop-filter ancestor, so this is a genuine
    // page-level context — the top layer of the admin z-scale (toasts 60 >
    // dialogs/scrim 40–50 > header 30 > positioned content, see Header.jsx
    // and report.md for the backdrop-filter stacking-context mechanism).
    <div className="fixed top-4 right-4 z-[60] max-w-md w-full pointer-events-none [&>*]:pointer-events-auto">
      {children}
    </div>
  )
}
