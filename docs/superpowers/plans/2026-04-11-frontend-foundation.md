# Frontend Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Next.js 15 frontend foundation covering project bootstrap, RTL/Arabic layout, design system, API client, auth/farm Zustand stores, Next.js middleware, login page, farm layout, flocks list, create flock, and flock details shell.

**Architecture:** Client-side SPA inside Next.js App Router. Token stored in localStorage (for API calls) and mirrored to a lightweight `auth_session` cookie (for server-side middleware routing). Zustand stores handle auth + farm state. All farm-scoped API calls send `X-Farm-Id` header. Skeleton pages stand in for backend endpoints not yet implemented.

**Tech Stack:** Next.js 15 App Router · React 19 · TypeScript · Tailwind CSS v3 · Zustand v5 · Axios · React Hook Form + Zod · @tanstack/react-query v5 · Lucide React · Cairo (Arabic Google Font)

---

## File Structure

```
frontend/
├── src/
│   ├── app/
│   │   ├── layout.tsx                    # Root layout — RTL, Arabic, Cairo font
│   │   ├── page.tsx                      # Redirect → /login or /flocks
│   │   ├── globals.css                   # Tailwind directives + CSS vars
│   │   ├── (auth)/
│   │   │   └── login/
│   │   │       └── page.tsx              # Login form
│   │   └── (farm)/
│   │       ├── layout.tsx                # Farm shell: sidebar + header + FarmSelector
│   │       ├── flocks/
│   │       │   ├── page.tsx              # Flocks list
│   │       │   ├── new/
│   │       │   │   └── page.tsx          # Create flock form
│   │       │   └── [id]/
│   │       │       └── page.tsx          # Flock details shell (tabs)
│   │       └── dashboard/
│   │           └── page.tsx              # Dashboard placeholder skeleton
│   ├── components/
│   │   ├── ui/
│   │   │   ├── Button.tsx
│   │   │   ├── Input.tsx
│   │   │   ├── Card.tsx
│   │   │   ├── Badge.tsx
│   │   │   ├── Skeleton.tsx
│   │   │   └── Spinner.tsx
│   │   ├── layout/
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Header.tsx
│   │   │   └── FarmSelector.tsx
│   │   └── flocks/
│   │       ├── FlockCard.tsx
│   │       └── FlockStatusBadge.tsx
│   ├── lib/
│   │   ├── api/
│   │   │   ├── client.ts                 # Axios instance + interceptors
│   │   │   └── flocks.ts                 # Flock API functions
│   │   └── utils.ts                      # cn(), formatDate(), etc.
│   ├── stores/
│   │   ├── auth.store.ts                 # Zustand auth store + localStorage persist
│   │   └── farm.store.ts                 # Zustand farm store + currentFarm
│   ├── middleware.ts                     # Next.js middleware — auth routing
│   └── types/
│       ├── auth.ts
│       ├── farm.ts
│       └── flock.ts
├── .env.local
├── next.config.ts
├── tailwind.config.ts
└── tsconfig.json
```

---

## Task 1: Project Bootstrap

**Files:**
- Create: `frontend/` (directory)
- Create: `frontend/.env.local`
- Create: `frontend/next.config.ts`

- [ ] **Step 1: Scaffold Next.js 15 project**

Run from `c:\Users\moham\Desktop\alamin`:
```bash
npx create-next-app@latest frontend \
  --typescript \
  --tailwind \
  --eslint \
  --app \
  --src-dir \
  --import-alias "@/*" \
  --no-turbopack
```
When prompted, accept all defaults.

- [ ] **Step 2: Install additional dependencies**

```bash
cd frontend
npm install axios zustand@^5 react-hook-form@^7 zod@^3 @hookform/resolvers@^3 @tanstack/react-query@^5 lucide-react@^0.460 clsx tailwind-merge
```

- [ ] **Step 3: Create `.env.local`**

```env
NEXT_PUBLIC_API_URL=http://localhost:8000
```

- [ ] **Step 4: Update `next.config.ts`**

```typescript
import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // Allow backend images if needed later
  images: {
    remotePatterns: [
      { protocol: 'http', hostname: 'localhost' },
    ],
  },
}

export default nextConfig
```

- [ ] **Step 5: Verify dev server starts**

```bash
npm run dev
```
Expected: server runs at http://localhost:3000 without errors.

- [ ] **Step 6: Commit**

```bash
cd frontend
git add -A
git commit -m "feat: scaffold Next.js 15 frontend with base dependencies"
```

---

## Task 2: Types

**Files:**
- Create: `src/types/auth.ts`
- Create: `src/types/farm.ts`
- Create: `src/types/flock.ts`

- [ ] **Step 1: Create `src/types/auth.ts`**

```typescript
export interface User {
  id: number
  name: string
  email: string | null
  whatsapp: string | null
  avatar_path: string | null
  status: 'active' | 'inactive' | 'suspended'
  farms: UserFarm[]
}

export interface UserFarm {
  id: number
  name: string
  status: 'pending_setup' | 'active' | 'suspended'
  role: 'super_admin' | 'farm_admin' | 'partner' | 'worker'
  is_primary: boolean
}

export interface LoginPayload {
  login: string    // email or whatsapp
  password: string
}

export interface AuthResponse {
  token: string
  user: User
}
```

- [ ] **Step 2: Create `src/types/farm.ts`**

```typescript
export interface Farm {
  id: number
  name: string
  location: string | null
  status: 'pending_setup' | 'active' | 'suspended'
  role: 'super_admin' | 'farm_admin' | 'partner' | 'worker'
  is_primary: boolean
}
```

- [ ] **Step 3: Create `src/types/flock.ts`**

```typescript
export type FlockStatus = 'draft' | 'active' | 'closed' | 'cancelled'

export interface Flock {
  id: number
  farm_id: number
  name: string
  status: FlockStatus
  start_date: string        // ISO date string
  close_date: string | null
  initial_count: number
  current_age_days: number | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface CreateFlockPayload {
  name: string
  start_date: string
  initial_count: number
  notes?: string
}

export interface FlockListResponse {
  data: Flock[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/types/
git commit -m "feat: add TypeScript types for auth, farm, flock"
```

---

## Task 3: Design System — Tailwind Config + Base UI Components

**Files:**
- Modify: `tailwind.config.ts`
- Modify: `src/app/globals.css`
- Create: `src/lib/utils.ts`
- Create: `src/components/ui/Button.tsx`
- Create: `src/components/ui/Input.tsx`
- Create: `src/components/ui/Card.tsx`
- Create: `src/components/ui/Badge.tsx`
- Create: `src/components/ui/Skeleton.tsx`
- Create: `src/components/ui/Spinner.tsx`

- [ ] **Step 1: Update `tailwind.config.ts`**

```typescript
import type { Config } from 'tailwindcss'

const config: Config = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['var(--font-cairo)', 'Cairo', 'system-ui', 'sans-serif'],
      },
      colors: {
        primary: {
          50:  '#f0fdf4',
          100: '#dcfce7',
          200: '#bbf7d0',
          300: '#86efac',
          400: '#4ade80',
          500: '#22c55e',
          600: '#16a34a',
          700: '#15803d',
          800: '#166534',
          900: '#14532d',
          950: '#052e16',
        },
      },
      borderRadius: {
        lg: '0.625rem',
        xl: '0.875rem',
        '2xl': '1rem',
      },
    },
  },
  plugins: [],
}

export default config
```

- [ ] **Step 2: Update `src/app/globals.css`**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap');

:root {
  --font-cairo: 'Cairo', sans-serif;
}

* {
  box-sizing: border-box;
}

body {
  font-family: var(--font-cairo), sans-serif;
  direction: rtl;
  text-align: right;
}

/* Scrollbar styling */
::-webkit-scrollbar {
  width: 6px;
}
::-webkit-scrollbar-track {
  background: #f1f5f9;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
```

- [ ] **Step 3: Create `src/lib/utils.ts`**

```typescript
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

export function formatNumber(n: number): string {
  return n.toLocaleString('ar-SA')
}
```

- [ ] **Step 4: Create `src/components/ui/Button.tsx`**

```typescript
'use client'

import { cn } from '@/lib/utils'
import { type ButtonHTMLAttributes, forwardRef } from 'react'
import { Spinner } from './Spinner'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', size = 'md', loading, disabled, children, ...props }, ref) => {
    const base =
      'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:pointer-events-none disabled:opacity-50'

    const variants = {
      primary: 'bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-800',
      outline: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
      ghost:   'text-slate-600 hover:bg-slate-100',
      danger:  'bg-red-600 text-white hover:bg-red-700',
    }

    const sizes = {
      sm: 'h-8 px-3 text-sm',
      md: 'h-10 px-4 text-sm',
      lg: 'h-12 px-6 text-base',
    }

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        className={cn(base, variants[variant], sizes[size], className)}
        {...props}
      >
        {loading && <Spinner size="sm" />}
        {children}
      </button>
    )
  }
)
Button.displayName = 'Button'
```

- [ ] **Step 5: Create `src/components/ui/Spinner.tsx`**

```typescript
import { cn } from '@/lib/utils'

interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg'
  className?: string
}

export function Spinner({ size = 'md', className }: SpinnerProps) {
  const sizes = { sm: 'h-4 w-4', md: 'h-6 w-6', lg: 'h-8 w-8' }
  return (
    <svg
      className={cn('animate-spin text-current', sizes[size], className)}
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path
        className="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
      />
    </svg>
  )
}
```

- [ ] **Step 6: Create `src/components/ui/Input.tsx`**

```typescript
'use client'

import { cn } from '@/lib/utils'
import { type InputHTMLAttributes, forwardRef } from 'react'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  hint?: string
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, label, error, hint, id, ...props }, ref) => {
    return (
      <div className="flex flex-col gap-1.5">
        {label && (
          <label htmlFor={id} className="text-sm font-medium text-slate-700">
            {label}
            {props.required && <span className="text-red-500 mr-1">*</span>}
          </label>
        )}
        <input
          id={id}
          ref={ref}
          className={cn(
            'h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900',
            'placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20',
            'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500',
            error && 'border-red-400 focus:border-red-500 focus:ring-red-500/20',
            className
          )}
          {...props}
        />
        {error && <p className="text-xs text-red-600">{error}</p>}
        {!error && hint && <p className="text-xs text-slate-500">{hint}</p>}
      </div>
    )
  }
)
Input.displayName = 'Input'
```

- [ ] **Step 7: Create `src/components/ui/Card.tsx`**

```typescript
import { cn } from '@/lib/utils'
import { type HTMLAttributes } from 'react'

interface CardProps extends HTMLAttributes<HTMLDivElement> {}

export function Card({ className, children, ...props }: CardProps) {
  return (
    <div
      className={cn('rounded-xl border border-slate-200 bg-white shadow-sm', className)}
      {...props}
    >
      {children}
    </div>
  )
}

export function CardHeader({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('border-b border-slate-100 px-6 py-4', className)} {...props}>
      {children}
    </div>
  )
}

export function CardContent({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('px-6 py-4', className)} {...props}>
      {children}
    </div>
  )
}
```

- [ ] **Step 8: Create `src/components/ui/Badge.tsx`**

```typescript
import { cn } from '@/lib/utils'
import { type HTMLAttributes } from 'react'

type BadgeVariant = 'default' | 'success' | 'warning' | 'danger' | 'info' | 'neutral'

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant
}

export function Badge({ className, variant = 'default', children, ...props }: BadgeProps) {
  const variants: Record<BadgeVariant, string> = {
    default: 'bg-slate-100 text-slate-700',
    success: 'bg-green-100 text-green-700',
    warning: 'bg-amber-100 text-amber-700',
    danger:  'bg-red-100 text-red-700',
    info:    'bg-blue-100 text-blue-700',
    neutral: 'bg-slate-100 text-slate-500',
  }
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        variants[variant],
        className
      )}
      {...props}
    >
      {children}
    </span>
  )
}
```

- [ ] **Step 9: Create `src/components/ui/Skeleton.tsx`**

```typescript
import { cn } from '@/lib/utils'

interface SkeletonProps {
  className?: string
}

export function Skeleton({ className }: SkeletonProps) {
  return (
    <div
      className={cn('animate-pulse rounded-md bg-slate-200', className)}
    />
  )
}

export function SkeletonCard() {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 space-y-3">
          <Skeleton className="h-5 w-1/3" />
          <Skeleton className="h-4 w-1/2" />
        </div>
        <Skeleton className="h-6 w-16 rounded-full" />
      </div>
      <div className="mt-4 grid grid-cols-3 gap-4">
        <Skeleton className="h-12 rounded-lg" />
        <Skeleton className="h-12 rounded-lg" />
        <Skeleton className="h-12 rounded-lg" />
      </div>
    </div>
  )
}
```

- [ ] **Step 10: Commit**

```bash
git add src/
git commit -m "feat: add design system — Tailwind config, base UI components"
```

---

## Task 4: Axios HTTP Client

**Files:**
- Create: `src/lib/api/client.ts`
- Create: `src/lib/api/flocks.ts`

- [ ] **Step 1: Create `src/lib/api/client.ts`**

```typescript
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
```

- [ ] **Step 2: Create `src/lib/api/flocks.ts`**

```typescript
import { apiClient } from './client'
import type { CreateFlockPayload, Flock, FlockListResponse } from '@/types/flock'

export const flocksApi = {
  list: () =>
    apiClient.get<FlockListResponse>('/flocks').then((r) => r.data),

  get: (id: number) =>
    apiClient.get<{ data: Flock }>(`/flocks/${id}`).then((r) => r.data),

  create: (payload: CreateFlockPayload) =>
    apiClient.post<{ data: Flock; message: string }>('/flocks', payload).then((r) => r.data),

  update: (id: number, payload: Partial<CreateFlockPayload>) =>
    apiClient.put<{ data: Flock; message: string }>(`/flocks/${id}`, payload).then((r) => r.data),
}
```

- [ ] **Step 3: Commit**

```bash
git add src/lib/
git commit -m "feat: add Axios API client with token/farm interceptors"
```

---

## Task 5: Auth Store (Zustand)

**Files:**
- Create: `src/stores/auth.store.ts`

- [ ] **Step 1: Create `src/stores/auth.store.ts`**

```typescript
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
  // Max-age 7 days — middleware reads this for routing
  document.cookie = `auth_session=${value}; path=/; max-age=${7 * 24 * 3600}; SameSite=Lax`
}

function clearAuthCookie() {
  document.cookie = 'auth_session=; path=/; max-age=0'
}

// ── Store ─────────────────────────────────────────────────────────────────────
export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
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
        localStorage.setItem('auth_token', token)
        setAuthCookie('1')
        set({ token, user, isAuthenticated: true })
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
```

- [ ] **Step 2: Commit**

```bash
git add src/stores/auth.store.ts
git commit -m "feat: add Zustand auth store with localStorage persist + cookie sync"
```

---

## Task 6: Farm Store (Zustand)

**Files:**
- Create: `src/stores/farm.store.ts`

- [ ] **Step 1: Create `src/stores/farm.store.ts`**

```typescript
import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import type { Farm } from '@/types/farm'

interface FarmState {
  farms: Farm[]
  currentFarm: Farm | null

  setFarms: (farms: Farm[]) => void
  setCurrentFarm: (farm: Farm) => void
  clearFarm: () => void
}

export const useFarmStore = create<FarmState>()(
  persist(
    (set) => ({
      farms: [],
      currentFarm: null,

      setFarms: (farms) => set({ farms }),

      setCurrentFarm: (farm) => {
        if (typeof window !== 'undefined') {
          localStorage.setItem('current_farm_id', String(farm.id))
        }
        set({ currentFarm: farm })
      },

      clearFarm: () => {
        if (typeof window !== 'undefined') {
          localStorage.removeItem('current_farm_id')
        }
        set({ currentFarm: null })
      },
    }),
    {
      name: 'alamin-farm',
      storage: createJSONStorage(() =>
        typeof window !== 'undefined' ? localStorage : ({} as Storage)
      ),
      partialize: (state) => ({
        farms: state.farms,
        currentFarm: state.currentFarm,
      }),
    }
  )
)
```

- [ ] **Step 2: Commit**

```bash
git add src/stores/farm.store.ts
git commit -m "feat: add Zustand farm store with FarmSelector support"
```

---

## Task 7: Next.js Middleware

**Files:**
- Create: `src/middleware.ts`

- [ ] **Step 1: Create `src/middleware.ts`**

```typescript
import { type NextRequest, NextResponse } from 'next/server'

const PUBLIC_PATHS = ['/login']

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl

  const isPublic = PUBLIC_PATHS.some(
    (path) => pathname === path || pathname.startsWith(`${path}/`)
  )

  // Check presence of auth cookie (set by auth store on login)
  const hasSession = request.cookies.has('auth_session')

  if (!hasSession && !isPublic) {
    const loginUrl = new URL('/login', request.url)
    loginUrl.searchParams.set('from', pathname)
    return NextResponse.redirect(loginUrl)
  }

  if (hasSession && pathname === '/login') {
    return NextResponse.redirect(new URL('/flocks', request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: [
    // Skip Next.js internals, static files, and API routes
    '/((?!_next/static|_next/image|favicon.ico|api/).*)',
  ],
}
```

- [ ] **Step 2: Commit**

```bash
git add src/middleware.ts
git commit -m "feat: add Next.js middleware for auth-guarded routing"
```

---

## Task 8: Root Layout (RTL + Arabic)

**Files:**
- Modify: `src/app/layout.tsx`
- Modify: `src/app/page.tsx`

- [ ] **Step 1: Update `src/app/layout.tsx`**

```typescript
import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'دجاجاتي — إدارة المداجن',
  description: 'نظام إدارة مداجن الدواجن',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="ar" dir="rtl">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link
          href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap"
          rel="stylesheet"
        />
      </head>
      <body className="min-h-screen bg-slate-50 text-slate-900 antialiased">
        {children}
      </body>
    </html>
  )
}
```

- [ ] **Step 2: Update `src/app/page.tsx`**

```typescript
import { redirect } from 'next/navigation'

// Root "/" redirects to flocks; middleware handles unauth redirect to /login
export default function RootPage() {
  redirect('/flocks')
}
```

- [ ] **Step 3: Verify RTL in browser**

Start the dev server (`npm run dev`), open http://localhost:3000. Confirm that the page redirects and the HTML element has `dir="rtl"` and `lang="ar"`.

- [ ] **Step 4: Commit**

```bash
git add src/app/layout.tsx src/app/page.tsx
git commit -m "feat: RTL Arabic root layout with Cairo font"
```

---

## Task 9: Login Page

**Files:**
- Create: `src/app/(auth)/login/page.tsx`

- [ ] **Step 1: Create `src/app/(auth)/login/page.tsx`**

```typescript
'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useRouter, useSearchParams } from 'next/navigation'
import { useAuthStore } from '@/stores/auth.store'
import { useFarmStore } from '@/stores/farm.store'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import type { Farm } from '@/types/farm'

// ── Zod schema ────────────────────────────────────────────────────────────────
const loginSchema = z.object({
  login: z.string().min(1, 'البريد الإلكتروني أو رقم الواتساب مطلوب'),
  password: z.string().min(1, 'كلمة المرور مطلوبة'),
})
type LoginForm = z.infer<typeof loginSchema>

// ── Component ─────────────────────────────────────────────────────────────────
export default function LoginPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { login } = useAuthStore()
  const { setFarms, setCurrentFarm } = useFarmStore()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({ resolver: zodResolver(loginSchema) })

  const onSubmit = async (data: LoginForm) => {
    setServerError(null)
    try {
      await login(data.login, data.password)

      // Hydrate farm store from user data returned by auth store
      const user = useAuthStore.getState().user
      if (user?.farms?.length) {
        const farms: Farm[] = user.farms.map((f) => ({
          id: f.id,
          name: f.name,
          status: f.status,
          role: f.role,
          is_primary: f.is_primary,
        }))
        setFarms(farms)

        // Auto-select primary farm, or first one
        const primaryFarm = farms.find((f) => f.is_primary) ?? farms[0]
        setCurrentFarm(primaryFarm)
      }

      const from = searchParams.get('from') ?? '/flocks'
      router.replace(from)
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string } } }
      setServerError(
        axiosError?.response?.data?.message ?? 'حدث خطأ غير متوقع، حاول مجدداً'
      )
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-primary-50 to-slate-100 p-4">
      <div className="w-full max-w-md">
        {/* Logo / Brand */}
        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-600 shadow-lg">
            <span className="text-3xl">🐔</span>
          </div>
          <h1 className="text-2xl font-bold text-slate-900">دجاجاتي</h1>
          <p className="mt-1 text-sm text-slate-500">نظام إدارة المداجن</p>
        </div>

        {/* Card */}
        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
          <h2 className="mb-6 text-xl font-semibold text-slate-800">تسجيل الدخول</h2>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
            <Input
              {...register('login')}
              id="login"
              label="البريد الإلكتروني أو الواتساب"
              placeholder="أدخل البريد الإلكتروني أو رقم الواتساب"
              type="text"
              autoComplete="username"
              error={errors.login?.message}
              required
            />

            <Input
              {...register('password')}
              id="password"
              label="كلمة المرور"
              placeholder="أدخل كلمة المرور"
              type="password"
              autoComplete="current-password"
              error={errors.password?.message}
              required
            />

            {serverError && (
              <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                {serverError}
              </div>
            )}

            <Button
              type="submit"
              className="w-full"
              size="lg"
              loading={isSubmitting}
            >
              دخول
            </Button>
          </form>
        </div>

        <p className="mt-6 text-center text-xs text-slate-400">
          © {new Date().getFullYear()} دجاجاتي — جميع الحقوق محفوظة
        </p>
      </div>
    </div>
  )
}
```

- [ ] **Step 2: Add `(auth)` layout to avoid RTL conflicts**

Create `src/app/(auth)/layout.tsx`:
```typescript
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>
}
```

- [ ] **Step 3: Manually test login flow**

Start the backend (`cd backend && php artisan serve`), then start the frontend (`npm run dev`).

Navigate to http://localhost:3000/login. Log in with valid credentials. Verify:
1. Redirect happens to `/flocks`
2. `auth_session` cookie is set
3. `alamin-auth` key in localStorage has token and user

- [ ] **Step 4: Commit**

```bash
git add src/app/\(auth\)/
git commit -m "feat: login page with react-hook-form + zod + farm auto-select"
```

---

## Task 10: Farm Layout — Sidebar + Header + FarmSelector

**Files:**
- Create: `src/app/(farm)/layout.tsx`
- Create: `src/components/layout/Sidebar.tsx`
- Create: `src/components/layout/Header.tsx`
- Create: `src/components/layout/FarmSelector.tsx`

- [ ] **Step 1: Create `src/components/layout/FarmSelector.tsx`**

```typescript
'use client'

import { useFarmStore } from '@/stores/farm.store'
import { useAuthStore } from '@/stores/auth.store'
import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import { cn } from '@/lib/utils'
import type { Farm } from '@/types/farm'

export function FarmSelector() {
  const { farms, currentFarm, setCurrentFarm } = useFarmStore()
  const { user } = useAuthStore()
  const [open, setOpen] = useState(false)

  // Sync farms from user if not in store yet
  const availableFarms: Farm[] = farms.length
    ? farms
    : (user?.farms ?? []).map((f) => ({
        id: f.id,
        name: f.name,
        status: f.status,
        role: f.role,
        is_primary: f.is_primary,
      }))

  if (!currentFarm) return null

  return (
    <div className="relative">
      <button
        onClick={() => setOpen((o) => !o)}
        className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
      >
        <span className="max-w-[140px] truncate">{currentFarm.name}</span>
        <ChevronDown className={cn('h-4 w-4 transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <>
          {/* Backdrop */}
          <div
            className="fixed inset-0 z-10"
            onClick={() => setOpen(false)}
          />
          {/* Dropdown */}
          <div className="absolute start-0 top-full z-20 mt-1 min-w-[200px] rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
            {availableFarms.map((farm) => (
              <button
                key={farm.id}
                onClick={() => {
                  setCurrentFarm(farm)
                  setOpen(false)
                }}
                className={cn(
                  'flex w-full items-center gap-3 px-4 py-2.5 text-sm transition hover:bg-slate-50',
                  currentFarm.id === farm.id && 'bg-primary-50 text-primary-700 font-medium'
                )}
              >
                <span className="flex-1 text-start truncate">{farm.name}</span>
                {currentFarm.id === farm.id && (
                  <span className="h-2 w-2 rounded-full bg-primary-500" />
                )}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  )
}
```

- [ ] **Step 2: Create `src/components/layout/Header.tsx`**

```typescript
'use client'

import { FarmSelector } from './FarmSelector'
import { useAuthStore } from '@/stores/auth.store'
import { useRouter } from 'next/navigation'
import { LogOut, User } from 'lucide-react'
import { apiClient } from '@/lib/api/client'

export function Header() {
  const { user, logout } = useAuthStore()
  const router = useRouter()

  const handleLogout = async () => {
    try {
      await apiClient.post('/auth/logout')
    } catch {
      // ignore — logout anyway
    } finally {
      logout()
      router.replace('/login')
    }
  }

  return (
    <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
      {/* Farm selector on the right (RTL: start) */}
      <FarmSelector />

      {/* User menu on the left (RTL: end) */}
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2 text-sm text-slate-600">
          <User className="h-4 w-4" />
          <span className="max-w-[120px] truncate font-medium">{user?.name}</span>
        </div>
        <button
          onClick={handleLogout}
          className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-slate-500 transition hover:bg-red-50 hover:text-red-600"
          title="تسجيل الخروج"
        >
          <LogOut className="h-4 w-4" />
          <span>خروج</span>
        </button>
      </div>
    </header>
  )
}
```

- [ ] **Step 3: Create `src/components/layout/Sidebar.tsx`**

```typescript
'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import {
  LayoutDashboard,
  Bird,
  Package,
  ShoppingCart,
  Receipt,
  Users,
  BarChart3,
} from 'lucide-react'

const NAV_ITEMS = [
  { label: 'لوحة التحكم',  href: '/dashboard',  icon: LayoutDashboard },
  { label: 'الأفواج',      href: '/flocks',      icon: Bird },
  { label: 'المخزون',      href: '/inventory',   icon: Package },
  { label: 'المبيعات',     href: '/sales',       icon: ShoppingCart },
  { label: 'المصروفات',    href: '/expenses',    icon: Receipt },
  { label: 'الشركاء',      href: '/partners',    icon: Users },
  { label: 'التقارير',     href: '/reports',     icon: BarChart3 },
]

export function Sidebar() {
  const pathname = usePathname()

  return (
    <aside className="flex w-60 flex-col border-l border-slate-200 bg-white">
      {/* Brand */}
      <div className="flex h-16 items-center gap-3 border-b border-slate-200 px-5">
        <span className="text-2xl">🐔</span>
        <span className="text-lg font-bold text-slate-900">دجاجاتي</span>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-1">
          {NAV_ITEMS.map(({ label, href, icon: Icon }) => {
            const isActive = pathname === href || pathname.startsWith(`${href}/`)
            return (
              <li key={href}>
                <Link
                  href={href}
                  className={cn(
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                    isActive
                      ? 'bg-primary-50 text-primary-700'
                      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                  )}
                >
                  <Icon className={cn('h-5 w-5', isActive ? 'text-primary-600' : 'text-slate-400')} />
                  {label}
                </Link>
              </li>
            )
          })}
        </ul>
      </nav>
    </aside>
  )
}
```

- [ ] **Step 4: Create `src/app/(farm)/layout.tsx`**

```typescript
import { Sidebar } from '@/components/layout/Sidebar'
import { Header } from '@/components/layout/Header'

export default function FarmLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex h-screen overflow-hidden bg-slate-50">
      {/* Sidebar on the right (RTL) */}
      <Sidebar />

      {/* Main area */}
      <div className="flex flex-1 flex-col overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto p-6">
          {children}
        </main>
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Create placeholder dashboard page**

Create `src/app/(farm)/dashboard/page.tsx`:
```typescript
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'

export default function DashboardPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-900">لوحة التحكم</h1>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i}>
            <CardContent className="py-5">
              <Skeleton className="mb-2 h-4 w-24" />
              <Skeleton className="h-8 w-16" />
            </CardContent>
          </Card>
        ))}
      </div>
      <Card>
        <CardHeader>
          <Skeleton className="h-5 w-40" />
        </CardHeader>
        <CardContent>
          <Skeleton className="h-48 w-full" />
        </CardContent>
      </Card>
    </div>
  )
}
```

- [ ] **Step 6: Commit**

```bash
git add src/app/\(farm\)/ src/components/layout/
git commit -m "feat: farm shell layout — sidebar, header, farm selector"
```

---

## Task 11: Flocks List Page

**Files:**
- Create: `src/components/flocks/FlockStatusBadge.tsx`
- Create: `src/components/flocks/FlockCard.tsx`
- Create: `src/app/(farm)/flocks/page.tsx`

- [ ] **Step 1: Create `src/components/flocks/FlockStatusBadge.tsx`**

```typescript
import { Badge } from '@/components/ui/Badge'
import type { FlockStatus } from '@/types/flock'

const STATUS_MAP: Record<FlockStatus, { label: string; variant: 'success' | 'info' | 'neutral' | 'danger' }> = {
  active:    { label: 'نشط',     variant: 'success' },
  draft:     { label: 'مسودة',   variant: 'info' },
  closed:    { label: 'مغلق',    variant: 'neutral' },
  cancelled: { label: 'ملغى',    variant: 'danger' },
}

interface FlockStatusBadgeProps {
  status: FlockStatus
}

export function FlockStatusBadge({ status }: FlockStatusBadgeProps) {
  const { label, variant } = STATUS_MAP[status] ?? { label: status, variant: 'neutral' }
  return <Badge variant={variant}>{label}</Badge>
}
```

- [ ] **Step 2: Create `src/components/flocks/FlockCard.tsx`**

```typescript
import Link from 'next/link'
import { Card, CardContent } from '@/components/ui/Card'
import { FlockStatusBadge } from './FlockStatusBadge'
import { formatDate, formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'
import { Bird, Calendar, ArrowLeft } from 'lucide-react'

interface FlockCardProps {
  flock: Flock
}

export function FlockCard({ flock }: FlockCardProps) {
  return (
    <Link href={`/flocks/${flock.id}`} className="block">
      <Card className="transition hover:border-primary-300 hover:shadow-md">
        <CardContent className="py-5">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <Bird className="h-4 w-4 shrink-0 text-primary-600" />
                <h3 className="truncate font-semibold text-slate-900">{flock.name}</h3>
              </div>
              <div className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                <Calendar className="h-3.5 w-3.5" />
                <span>{formatDate(flock.start_date)}</span>
                {flock.current_age_days !== null && (
                  <span className="text-slate-400">• يوم {flock.current_age_days}</span>
                )}
              </div>
            </div>
            <FlockStatusBadge status={flock.status} />
          </div>

          <div className="mt-4 grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 text-center">
            <div>
              <p className="text-xs text-slate-500">العدد الأولي</p>
              <p className="mt-0.5 font-semibold text-slate-900">{formatNumber(flock.initial_count)}</p>
            </div>
            <div>
              <p className="text-xs text-slate-500">الحالة</p>
              <p className="mt-0.5 font-semibold text-slate-900 capitalize">{flock.status}</p>
            </div>
          </div>

          <div className="mt-3 flex items-center justify-end text-xs font-medium text-primary-600">
            <span>عرض التفاصيل</span>
            <ArrowLeft className="ms-1 h-3.5 w-3.5" />
          </div>
        </CardContent>
      </Card>
    </Link>
  )
}
```

- [ ] **Step 3: Create `src/app/(farm)/flocks/page.tsx`**

```typescript
'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { Bird, Plus, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { SkeletonCard } from '@/components/ui/Skeleton'
import { FlockCard } from '@/components/flocks/FlockCard'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import type { Flock } from '@/types/flock'

export default function FlocksPage() {
  const { currentFarm } = useFarmStore()
  const [flocks, setFlocks] = useState<Flock[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!currentFarm) return

    setLoading(true)
    setError(null)

    flocksApi
      .list()
      .then((res) => setFlocks(res.data))
      .catch((err) => {
        // 404 or backend not ready → show empty state
        if (err?.response?.status === 404) {
          setFlocks([])
        } else {
          setError('تعذّر تحميل قائمة الأفواج. تأكد من تشغيل الخادم.')
        }
      })
      .finally(() => setLoading(false))
  }, [currentFarm])

  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">الأفواج</h1>
          {currentFarm && (
            <p className="mt-0.5 text-sm text-slate-500">{currentFarm.name}</p>
          )}
        </div>
        <Button asChild>
          <Link href="/flocks/new">
            <Plus className="h-4 w-4" />
            فوج جديد
          </Link>
        </Button>
      </div>

      {/* Loading skeletons */}
      {loading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => <SkeletonCard key={i} />)}
        </div>
      )}

      {/* Error state */}
      {!loading && error && (
        <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {/* Empty state */}
      {!loading && !error && flocks.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
          <Bird className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-lg font-semibold text-slate-700">لا توجد أفواج بعد</h3>
          <p className="mt-1 text-sm text-slate-500">ابدأ بإنشاء فوجك الأول لهذه المزرعة</p>
          <Button asChild className="mt-5">
            <Link href="/flocks/new">
              <Plus className="h-4 w-4" />
              إنشاء فوج جديد
            </Link>
          </Button>
        </div>
      )}

      {/* Flocks grid */}
      {!loading && !error && flocks.length > 0 && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {flocks.map((flock) => (
            <FlockCard key={flock.id} flock={flock} />
          ))}
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 4: Fix `Button` to support `asChild` prop**

Update `src/components/ui/Button.tsx` — add `asChild` support using `Slot` pattern:

```typescript
'use client'

import { cn } from '@/lib/utils'
import { type ButtonHTMLAttributes, forwardRef } from 'react'
import { Spinner } from './Spinner'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  asChild?: boolean
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', size = 'md', loading, disabled, asChild, children, ...props }, ref) => {
    const base =
      'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:pointer-events-none disabled:opacity-50'

    const variants = {
      primary: 'bg-primary-600 text-white hover:bg-primary-700 active:bg-primary-800',
      outline: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
      ghost:   'text-slate-600 hover:bg-slate-100',
      danger:  'bg-red-600 text-white hover:bg-red-700',
    }

    const sizes = {
      sm: 'h-8 px-3 text-sm',
      md: 'h-10 px-4 text-sm',
      lg: 'h-12 px-6 text-base',
    }

    const allClasses = cn(base, variants[variant], sizes[size], className)

    // When asChild, clone the child and apply button classes
    if (asChild && children && typeof children === 'object' && 'type' in (children as object)) {
      const child = children as React.ReactElement
      return (
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        <child.type {...child.props} className={cn(allClasses, child.props.className)} ref={ref} />
      )
    }

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        className={allClasses}
        {...props}
      >
        {loading && <Spinner size="sm" />}
        {children}
      </button>
    )
  }
)
Button.displayName = 'Button'
```

- [ ] **Step 5: Commit**

```bash
git add src/components/flocks/ src/app/\(farm\)/flocks/
git commit -m "feat: flocks list page with loading/empty/error states"
```

---

## Task 12: Create Flock Page

**Files:**
- Create: `src/app/(farm)/flocks/new/page.tsx`

- [ ] **Step 1: Create `src/app/(farm)/flocks/new/page.tsx`**

```typescript
'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useRouter } from 'next/navigation'
import { ArrowRight } from 'lucide-react'
import Link from 'next/link'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { flocksApi } from '@/lib/api/flocks'

// ── Validation schema ────────────────────────────────────────────────────────
const createFlockSchema = z.object({
  name: z
    .string()
    .min(2, 'اسم الفوج يجب أن يكون حرفين على الأقل')
    .max(190, 'اسم الفوج طويل جداً'),
  start_date: z
    .string()
    .min(1, 'تاريخ البدء مطلوب')
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'صيغة التاريخ غير صحيحة'),
  initial_count: z
    .number({ invalid_type_error: 'يجب إدخال رقم صحيح' })
    .int('يجب أن يكون عدداً صحيحاً')
    .positive('العدد الأولي يجب أن يكون أكبر من الصفر'),
  notes: z.string().max(1000, 'الملاحظات طويلة جداً').optional().or(z.literal('')),
})

type CreateFlockForm = z.infer<typeof createFlockSchema>

// ── Component ────────────────────────────────────────────────────────────────
export default function CreateFlockPage() {
  const router = useRouter()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<CreateFlockForm>({
    resolver: zodResolver(createFlockSchema),
    defaultValues: {
      start_date: new Date().toISOString().split('T')[0],
    },
  })

  const onSubmit = async (data: CreateFlockForm) => {
    setServerError(null)
    try {
      const result = await flocksApi.create({
        name: data.name,
        start_date: data.start_date,
        initial_count: data.initial_count,
        notes: data.notes || undefined,
      })
      router.push(`/flocks/${result.data.id}`)
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const msg = axiosErr?.response?.data?.message
      // Show first validation error if available
      const firstError = axiosErr?.response?.data?.errors
        ? Object.values(axiosErr.response.data.errors)[0]?.[0]
        : null
      setServerError(firstError ?? msg ?? 'حدث خطأ غير متوقع')
    }
  }

  return (
    <div className="mx-auto max-w-xl space-y-6">
      {/* Back link */}
      <Link
        href="/flocks"
        className="inline-flex items-center gap-1.5 text-sm text-slate-500 transition hover:text-slate-900"
      >
        <ArrowRight className="h-4 w-4" />
        العودة للأفواج
      </Link>

      <div>
        <h1 className="text-2xl font-bold text-slate-900">فوج جديد</h1>
        <p className="mt-1 text-sm text-slate-500">أدخل بيانات الفوج الجديد للمزرعة</p>
      </div>

      <Card>
        <CardHeader>
          <h2 className="font-semibold text-slate-800">بيانات الفوج</h2>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5" noValidate>
            <Input
              {...register('name')}
              id="name"
              label="اسم الفوج"
              placeholder="مثال: فوج أبريل 2026"
              error={errors.name?.message}
              required
            />

            <Input
              {...register('start_date')}
              id="start_date"
              label="تاريخ البدء"
              type="date"
              error={errors.start_date?.message}
              required
            />

            <Input
              {...register('initial_count', { valueAsNumber: true })}
              id="initial_count"
              label="العدد الأولي (عدد الكتاكيت)"
              type="number"
              min={1}
              placeholder="مثال: 5000"
              error={errors.initial_count?.message}
              required
            />

            <div className="flex flex-col gap-1.5">
              <label htmlFor="notes" className="text-sm font-medium text-slate-700">
                ملاحظات (اختياري)
              </label>
              <textarea
                {...register('notes')}
                id="notes"
                rows={3}
                placeholder="أي ملاحظات إضافية عن الفوج..."
                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
              />
              {errors.notes && (
                <p className="text-xs text-red-600">{errors.notes.message}</p>
              )}
            </div>

            {serverError && (
              <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                {serverError}
              </div>
            )}

            <div className="flex items-center justify-end gap-3 pt-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => router.back()}
              >
                إلغاء
              </Button>
              <Button type="submit" loading={isSubmitting}>
                إنشاء الفوج
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
```

- [ ] **Step 2: Commit**

```bash
git add src/app/\(farm\)/flocks/new/
git commit -m "feat: create flock page with form validation + API call"
```

---

## Task 13: Flock Details Shell

**Files:**
- Create: `src/app/(farm)/flocks/[id]/page.tsx`

- [ ] **Step 1: Create `src/app/(farm)/flocks/[id]/page.tsx`**

```typescript
'use client'

import { useEffect, useState } from 'react'
import { use } from 'react'
import Link from 'next/link'
import { ArrowRight, AlertCircle } from 'lucide-react'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { flocksApi } from '@/lib/api/flocks'
import { formatDate, formatNumber } from '@/lib/utils'
import { cn } from '@/lib/utils'
import type { Flock } from '@/types/flock'

// ── Tab definition ────────────────────────────────────────────────────────────
type TabKey = 'mortalities' | 'feed-logs' | 'medicine-logs' | 'water-logs' | 'notes' | 'sales'

const TABS: { key: TabKey; label: string }[] = [
  { key: 'mortalities',   label: 'النفوق' },
  { key: 'feed-logs',     label: 'العلف' },
  { key: 'medicine-logs', label: 'الدواء' },
  { key: 'water-logs',    label: 'المياه' },
  { key: 'notes',         label: 'الملاحظات' },
  { key: 'sales',         label: 'المبيعات' },
]

// ── Skeleton loading ──────────────────────────────────────────────────────────
function FlockDetailsSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-7 w-48" />
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i}>
            <CardContent className="py-4">
              <Skeleton className="mb-1 h-3 w-20" />
              <Skeleton className="h-7 w-16" />
            </CardContent>
          </Card>
        ))}
      </div>
      <Skeleton className="h-10 w-full rounded-lg" />
      <Skeleton className="h-64 w-full rounded-xl" />
    </div>
  )
}

// ── Tab panel placeholder ─────────────────────────────────────────────────────
function TabPlaceholder({ tab }: { tab: TabKey }) {
  const labels: Record<TabKey, string> = {
    mortalities:   'سجلات النفوق',
    'feed-logs':     'سجلات العلف',
    'medicine-logs': 'سجلات الدواء',
    'water-logs':    'سجلات المياه',
    notes:         'الملاحظات',
    sales:         'المبيعات',
  }
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center text-slate-500">
      <p className="text-base font-medium">{labels[tab]}</p>
      <p className="mt-1 text-sm">قيد الإنشاء — سيُضاف قريباً</p>
    </div>
  )
}

// ── Main component ────────────────────────────────────────────────────────────
export default function FlockDetailPage({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = use(params)
  const flockId = Number(id)

  const [flock, setFlock] = useState<Flock | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<TabKey>('mortalities')

  useEffect(() => {
    setLoading(true)
    flocksApi
      .get(flockId)
      .then((res) => setFlock(res.data))
      .catch((err) => {
        if (err?.response?.status === 404) {
          setError('الفوج غير موجود')
        } else {
          setError('تعذّر تحميل بيانات الفوج')
        }
      })
      .finally(() => setLoading(false))
  }, [flockId])

  if (loading) return <FlockDetailsSkeleton />

  if (error) {
    return (
      <div className="space-y-4">
        <Link href="/flocks" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900">
          <ArrowRight className="h-4 w-4" />
          العودة للأفواج
        </Link>
        <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      </div>
    )
  }

  if (!flock) return null

  return (
    <div className="space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center gap-2 text-sm text-slate-500">
        <Link href="/flocks" className="hover:text-slate-900">الأفواج</Link>
        <span>/</span>
        <span className="font-medium text-slate-900">{flock.name}</span>
      </div>

      {/* Title row */}
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">{flock.name}</h1>
          <p className="mt-0.5 text-sm text-slate-500">
            بدأ في {formatDate(flock.start_date)}
            {flock.current_age_days !== null && ` · العمر: ${flock.current_age_days} يوم`}
          </p>
        </div>
        <FlockStatusBadge status={flock.status} />
      </div>

      {/* Stats cards */}
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Card>
          <CardContent className="py-4">
            <p className="text-xs text-slate-500">العدد الأولي</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">
              {formatNumber(flock.initial_count)}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="py-4">
            <p className="text-xs text-slate-500">تاريخ البدء</p>
            <p className="mt-1 text-base font-semibold text-slate-900">
              {formatDate(flock.start_date)}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="py-4">
            <p className="text-xs text-slate-500">عمر الفوج</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">
              {flock.current_age_days !== null ? `${flock.current_age_days} يوم` : '—'}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="py-4">
            <p className="text-xs text-slate-500">الحالة</p>
            <div className="mt-2">
              <FlockStatusBadge status={flock.status} />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Notes */}
      {flock.notes && (
        <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {flock.notes}
        </div>
      )}

      {/* Tabs */}
      <div>
        <div className="border-b border-slate-200">
          <nav className="flex gap-1 overflow-x-auto">
            {TABS.map(({ key, label }) => (
              <button
                key={key}
                onClick={() => setActiveTab(key)}
                className={cn(
                  'whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors',
                  activeTab === key
                    ? 'border-primary-600 text-primary-700'
                    : 'border-transparent text-slate-500 hover:text-slate-900'
                )}
              >
                {label}
              </button>
            ))}
          </nav>
        </div>

        <Card className="mt-4 rounded-t-none border-t-0">
          <CardContent>
            <TabPlaceholder tab={activeTab} />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
```

- [ ] **Step 2: Commit**

```bash
git add src/app/\(farm\)/flocks/\[id\]/
git commit -m "feat: flock details shell with stats, tabs, skeleton, error state"
```

---

## Task 14: Skeleton Pages for Unimplemented Sections

**Files:**
- Create: `src/app/(farm)/inventory/page.tsx`
- Create: `src/app/(farm)/sales/page.tsx`
- Create: `src/app/(farm)/expenses/page.tsx`
- Create: `src/app/(farm)/partners/page.tsx`
- Create: `src/app/(farm)/reports/page.tsx`

- [ ] **Step 1: Create skeleton pages for sidebar links**

Create `src/app/(farm)/inventory/page.tsx`:
```typescript
import { Skeleton } from '@/components/ui/Skeleton'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'

export default function InventoryPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">المخزون</h1>
        <Skeleton className="h-10 w-32 rounded-lg" />
      </div>
      <Card>
        <CardHeader><Skeleton className="h-5 w-40" /></CardHeader>
        <CardContent className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full rounded-lg" />
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
```

Create `src/app/(farm)/sales/page.tsx`:
```typescript
import { Skeleton } from '@/components/ui/Skeleton'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'

export default function SalesPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">المبيعات</h1>
        <Skeleton className="h-10 w-32 rounded-lg" />
      </div>
      <Card>
        <CardHeader><Skeleton className="h-5 w-40" /></CardHeader>
        <CardContent className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full rounded-lg" />
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
```

Create `src/app/(farm)/expenses/page.tsx`:
```typescript
import { Skeleton } from '@/components/ui/Skeleton'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'

export default function ExpensesPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">المصروفات</h1>
        <Skeleton className="h-10 w-32 rounded-lg" />
      </div>
      <Card>
        <CardHeader><Skeleton className="h-5 w-40" /></CardHeader>
        <CardContent className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full rounded-lg" />
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
```

Create `src/app/(farm)/partners/page.tsx`:
```typescript
import { Skeleton } from '@/components/ui/Skeleton'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'

export default function PartnersPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">الشركاء</h1>
        <Skeleton className="h-10 w-32 rounded-lg" />
      </div>
      <Card>
        <CardHeader><Skeleton className="h-5 w-40" /></CardHeader>
        <CardContent className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-14 w-full rounded-lg" />
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
```

Create `src/app/(farm)/reports/page.tsx`:
```typescript
import { Skeleton } from '@/components/ui/Skeleton'
import { Card, CardContent, CardHeader } from '@/components/ui/Card'

export default function ReportsPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-slate-900">التقارير</h1>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {Array.from({ length: 4 }).map((_, i) => (
          <Card key={i}>
            <CardHeader><Skeleton className="h-5 w-36" /></CardHeader>
            <CardContent>
              <Skeleton className="h-32 w-full" />
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
```

- [ ] **Step 2: Commit**

```bash
git add src/app/\(farm\)/inventory/ src/app/\(farm\)/sales/ src/app/\(farm\)/expenses/ src/app/\(farm\)/partners/ src/app/\(farm\)/reports/
git commit -m "feat: skeleton placeholder pages for inventory, sales, expenses, partners, reports"
```

---

## Self-Review

### Spec Coverage

| Requirement | Task |
|---|---|
| Project bootstrap | Task 1 |
| RTL + Arabic layout | Tasks 3, 8 |
| Design system | Task 3 |
| Axios client with X-Farm-Id | Task 4 |
| Auth store (localStorage + cookie) | Task 5 |
| Farm store + FarmSelector | Tasks 6, 10 |
| Next.js middleware | Task 7 |
| Login page | Task 9 |
| Farm layout (sidebar + header) | Task 10 |
| Flocks list page | Task 11 |
| Create flock page | Task 12 |
| Flock details shell with tabs | Task 13 |
| Skeleton pages for unimplemented backend | Tasks 11, 13, 14 |
| No daily-records/harvests | ✅ Tabs use mortalities/feed-logs/medicine-logs/water-logs/notes/sales |
| Multi-farm FarmSelector | Tasks 6, 10 |

### Type Consistency Check

- `Farm` type in `types/farm.ts` matches what `useFarmStore` expects ✅
- `UserFarm` in `types/auth.ts` has the same `role` union as `Farm.role` ✅
- `Flock.status` type used in `FlockStatusBadge` matches `types/flock.ts` ✅
- `flocksApi.list()` returns `FlockListResponse` which has `data: Flock[]` ✅
- `flocksApi.get()` returns `{ data: Flock }` used by detail page ✅
- `Button` with `asChild` used in flocks page ✅
