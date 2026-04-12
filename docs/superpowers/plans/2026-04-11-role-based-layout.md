# Role-Based Layout + Navigation + Route Guards — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce role-based navigation, sidebar filtering, and client-side route guards for the four system roles (`super_admin`, `farm_admin`, `worker`, `partner`) without breaking existing auth or farm stores.

**Architecture:** Roles flow from the backend via `BuildUserDataAction` → `auth_session` cookie → Zustand stores. The existing `src/proxy.ts` handles auth-only protection (already running in Next.js 16 where `proxy.ts` replaces `middleware.ts`). Role enforcement is done client-side via a `RoleGuard` React component in the farm layout — the backend already enforces permissions at the API layer. Role utilities are centralized in `src/lib/roles.ts`.

**Tech Stack:** Next.js 16 App Router · TypeScript · Zustand · Tailwind CSS v4 · RTL Arabic UI

---

## Codebase context (read before starting any task)

### Existing files to read first

| File | Role |
|------|------|
| `src/types/auth.ts` | Defines `User`, `UserFarm` — currently has wrong roles (`owner\|manager\|viewer`) |
| `src/types/farm.ts` | Defines `Farm` — same wrong roles |
| `src/stores/auth.store.ts` | Zustand auth store — reads `user.farms[]` from backend |
| `src/stores/farm.store.ts` | Zustand farm store — `currentFarm.role` is the active role |
| `src/proxy.ts` | Next.js 16 proxy (= middleware) — already running, auth-cookie check only |
| `src/components/layout/Sidebar.tsx` | Static nav items, no role filtering |
| `src/app/(farm)/layout.tsx` | Farm shell — Sidebar + Header + children |
| `src/app/(farm)/flocks/page.tsx` | Flock list with "فوج جديد" button visible to all |
| `src/app/(auth)/login/page.tsx` | Login — redirects to `/flocks` regardless of role |

### Actual backend roles

From `BuildUserDataAction.php` and `database_schema.md`:
- `super_admin` — global, no farm context (can see all farms)
- `farm_admin` — per-farm, full access
- `worker` — per-farm, operational only (flocks + inventory)
- `partner` — per-farm, read-only (flocks + reports)

### Role-to-nav access matrix

```
Route               | super_admin | farm_admin | worker | partner
--------------------|-------------|------------|--------|--------
/dashboard          | ✓           | ✓          | ✗      | ✗
/flocks (list)      | ✓           | ✓          | ✓      | ✓ (ro)
/flocks/new         | ✓           | ✓          | ✗      | ✗
/flocks/[id]        | ✓           | ✓          | ✓      | ✓ (ro)
/inventory          | ✓           | ✓          | ✓      | ✗
/sales              | ✓           | ✓          | ✗      | ✗
/expenses           | ✓           | ✓          | ✗      | ✗
/partners           | ✓           | ✓          | ✗      | ✗
/reports            | ✓           | ✓          | ✗      | ✓ (ro)
```

### Important: proxy.ts is already the middleware

In Next.js 16, `middleware.ts` was deprecated and renamed to `proxy.ts`. The existing `src/proxy.ts` IS already running as middleware. No new file is needed for auth protection — it already redirects unauthenticated users to `/login`.

---

## File map

**Create:**
- `frontend/src/lib/roles.ts`
- `frontend/src/components/layout/RoleGuard.tsx`
- `frontend/src/app/(farm)/unauthorized/page.tsx`

**Modify:**
- `frontend/src/types/auth.ts`
- `frontend/src/types/farm.ts`
- `frontend/src/components/layout/Sidebar.tsx`
- `frontend/src/app/(farm)/layout.tsx`
- `frontend/src/app/(farm)/flocks/page.tsx`
- `frontend/src/app/(auth)/login/page.tsx`

---

## Task 1: Fix role types + create role utilities

**Files:**
- Modify: `frontend/src/types/auth.ts`
- Modify: `frontend/src/types/farm.ts`
- Create: `frontend/src/lib/roles.ts`

- [ ] **Step 1: Update `frontend/src/types/auth.ts`**

Read the file first, then replace it entirely with:

```typescript
// frontend/src/types/auth.ts

export type FarmRole = 'super_admin' | 'farm_admin' | 'worker' | 'partner'

export interface User {
  id: number
  name: string
  email: string | null
  whatsapp: string | null
  status: 'active' | 'inactive' | 'suspended'
  last_login_at: string | null
  farms: UserFarm[]
}

export interface UserFarm {
  id: number
  name: string
  status: 'active' | 'pending_setup' | 'suspended'
  role: FarmRole | null
  is_primary: boolean
}

export interface LoginResponse {
  token: string
  user: User
}
```

- [ ] **Step 2: Update `frontend/src/types/farm.ts`**

Read the file first, then replace it entirely with:

```typescript
// frontend/src/types/farm.ts

import type { FarmRole } from './auth'

export interface Farm {
  id: number
  name: string
  status: 'active' | 'pending_setup' | 'suspended'
  role: FarmRole | null
  is_primary: boolean
}
```

- [ ] **Step 3: Create `frontend/src/lib/roles.ts`**

```typescript
// frontend/src/lib/roles.ts

import { useFarmStore } from '@/stores/farm.store'
import type { FarmRole } from '@/types/auth'

// ── Re-export for convenience ─────────────────────────────────────────────────
export type { FarmRole }

// ── Nav hrefs visible to each role ────────────────────────────────────────────
// Keys must match the `href` values in ALL_NAV_ITEMS in Sidebar.tsx
export const NAV_HREFS_BY_ROLE: Record<FarmRole, readonly string[]> = {
  super_admin: ['/dashboard', '/flocks', '/inventory', '/sales', '/expenses', '/partners', '/reports'],
  farm_admin:  ['/dashboard', '/flocks', '/inventory', '/sales', '/expenses', '/partners', '/reports'],
  worker:      ['/flocks', '/inventory'],
  partner:     ['/flocks', '/reports'],
}

// ── Route access guard ────────────────────────────────────────────────────────

/**
 * Returns true if the given role may navigate to the given pathname.
 * super_admin and farm_admin can access any route.
 * worker: flocks list + flock detail + inventory.
 * partner: flocks list + flock detail + reports (read-only).
 * /unauthorized is always accessible to show the denial page.
 */
export function canAccessRoute(role: FarmRole | null, pathname: string): boolean {
  if (!role) return true // Store not yet hydrated — defer to server enforcement

  if (role === 'super_admin' || role === 'farm_admin') return true

  // Always allow the denial page itself
  if (pathname === '/unauthorized') return true

  if (role === 'worker') {
    if (pathname === '/flocks') return true
    if (pathname.startsWith('/flocks/') && pathname !== '/flocks/new') return true
    if (pathname === '/inventory' || pathname.startsWith('/inventory/')) return true
    return false
  }

  if (role === 'partner') {
    if (pathname === '/flocks') return true
    if (pathname.startsWith('/flocks/') && pathname !== '/flocks/new') return true
    if (pathname === '/reports' || pathname.startsWith('/reports/')) return true
    return false
  }

  return false
}

// ── Post-login default landing route ─────────────────────────────────────────

/**
 * All roles land on /flocks — the only fully functional module in V1.
 * Adjust once more modules ship.
 */
export function getDefaultRoute(_role: FarmRole | null): string {
  return '/flocks'
}

// ── Hooks ─────────────────────────────────────────────────────────────────────

/** Returns the role for the currently selected farm, or null if not loaded. */
export function useCurrentRole(): FarmRole | null {
  const role = useFarmStore((s) => s.currentFarm?.role)
  return (role as FarmRole | null) ?? null
}

/**
 * Returns true when the current user can only view data (partner role).
 * Use this hook in page components to hide create/edit/delete buttons.
 */
export function useIsReadOnly(): boolean {
  return useCurrentRole() === 'partner'
}
```

- [ ] **Step 4: Run TypeScript check**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
npx tsc --noEmit 2>&1
echo "EXIT:$?"
```

Expected: `EXIT:0`

If there are errors, check that `@/types/auth` path alias resolves correctly and that all imports match the new type names.

- [ ] **Step 5: Commit**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
git add src/types/auth.ts src/types/farm.ts src/lib/roles.ts
git commit -m "feat(roles): fix role types and add role utilities"
```

---

## Task 2: Role-based Sidebar

**Files:**
- Modify: `frontend/src/components/layout/Sidebar.tsx`

- [ ] **Step 1: Read the current Sidebar.tsx**

Read `frontend/src/components/layout/Sidebar.tsx` to see the exact current content before editing.

- [ ] **Step 2: Replace Sidebar.tsx with role-filtered version**

```tsx
// frontend/src/components/layout/Sidebar.tsx
'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import { Lock, LayoutDashboard, Bird, Package, ShoppingCart, Receipt, Users, BarChart3 } from 'lucide-react'
import { useFarmStore } from '@/stores/farm.store'
import { NAV_HREFS_BY_ROLE } from '@/lib/roles'
import type { FarmRole } from '@/types/auth'

// ── All possible nav items ────────────────────────────────────────────────────
const ALL_NAV_ITEMS = [
  { label: 'لوحة التحكم', href: '/dashboard',  icon: LayoutDashboard },
  { label: 'الأفواج',     href: '/flocks',      icon: Bird },
  { label: 'المخزون',     href: '/inventory',   icon: Package },
  { label: 'المبيعات',    href: '/sales',       icon: ShoppingCart },
  { label: 'المصروفات',   href: '/expenses',    icon: Receipt },
  { label: 'الشركاء',     href: '/partners',    icon: Users },
  { label: 'التقارير',    href: '/reports',     icon: BarChart3 },
]

export function Sidebar() {
  const pathname    = usePathname()
  const currentFarm = useFarmStore((s) => s.currentFarm)
  const role        = (currentFarm?.role ?? null) as FarmRole | null

  // Filter nav items by role; show all if role not yet loaded
  const allowedHrefs = role ? NAV_HREFS_BY_ROLE[role] : ALL_NAV_ITEMS.map((i) => i.href)
  const navItems     = ALL_NAV_ITEMS.filter(({ href }) => allowedHrefs.includes(href))
  const isPartner    = role === 'partner'

  return (
    <aside className="flex w-60 flex-col border-l border-slate-200 bg-white">

      {/* Brand */}
      <div className="flex h-16 items-center gap-3 border-b border-slate-200 px-5">
        <span className="text-2xl">🐔</span>
        <span className="text-lg font-bold text-slate-900">دجاجاتي</span>
      </div>

      {/* Read-only badge — partner role only */}
      {isPartner && (
        <div className="mx-3 mt-3 flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
          <Lock className="h-3.5 w-3.5 shrink-0" />
          <span>وضع القراءة فقط</span>
        </div>
      )}

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-1">
          {navItems.map(({ label, href, icon: Icon }) => {
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
                  <Icon
                    className={cn(
                      'h-5 w-5',
                      isActive ? 'text-primary-600' : 'text-slate-400'
                    )}
                  />
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

- [ ] **Step 3: Run TypeScript check**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
npx tsc --noEmit 2>&1
echo "EXIT:$?"
```

Expected: `EXIT:0`

- [ ] **Step 4: Commit**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
git add src/components/layout/Sidebar.tsx
git commit -m "feat(roles): filter sidebar nav by role, partner read-only badge"
```

---

## Task 3: RoleGuard + unauthorized page + layout + flock list guard

**Files:**
- Create: `frontend/src/components/layout/RoleGuard.tsx`
- Create: `frontend/src/app/(farm)/unauthorized/page.tsx`
- Modify: `frontend/src/app/(farm)/layout.tsx`
- Modify: `frontend/src/app/(farm)/flocks/page.tsx`

- [ ] **Step 1: Create `frontend/src/components/layout/RoleGuard.tsx`**

```tsx
// frontend/src/components/layout/RoleGuard.tsx
'use client'

import { useEffect, useState } from 'react'
import { usePathname, useRouter } from 'next/navigation'
import { useFarmStore } from '@/stores/farm.store'
import { canAccessRoute } from '@/lib/roles'
import type { FarmRole } from '@/types/auth'

/**
 * Client-side route guard that reads the current farm's role from Zustand and
 * redirects to /unauthorized if the role cannot access the current pathname.
 *
 * Renders children immediately on the first paint (before store hydration)
 * to avoid a flash of the unauthorized page on hard refresh.
 * Once the store is hydrated and the role is known, access is re-evaluated.
 */
export function RoleGuard({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const router   = useRouter()
  const [mounted, setMounted] = useState(false)

  const role = useFarmStore((s) => (s.currentFarm?.role ?? null) as FarmRole | null)

  // Wait for client mount so the Zustand store is hydrated from localStorage
  useEffect(() => {
    setMounted(true)
  }, [])

  useEffect(() => {
    if (!mounted || role === null) return
    if (!canAccessRoute(role, pathname)) {
      router.replace('/unauthorized')
    }
  }, [mounted, role, pathname, router])

  // Before mount: render children without restriction (store not yet hydrated)
  if (!mounted || role === null) return <>{children}</>

  // After mount: suppress content while redirect is in progress
  if (!canAccessRoute(role, pathname)) return null

  return <>{children}</>
}
```

- [ ] **Step 2: Create `frontend/src/app/(farm)/unauthorized/page.tsx`**

```tsx
// frontend/src/app/(farm)/unauthorized/page.tsx
import Link from 'next/link'

export default function UnauthorizedPage() {
  return (
    <div className="flex flex-col items-center justify-center py-24 text-center">
      <div className="mb-4 text-6xl">🚫</div>
      <h1 className="mb-2 text-2xl font-bold text-slate-900">غير مصرح لك بالوصول</h1>
      <p className="mb-8 max-w-sm text-sm text-slate-500">
        ليس لديك صلاحية لعرض هذه الصفحة. إذا كنت تعتقد أن هذا خطأ،
        يرجى التواصل مع مدير المزرعة.
      </p>
      <Link
        href="/flocks"
        className="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
      >
        العودة للأفواج
      </Link>
    </div>
  )
}
```

- [ ] **Step 3: Update `frontend/src/app/(farm)/layout.tsx`**

Read the current file first, then replace with:

```tsx
// frontend/src/app/(farm)/layout.tsx
import { Sidebar } from '@/components/layout/Sidebar'
import { Header } from '@/components/layout/Header'
import { RoleGuard } from '@/components/layout/RoleGuard'

export default function FarmLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex h-screen overflow-hidden bg-slate-50">
      {/* Sidebar on the right in RTL */}
      <Sidebar />

      {/* Main area */}
      <div className="flex flex-1 flex-col overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto p-6">
          <RoleGuard>{children}</RoleGuard>
        </main>
      </div>
    </div>
  )
}
```

- [ ] **Step 4: Update `frontend/src/app/(farm)/flocks/page.tsx` — hide write actions for partner/worker**

Read the current file first. Add the `useCurrentRole` import and hide the "فوج جديد" button when role is `partner` or `worker`.

Find the page header section that contains the "فوج جديد" button:

```tsx
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
```

And the empty state "فوج جديد" button:

```tsx
          <Button asChild className="mt-5">
            <Link href="/flocks/new">
              <Plus className="h-4 w-4" />
              إنشاء فوج جديد
            </Link>
          </Button>
```

Add `useCurrentRole` to the imports at the top of the file (with the other store imports), and add `canAdd` logic. The full updated flocks page:

```tsx
// frontend/src/app/(farm)/flocks/page.tsx
'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { Bird, Plus, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { SkeletonCard } from '@/components/ui/Skeleton'
import { FlockCard } from '@/components/flocks/FlockCard'
import { FlockStatusBadge } from '@/components/flocks/FlockStatusBadge'
import { flocksApi } from '@/lib/api/flocks'
import { useFarmStore } from '@/stores/farm.store'
import { useCurrentRole } from '@/lib/roles'
import { formatDate, formatNumber } from '@/lib/utils'
import type { Flock } from '@/types/flock'

export default function FlocksPage() {
  const { currentFarm } = useFarmStore()
  const role = useCurrentRole()
  const [flocks, setFlocks] = useState<Flock[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Only farm_admin and super_admin can create new flocks
  const canCreateFlock = role === 'farm_admin' || role === 'super_admin'

  useEffect(() => {
    if (!currentFarm) return
    setLoading(true)
    setError(null)
    flocksApi
      .list()
      .then((res) => setFlocks(res.data))
      .catch((err: { response?: { status?: number } }) => {
        if (err?.response?.status === 404) {
          setFlocks([])
        } else {
          setError('تعذّر تحميل قائمة الأفواج. تأكد من تشغيل الخادم.')
        }
      })
      .finally(() => setLoading(false))
  }, [currentFarm])

  const activeFlocks = flocks.filter((f) => f.status === 'active' || f.status === 'draft')
  const closedFlocks = flocks.filter((f) => f.status === 'closed' || f.status === 'cancelled')

  return (
    <div className="space-y-8">
      {/* Page header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">الأفواج</h1>
          {currentFarm && (
            <p className="mt-0.5 text-sm text-slate-500">{currentFarm.name}</p>
          )}
        </div>
        {canCreateFlock && (
          <Button asChild>
            <Link href="/flocks/new">
              <Plus className="h-4 w-4" />
              فوج جديد
            </Link>
          </Button>
        )}
      </div>

      {/* Loading skeletons */}
      {loading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <SkeletonCard key={i} />
          ))}
        </div>
      )}

      {/* Error */}
      {!loading && error && (
        <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-5 text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {/* Empty */}
      {!loading && !error && flocks.length === 0 && (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 text-center">
          <Bird className="mb-4 h-12 w-12 text-slate-300" />
          <h3 className="text-lg font-semibold text-slate-700">لا توجد أفواج بعد</h3>
          <p className="mt-1 text-sm text-slate-500">
            {canCreateFlock
              ? 'ابدأ بإنشاء فوجك الأول لهذه المزرعة'
              : 'لا توجد أفواج لهذه المزرعة حتى الآن'}
          </p>
          {canCreateFlock && (
            <Button asChild className="mt-5">
              <Link href="/flocks/new">
                <Plus className="h-4 w-4" />
                إنشاء فوج جديد
              </Link>
            </Button>
          )}
        </div>
      )}

      {!loading && !error && flocks.length > 0 && (
        <>
          {/* ── Active / Draft flocks ─────────────────────────────── */}
          {activeFlocks.length > 0 && (
            <section>
              <h2 className="mb-4 flex items-center gap-2 text-base font-semibold text-slate-700">
                <span className="h-2.5 w-2.5 rounded-full bg-green-500" />
                الأفواج النشطة
                <span className="ms-1 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">
                  {activeFlocks.length}
                </span>
              </h2>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {activeFlocks.map((flock) => (
                  <FlockCard key={flock.id} flock={flock} />
                ))}
              </div>
            </section>
          )}

          {/* ── Closed / Cancelled flocks table ──────────────────── */}
          {closedFlocks.length > 0 && (
            <section>
              <h2 className="mb-4 flex items-center gap-2 text-base font-semibold text-slate-700">
                <span className="h-2.5 w-2.5 rounded-full bg-slate-400" />
                الأفواج المغلقة
                <span className="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                  {closedFlocks.length}
                </span>
              </h2>
              <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-medium text-slate-500">
                      <th className="px-5 py-3">الفوج</th>
                      <th className="px-5 py-3">تاريخ البدء</th>
                      <th className="px-5 py-3">تاريخ الإغلاق</th>
                      <th className="px-5 py-3">العدد الأولي</th>
                      <th className="px-5 py-3">الحالة</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {closedFlocks.map((flock) => (
                      <tr key={flock.id} className="transition hover:bg-slate-50">
                        <td className="px-5 py-3">
                          <Link
                            href={`/flocks/${flock.id}`}
                            className="font-medium text-slate-800 hover:text-primary-600"
                          >
                            {flock.name}
                          </Link>
                        </td>
                        <td className="px-5 py-3 text-slate-500">
                          {formatDate(flock.start_date)}
                        </td>
                        <td className="px-5 py-3 text-slate-500">
                          {flock.end_date ? formatDate(flock.end_date) : '—'}
                        </td>
                        <td className="px-5 py-3 text-slate-500">
                          {formatNumber(flock.initial_count)}
                        </td>
                        <td className="px-5 py-3">
                          <FlockStatusBadge status={flock.status} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </>
      )}
    </div>
  )
}
```

- [ ] **Step 5: Run TypeScript check**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
npx tsc --noEmit 2>&1
echo "EXIT:$?"
```

Expected: `EXIT:0`

- [ ] **Step 6: Commit**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
git add src/components/layout/RoleGuard.tsx
git add "src/app/(farm)/unauthorized/page.tsx"
git add "src/app/(farm)/layout.tsx"
git add "src/app/(farm)/flocks/page.tsx"
git commit -m "feat(roles): RoleGuard, unauthorized page, flock list write guard"
```

---

## Task 4: Login redirect — role-aware post-login destination

**Files:**
- Modify: `frontend/src/app/(auth)/login/page.tsx`

- [ ] **Step 1: Read the current login page**

Read `frontend/src/app/(auth)/login/page.tsx` to see the exact current content.

- [ ] **Step 2: Update login/page.tsx with role-aware redirect**

The change is in `onSubmit`: after login, check if the `from` param is accessible for the user's role; if not, use `getDefaultRoute(role)`.

```tsx
// frontend/src/app/(auth)/login/page.tsx
'use client'

import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useRouter, useSearchParams } from 'next/navigation'
import { useAuthStore } from '@/stores/auth.store'
import { useFarmStore } from '@/stores/farm.store'
import { canAccessRoute, getDefaultRoute } from '@/lib/roles'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import type { Farm } from '@/types/farm'
import type { FarmRole } from '@/types/auth'

// ── Zod schema ────────────────────────────────────────────────────────────────
const loginSchema = z.object({
  login:    z.string().min(1, 'البريد الإلكتروني أو رقم الواتساب مطلوب'),
  password: z.string().min(1, 'كلمة المرور مطلوبة'),
})
type LoginForm = z.infer<typeof loginSchema>

// ── Component ─────────────────────────────────────────────────────────────────
export default function LoginPage() {
  const router       = useRouter()
  const searchParams = useSearchParams()
  const { login }    = useAuthStore()
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

      // Hydrate farm store from user returned by auth store
      const user = useAuthStore.getState().user
      if (user?.farms?.length) {
        const farms: Farm[] = user.farms.map((f) => ({
          id:         f.id,
          name:       f.name,
          status:     f.status,
          role:       f.role,
          is_primary: f.is_primary,
        }))
        setFarms(farms)

        // Auto-select primary farm, or first one
        const primaryFarm = farms.find((f) => f.is_primary) ?? farms[0]
        setCurrentFarm(primaryFarm)

        // Determine redirect destination based on role
        const role = (primaryFarm?.role ?? null) as FarmRole | null
        const from = searchParams.get('from')

        // Use 'from' if the role can access it; otherwise fall back to default
        const destination =
          from && canAccessRoute(role, from) ? from : getDefaultRoute(role)
        router.replace(destination)
      } else {
        // No farms — go to default
        router.replace(getDefaultRoute(null))
      }
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

- [ ] **Step 3: Run TypeScript check**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
npx tsc --noEmit 2>&1
echo "EXIT:$?"
```

Expected: `EXIT:0`

- [ ] **Step 4: Commit**

```bash
cd c:\Users\moham\Desktop\alamin\frontend
git add "src/app/(auth)/login/page.tsx"
git commit -m "feat(roles): role-aware post-login redirect"
```

---

## Self-Review

### 1. Spec coverage

| Requirement | Task |
|-------------|------|
| تحديد الواجهة المناسبة لكل دور (correct interface per role) | Task 4 — role-based redirect after login |
| منع ظهور لوحة أدمن الكاملة للجميع (block full admin panel) | Task 2 — Sidebar filtered per role |
| جعل `partner` في وضع Read-Only | Tasks 2+3 — badge in sidebar, `canCreateFlock` in flocks page, `useIsReadOnly()` hook ready for other components |
| جعل `worker` يرى فقط الصفحات التشغيلية | Tasks 2+3 — Sidebar shows only flocks+inventory, RoleGuard blocks other routes |
| إبقاء `farm_admin` على اللوحة الكاملة | `canAccessRoute` returns `true` for `farm_admin` on all routes |
| فصل واضح في layout و sidebar و route protection | Task 3: RoleGuard in layout; Task 2: Sidebar filtered; proxy.ts: auth check |
| لا تكسر auth store و farm store و proxy.ts | Only types changed; stores and proxy untouched |
| العربية فقط + RTL | All UI text in Arabic; unauthorized page in Arabic |
| TypeScript check | Run after every task |

### 2. Placeholder scan

No TBD, TODO, or vague steps — all steps have complete code.

### 3. Type consistency

- `FarmRole` defined once in `src/types/auth.ts`, re-exported from `src/lib/roles.ts`
- `Farm.role: FarmRole | null` — consistent between `types/farm.ts`, `types/auth.ts` (UserFarm.role), and usage in Sidebar/RoleGuard/login page
- `canAccessRoute(role, pathname)` — same signature used in `RoleGuard`, `login/page.tsx`, referenced in `proxy.ts` context (proxy only checks cookie, not role)
- `NAV_HREFS_BY_ROLE` keys match `href` values in `ALL_NAV_ITEMS` — verified: `/dashboard`, `/flocks`, `/inventory`, `/sales`, `/expenses`, `/partners`, `/reports`

### 4. What is NOT in scope of this plan (intentional)

- `partner` read-only on the flock **detail page** (flocks/[id]) — the `useIsReadOnly()` hook is ready; the flock detail page currently has no edit buttons to hide (only the "تعديل الفوج" button in the update form is relevant — that page is in scope of a future plan)
- `partner` read-only in `MortalitiesTab` — the tab already blocks adding when `flockStatus !== 'active'`; the full read-only guard via `useIsReadOnly()` is out of scope here
- Super admin cross-farm view — super_admin is treated as having access to all routes; no special super-admin dashboard is built in this plan
- Server-side role enforcement in `proxy.ts` — requires storing role in cookie; intentionally deferred since backend enforces at API level
