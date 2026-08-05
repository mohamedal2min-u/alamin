# Mobile Design Pass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix concrete phone-layout bugs across the farm app's mobile pages — missing edge padding, wide tables that clip or force horizontal scroll on phones, and undersized touch targets — following the pattern already validated on the inventory page and already correct in `my-wallet/page.tsx`.

**Architecture:** Each affected page gets a `<div className="... sm:hidden">` (or `md:hidden`) compact card list rendered alongside the existing `<div className="hidden sm:block">` table, both mapping the same array with no shared abstraction (matches the existing `my-wallet/page.tsx` pattern — no new components). The layout-level padding bug is fixed once at `(farm)/layout.tsx`.

**Tech Stack:** Next.js 16 (App Router) + React 19 + TypeScript + Tailwind CSS v4. No component test framework exists for these pages.

## Global Constraints

- No new shared components/abstractions — follow the existing per-page inline-JSX convention (confirmed in `my-wallet/page.tsx`).
- No visual redesign beyond phone-width correctness — same colors, spacing scale (`rounded-2xl`, `--shadow-card`), and copy already used on each page.
- `admin/*` pages are out of scope (desktop-sidebar layout branch, not phone-facing).
- Verification per task is `npx tsc --noEmit` from `frontend/` — there is no test suite covering these page components. No live/browser verification is planned (documented limitation, same as the inventory work).
- Spec: `docs/superpowers/specs/2026-08-05-mobile-design-pass-design.md`.

---

### Task 1: Shared layout padding fix

**Files:**
- Modify: `frontend/src/app/(farm)/layout.tsx:83`
- Modify: `frontend/src/app/(farm)/inventory/page.tsx` (root wrapper div, currently reads `className="space-y-5 px-3 sm:px-4"`)

**Interfaces:** None — standalone className edits, no exported symbols change.

- [ ] **Step 1: Add horizontal padding at the shared layout level**

In `frontend/src/app/(farm)/layout.tsx`, change:

```tsx
      <div className="mx-auto max-w-2xl">
```

to:

```tsx
      <div className="mx-auto max-w-2xl px-3 sm:px-4">
```

- [ ] **Step 2: Remove the now-redundant page-local padding from inventory**

In `frontend/src/app/(farm)/inventory/page.tsx`, find the `InventoryPage` component's root return and change:

```tsx
  return (
    <div className="space-y-5 px-3 sm:px-4">
```

back to:

```tsx
  return (
    <div className="space-y-5">
```

(This padding was added directly to the inventory page in a prior change, before the shared layout fix existed. Leaving both would double the padding.)

- [ ] **Step 3: Verify**

Run: `cd frontend && npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/app/\(farm\)/layout.tsx frontend/src/app/\(farm\)/inventory/page.tsx
git commit -m "fix: add horizontal padding at shared mobile layout level

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Sales page — mobile card fallback for the sales table

**Files:**
- Modify: `frontend/src/app/(farm)/sales/page.tsx:114-165`

**Interfaces:** None — uses existing imports already present in the file (`formatDate`, `formatNumber`, `formatCurrency` from `@/lib/utils`; `PaymentStatusBadge` from `@/components/sales/PaymentStatusBadge`; `Sale` type from `@/types/sale`). No new imports needed.

- [ ] **Step 1: Replace the table block with a card+table split**

Replace this exact block (lines 113–165):

```tsx
      {/* Sales table */}
      {!loading && !error && sales.length > 0 && (
        <div className="overflow-x-auto rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                <th className="px-5 py-3">التاريخ</th>
                <th className="px-5 py-3">المشتري</th>
                <th className="px-5 py-3">الطيور</th>
                <th className="px-5 py-3">الوزن (كغ)</th>
                <th className="px-5 py-3">الصافي</th>
                <th className="px-5 py-3">المستلم</th>
                <th className="px-5 py-3">المتبقي</th>
                <th className="px-5 py-3">الحالة</th>
                <th className="px-5 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {sales.map((sale) => {
                const totalBirds  = sale.items.reduce((s, i) => s + i.birds_count, 0)
                const totalWeight = sale.items.reduce((s, i) => s + Number(i.total_weight_kg), 0)

                return (
                  <tr key={sale.id} className="transition-colors hover:bg-slate-50">
                    <td className="px-5 py-3 text-slate-600">{formatDate(sale.sale_date)}</td>
                    <td className="px-5 py-3 font-medium text-slate-800">{sale.buyer_name ?? '—'}</td>
                    <td className="px-5 py-3 tabular-nums text-slate-700">{formatNumber(totalBirds)}</td>
                    <td className="px-5 py-3 tabular-nums text-slate-700">{formatNumber(Number(totalWeight.toFixed(1)))}</td>
                    <td className="px-5 py-3 tabular-nums font-semibold text-slate-900">{formatCurrency(Number(sale.net_amount))}</td>
                    <td className="px-5 py-3 tabular-nums text-primary-700 font-medium">{formatCurrency(Number(sale.received_amount))}</td>
                    <td className={`px-5 py-3 tabular-nums font-medium ${sale.remaining_amount > 0 ? 'text-red-600' : 'text-slate-400'}`}>
                      {formatCurrency(Number(sale.remaining_amount))}
                    </td>
                    <td className="px-5 py-3">
                      <PaymentStatusBadge status={sale.payment_status} />
                    </td>
                    <td className="px-5 py-3 text-start">
                      {sale.payment_status !== 'paid' && (
                        <button
                          onClick={() => setPaymentSale(sale)}
                          className="text-xs font-medium text-primary-600 hover:underline"
                        >
                          تحديث الدفع
                        </button>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
```

with:

```tsx
      {/* Sales: mobile cards + desktop table */}
      {!loading && !error && sales.length > 0 && (
        <>
          {/* Mobile cards */}
          <div className="space-y-2 sm:hidden">
            {sales.map((sale) => {
              const totalBirds  = sale.items.reduce((s, i) => s + i.birds_count, 0)
              const totalWeight = sale.items.reduce((s, i) => s + Number(i.total_weight_kg), 0)
              return (
                <div key={sale.id} className="rounded-2xl border border-slate-200/60 bg-white p-3.5" style={{ boxShadow: 'var(--shadow-card)' }}>
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-bold text-slate-800">{sale.buyer_name ?? '—'}</p>
                      <p className="mt-0.5 text-[10px] text-slate-400">
                        {formatDate(sale.sale_date)} · {formatNumber(totalBirds)} طائر · {formatNumber(Number(totalWeight.toFixed(1)))} كغ
                      </p>
                    </div>
                    <PaymentStatusBadge status={sale.payment_status} />
                  </div>

                  <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div>
                      <p className="text-[9px] font-extrabold text-slate-400">الصافي</p>
                      <p className="text-[13px] font-black tabular-nums text-slate-900">{formatCurrency(Number(sale.net_amount))}</p>
                    </div>
                    <div>
                      <p className="text-[9px] font-extrabold text-slate-400">المستلم</p>
                      <p className="text-[13px] font-black tabular-nums text-primary-700">{formatCurrency(Number(sale.received_amount))}</p>
                    </div>
                    <div>
                      <p className="text-[9px] font-extrabold text-slate-400">المتبقي</p>
                      <p className={`text-[13px] font-black tabular-nums ${sale.remaining_amount > 0 ? 'text-red-600' : 'text-slate-400'}`}>
                        {formatCurrency(Number(sale.remaining_amount))}
                      </p>
                    </div>
                  </div>

                  {sale.payment_status !== 'paid' && (
                    <button
                      onClick={() => setPaymentSale(sale)}
                      className="mt-3 w-full rounded-xl bg-primary-50 py-2 text-xs font-bold text-primary-600 transition-colors hover:bg-primary-100"
                    >
                      تحديث الدفع
                    </button>
                  )}
                </div>
              )
            })}
          </div>

          {/* Desktop table */}
          <div className="hidden overflow-x-auto rounded-2xl border border-slate-200/60 bg-white sm:block" style={{ boxShadow: 'var(--shadow-card)' }}>
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                  <th className="px-5 py-3">التاريخ</th>
                  <th className="px-5 py-3">المشتري</th>
                  <th className="px-5 py-3">الطيور</th>
                  <th className="px-5 py-3">الوزن (كغ)</th>
                  <th className="px-5 py-3">الصافي</th>
                  <th className="px-5 py-3">المستلم</th>
                  <th className="px-5 py-3">المتبقي</th>
                  <th className="px-5 py-3">الحالة</th>
                  <th className="px-5 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {sales.map((sale) => {
                  const totalBirds  = sale.items.reduce((s, i) => s + i.birds_count, 0)
                  const totalWeight = sale.items.reduce((s, i) => s + Number(i.total_weight_kg), 0)

                  return (
                    <tr key={sale.id} className="transition-colors hover:bg-slate-50">
                      <td className="px-5 py-3 text-slate-600">{formatDate(sale.sale_date)}</td>
                      <td className="px-5 py-3 font-medium text-slate-800">{sale.buyer_name ?? '—'}</td>
                      <td className="px-5 py-3 tabular-nums text-slate-700">{formatNumber(totalBirds)}</td>
                      <td className="px-5 py-3 tabular-nums text-slate-700">{formatNumber(Number(totalWeight.toFixed(1)))}</td>
                      <td className="px-5 py-3 tabular-nums font-semibold text-slate-900">{formatCurrency(Number(sale.net_amount))}</td>
                      <td className="px-5 py-3 tabular-nums text-primary-700 font-medium">{formatCurrency(Number(sale.received_amount))}</td>
                      <td className={`px-5 py-3 tabular-nums font-medium ${sale.remaining_amount > 0 ? 'text-red-600' : 'text-slate-400'}`}>
                        {formatCurrency(Number(sale.remaining_amount))}
                      </td>
                      <td className="px-5 py-3">
                        <PaymentStatusBadge status={sale.payment_status} />
                      </td>
                      <td className="px-5 py-3 text-start">
                        {sale.payment_status !== 'paid' && (
                          <button
                            onClick={() => setPaymentSale(sale)}
                            className="text-xs font-medium text-primary-600 hover:underline"
                          >
                            تحديث الدفع
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </>
      )}
```

- [ ] **Step 2: Verify**

Run: `cd frontend && npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add "frontend/src/app/(farm)/sales/page.tsx"
git commit -m "fix: add mobile card view for sales table

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Partners page — mobile card fallback for the partners table

**Files:**
- Modify: `frontend/src/app/(farm)/partners/page.tsx:151-283`

**Interfaces:** None — uses existing imports already present (`Users`, `Phone`, `Mail`, `Wallet`, `Edit2` from `lucide-react`; `Card` from `@/components/ui/Card`; `Partner` type from `@/lib/api/partners`). No new imports needed. `filteredPartners` (from the existing `useMemo` at line 79) always returns an array (never `undefined`), so the optional-chaining (`filteredPartners?.map`) in the replacement below is dropped in favor of plain `.map` where the block is already gated by the empty-check branch.

- [ ] **Step 1: Replace the `<Card>` block with an empty-state branch + card/table split**

Replace this exact block (lines 151–283):

```tsx
      <Card className="border-slate-200/60 overflow-hidden bg-white rounded-2xl">
        {isLoading ? (
          <div className="divide-y divide-slate-50">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="flex items-center gap-4 px-5 py-4 animate-pulse">
                <div className="w-9 h-9 rounded-xl bg-slate-100 shrink-0" />
                <div className="flex-1 space-y-1.5">
                  <div className="h-3 w-28 bg-slate-100 rounded" />
                  <div className="h-2.5 w-20 bg-slate-50 rounded" />
                </div>
                <div className="h-7 w-14 bg-slate-100 rounded-lg" />
                <div className="h-7 w-16 bg-slate-100 rounded-lg" />
              </div>
            ))}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-right">
              <thead>
                <tr className="border-b border-slate-100 no-print">
                  <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest">الشريك</th>
                  <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest hidden sm:table-cell">التواصل</th>
                  <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest text-center">الحصة</th>
                  <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest text-center">الحالة</th>
                  <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest text-left">إجراءات</th>
                </tr>
                {/* Print header */}
                <tr className="hidden print:table-row bg-slate-100 border-b border-slate-300">
                  <th className="px-4 py-2 text-right text-xs font-bold">الاسم</th>
                  <th className="px-4 py-2 text-right text-xs font-bold">الواتساب</th>
                  <th className="px-4 py-2 text-center text-xs font-bold">الحصة</th>
                  <th className="px-4 py-2 text-center text-xs font-bold">الحالة</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50">
                {filteredPartners?.map((partner) => (
                  <tr key={partner.id} className="group hover:bg-slate-50/80 transition-colors duration-200">
                    {/* Partner info */}
                    <td className="px-5 py-3.5">
                      <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs group-hover:bg-sky-50 group-hover:text-sky-600 transition-colors duration-200 shrink-0">
                          {partner.name.substring(0, 2)}
                        </div>
                        <div className="min-w-0">
                          <p className="text-sm font-bold text-slate-900 truncate">{partner.name}</p>
                          {/* Show whatsapp on mobile since 2nd column hidden */}
                          {partner.whatsapp && (
                            <p className="text-xs text-slate-400 font-medium mt-0.5 sm:hidden flex items-center gap-1">
                              <Phone className="w-3 h-3" />
                              {partner.whatsapp}
                            </p>
                          )}
                        </div>
                      </div>
                    </td>
                    {/* Contact */}
                    <td className="px-5 py-3.5 hidden sm:table-cell">
                      <div className="space-y-1">
                        {partner.whatsapp && (
                          <div className="flex items-center gap-2 text-sm text-slate-600">
                            <Phone className="w-3.5 h-3.5 text-primary-500" />
                            <span className="font-medium">{partner.whatsapp}</span>
                          </div>
                        )}
                        {partner.email && (
                          <div className="flex items-center gap-2 text-xs text-slate-400">
                            <Mail className="w-3.5 h-3.5" />
                            <span className="font-medium truncate max-w-[160px]">{partner.email}</span>
                          </div>
                        )}
                      </div>
                    </td>
                    {/* Share */}
                    <td className="px-5 py-3.5 text-center">
                      {partner.shares && partner.shares.length > 0 ? (
                        <span className="inline-flex items-center px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 font-bold text-sm border border-sky-100">
                          {partner.shares[0].share_percent}%
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-50 text-slate-400 font-semibold text-sm">
                          0%
                        </span>
                      )}
                    </td>
                    {/* Status */}
                    <td className="px-5 py-3.5 text-center">
                      <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold ${
                        partner.status === 'active' 
                          ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' 
                          : 'bg-slate-50 text-slate-400 border border-slate-100'
                      }`}>
                        <span className={`w-1.5 h-1.5 rounded-full ${partner.status === 'active' ? 'bg-emerald-500' : 'bg-slate-300'}`} />
                        {partner.status === 'active' ? 'نشط' : 'غير نشط'}
                      </span>
                    </td>
                    {/* Actions */}
                    <td className="px-5 py-3.5 no-print">
                      <div className="flex items-center justify-end gap-1.5">
                        <button 
                          onClick={() => handleOpenLedger(partner)}
                          className="h-8 px-2.5 flex items-center gap-1.5 bg-primary-50 border border-primary-100 rounded-lg text-primary-600 hover:bg-primary-100 text-xs font-bold transition-colors duration-200"
                          title="المحفظة المالية"
                        >
                          <Wallet className="w-3.5 h-3.5" />
                          <span className="hidden sm:inline">المحفظة</span>
                        </button>
                        <button 
                          onClick={() => handleEdit(partner)}
                          className="h-8 w-8 flex items-center justify-center bg-slate-50 border border-slate-100 rounded-lg text-slate-400 hover:text-sky-600 hover:bg-sky-50 hover:border-sky-100 transition-colors duration-200"
                          title="تعديل"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {filteredPartners?.length === 0 && (
                  <tr>
                    <td colSpan={5} className="py-20 text-center">
                      <div className="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <Users className="w-7 h-7 text-slate-200" />
                      </div>
                      <p className="text-sm font-bold text-slate-400">لا يوجد شركاء</p>
                      <p className="text-xs font-medium text-slate-300 mt-1">أضف شريكاً جديداً للبدء</p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </Card>
```

with:

```tsx
      <Card className="border-slate-200/60 overflow-hidden bg-white rounded-2xl">
        {isLoading ? (
          <div className="divide-y divide-slate-50">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="flex items-center gap-4 px-5 py-4 animate-pulse">
                <div className="w-9 h-9 rounded-xl bg-slate-100 shrink-0" />
                <div className="flex-1 space-y-1.5">
                  <div className="h-3 w-28 bg-slate-100 rounded" />
                  <div className="h-2.5 w-20 bg-slate-50 rounded" />
                </div>
                <div className="h-7 w-14 bg-slate-100 rounded-lg" />
                <div className="h-7 w-16 bg-slate-100 rounded-lg" />
              </div>
            ))}
          </div>
        ) : filteredPartners.length === 0 ? (
          <div className="py-20 text-center">
            <div className="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <Users className="w-7 h-7 text-slate-200" />
            </div>
            <p className="text-sm font-bold text-slate-400">لا يوجد شركاء</p>
            <p className="text-xs font-medium text-slate-300 mt-1">أضف شريكاً جديداً للبدء</p>
          </div>
        ) : (
          <>
            {/* Mobile cards */}
            <div className="divide-y divide-slate-50 sm:hidden">
              {filteredPartners.map((partner) => (
                <div key={partner.id} className="flex items-center gap-3 px-4 py-3.5">
                  <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-500">
                    {partner.name.substring(0, 2)}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold text-slate-900">{partner.name}</p>
                    {partner.whatsapp && (
                      <p className="mt-0.5 flex items-center gap-1 text-xs font-medium text-slate-400">
                        <Phone className="h-3 w-3" />{partner.whatsapp}
                      </p>
                    )}
                  </div>
                  <div className="flex shrink-0 flex-col items-end gap-1">
                    <span className={`inline-flex items-center rounded-lg px-2 py-1 text-[10px] font-bold ${
                      partner.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-400'
                    }`}>
                      {partner.status === 'active' ? 'نشط' : 'غير نشط'}
                    </span>
                    <span className="text-[11px] font-bold text-sky-700">
                      {partner.shares && partner.shares.length > 0 ? partner.shares[0].share_percent : 0}%
                    </span>
                  </div>
                  <div className="flex shrink-0 items-center gap-1.5">
                    <button
                      onClick={() => handleOpenLedger(partner)}
                      className="flex h-9 w-9 items-center justify-center rounded-lg border border-primary-100 bg-primary-50 text-primary-600"
                      title="المحفظة المالية"
                    >
                      <Wallet className="h-4 w-4" />
                    </button>
                    <button
                      onClick={() => handleEdit(partner)}
                      className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-100 bg-slate-50 text-slate-400"
                      title="تعديل"
                    >
                      <Edit2 className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              ))}
            </div>

            {/* Desktop table */}
            <div className="hidden overflow-x-auto sm:block">
              <table className="w-full text-right">
                <thead>
                  <tr className="border-b border-slate-100 no-print">
                    <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest">الشريك</th>
                    <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest hidden sm:table-cell">التواصل</th>
                    <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest text-center">الحصة</th>
                    <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest text-center">الحالة</th>
                    <th className="px-5 py-3.5 text-[10px] font-bold uppercase text-slate-400 tracking-widest text-left">إجراءات</th>
                  </tr>
                  {/* Print header */}
                  <tr className="hidden print:table-row bg-slate-100 border-b border-slate-300">
                    <th className="px-4 py-2 text-right text-xs font-bold">الاسم</th>
                    <th className="px-4 py-2 text-right text-xs font-bold">الواتساب</th>
                    <th className="px-4 py-2 text-center text-xs font-bold">الحصة</th>
                    <th className="px-4 py-2 text-center text-xs font-bold">الحالة</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-50">
                  {filteredPartners.map((partner) => (
                    <tr key={partner.id} className="group hover:bg-slate-50/80 transition-colors duration-200">
                      {/* Partner info */}
                      <td className="px-5 py-3.5">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs group-hover:bg-sky-50 group-hover:text-sky-600 transition-colors duration-200 shrink-0">
                            {partner.name.substring(0, 2)}
                          </div>
                          <div className="min-w-0">
                            <p className="text-sm font-bold text-slate-900 truncate">{partner.name}</p>
                            {partner.whatsapp && (
                              <p className="text-xs text-slate-400 font-medium mt-0.5 sm:hidden flex items-center gap-1">
                                <Phone className="w-3 h-3" />
                                {partner.whatsapp}
                              </p>
                            )}
                          </div>
                        </div>
                      </td>
                      {/* Contact */}
                      <td className="px-5 py-3.5 hidden sm:table-cell">
                        <div className="space-y-1">
                          {partner.whatsapp && (
                            <div className="flex items-center gap-2 text-sm text-slate-600">
                              <Phone className="w-3.5 h-3.5 text-primary-500" />
                              <span className="font-medium">{partner.whatsapp}</span>
                            </div>
                          )}
                          {partner.email && (
                            <div className="flex items-center gap-2 text-xs text-slate-400">
                              <Mail className="w-3.5 h-3.5" />
                              <span className="font-medium truncate max-w-[160px]">{partner.email}</span>
                            </div>
                          )}
                        </div>
                      </td>
                      {/* Share */}
                      <td className="px-5 py-3.5 text-center">
                        {partner.shares && partner.shares.length > 0 ? (
                          <span className="inline-flex items-center px-3 py-1.5 rounded-lg bg-sky-50 text-sky-700 font-bold text-sm border border-sky-100">
                            {partner.shares[0].share_percent}%
                          </span>
                        ) : (
                          <span className="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-50 text-slate-400 font-semibold text-sm">
                            0%
                          </span>
                        )}
                      </td>
                      {/* Status */}
                      <td className="px-5 py-3.5 text-center">
                        <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold ${
                          partner.status === 'active'
                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-100'
                            : 'bg-slate-50 text-slate-400 border border-slate-100'
                        }`}>
                          <span className={`w-1.5 h-1.5 rounded-full ${partner.status === 'active' ? 'bg-emerald-500' : 'bg-slate-300'}`} />
                          {partner.status === 'active' ? 'نشط' : 'غير نشط'}
                        </span>
                      </td>
                      {/* Actions */}
                      <td className="px-5 py-3.5 no-print">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            onClick={() => handleOpenLedger(partner)}
                            className="h-8 px-2.5 flex items-center gap-1.5 bg-primary-50 border border-primary-100 rounded-lg text-primary-600 hover:bg-primary-100 text-xs font-bold transition-colors duration-200"
                            title="المحفظة المالية"
                          >
                            <Wallet className="w-3.5 h-3.5" />
                            <span className="hidden sm:inline">المحفظة</span>
                          </button>
                          <button
                            onClick={() => handleEdit(partner)}
                            className="h-8 w-8 flex items-center justify-center bg-slate-50 border border-slate-100 rounded-lg text-slate-400 hover:text-sky-600 hover:bg-sky-50 hover:border-sky-100 transition-colors duration-200"
                            title="تعديل"
                          >
                            <Edit2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </Card>
```

- [ ] **Step 2: Verify**

Run: `cd frontend && npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add "frontend/src/app/(farm)/partners/page.tsx"
git commit -m "fix: add mobile card view for partners table

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Expenses page — mobile card fallback + bigger close button

**Files:**
- Modify: `frontend/src/app/(farm)/expenses/page.tsx:146-148` (close button)
- Modify: `frontend/src/app/(farm)/expenses/page.tsx:253-291` (table)

**Interfaces:** None — uses existing imports (`formatDate`, `formatNumber` from `@/lib/utils`, `X` from `lucide-react`, `PAYMENT_STATUS_LABEL` module constant already defined at the top of this file). No new imports needed.

- [ ] **Step 1: Give the inline-form close button a real touch target**

Replace:

```tsx
            <button type="button" onClick={handleCancel} className="text-slate-400 hover:text-slate-600">
              <X className="h-4 w-4" />
            </button>
```

with:

```tsx
            <button
              type="button"
              onClick={handleCancel}
              className="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
            >
              <X className="h-4 w-4" />
            </button>
```

- [ ] **Step 2: Replace the expenses table with a card+table split**

Replace this exact block (lines 252–291):

```tsx
      {/* Table */}
      {!loading && !error && expenses.length > 0 && (
        <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                <th className="px-5 py-3">التاريخ</th>
                <th className="px-5 py-3">التصنيف</th>
                <th className="px-5 py-3">الفوج</th>
                <th className="px-5 py-3">المبلغ</th>
                <th className="px-5 py-3">حالة الدفع</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {expenses.map((expense) => {
                const status = PAYMENT_STATUS_LABEL[expense.payment_status]
                return (
                  <tr key={expense.id} className="transition-colors duration-200 hover:bg-slate-50">
                    <td className="px-5 py-3 text-slate-500">{formatDate(expense.entry_date)}</td>
                    <td className="px-5 py-3 font-medium text-slate-800">
                      {expense.category_name ?? expense.expense_type ?? '—'}
                    </td>
                    <td className="px-5 py-3 text-slate-500">{expense.flock_name ?? '—'}</td>
                    <td className="px-5 py-3 tabular-nums font-semibold text-slate-800">
                      {formatNumber(expense.total_amount)} USD
                    </td>
                    <td className="px-5 py-3">
                      {status && (
                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${status.color}`}>
                          {status.label}
                        </span>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
```

with:

```tsx
      {/* Table: mobile cards + desktop table */}
      {!loading && !error && expenses.length > 0 && (
        <>
          {/* Mobile cards */}
          <div className="space-y-2 sm:hidden">
            {expenses.map((expense) => {
              const status = PAYMENT_STATUS_LABEL[expense.payment_status]
              return (
                <div key={expense.id} className="rounded-2xl border border-slate-200/60 bg-white p-3.5" style={{ boxShadow: 'var(--shadow-card)' }}>
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-bold text-slate-800">{expense.category_name ?? expense.expense_type ?? '—'}</p>
                      <p className="mt-0.5 text-[10px] text-slate-400">
                        {formatDate(expense.entry_date)}{expense.flock_name ? ` · ${expense.flock_name}` : ''}
                      </p>
                    </div>
                    {status && (
                      <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-semibold ${status.color}`}>
                        {status.label}
                      </span>
                    )}
                  </div>
                  <p className="mt-2 text-sm font-black tabular-nums text-slate-800">{formatNumber(expense.total_amount)} USD</p>
                </div>
              )
            })}
          </div>

          {/* Desktop table */}
          <div className="hidden overflow-hidden rounded-2xl border border-slate-200/60 bg-white sm:block" style={{ boxShadow: 'var(--shadow-card)' }}>
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                  <th className="px-5 py-3">التاريخ</th>
                  <th className="px-5 py-3">التصنيف</th>
                  <th className="px-5 py-3">الفوج</th>
                  <th className="px-5 py-3">المبلغ</th>
                  <th className="px-5 py-3">حالة الدفع</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {expenses.map((expense) => {
                  const status = PAYMENT_STATUS_LABEL[expense.payment_status]
                  return (
                    <tr key={expense.id} className="transition-colors duration-200 hover:bg-slate-50">
                      <td className="px-5 py-3 text-slate-500">{formatDate(expense.entry_date)}</td>
                      <td className="px-5 py-3 font-medium text-slate-800">
                        {expense.category_name ?? expense.expense_type ?? '—'}
                      </td>
                      <td className="px-5 py-3 text-slate-500">{expense.flock_name ?? '—'}</td>
                      <td className="px-5 py-3 tabular-nums font-semibold text-slate-800">
                        {formatNumber(expense.total_amount)} USD
                      </td>
                      <td className="px-5 py-3">
                        {status && (
                          <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${status.color}`}>
                            {status.label}
                          </span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </>
      )}
```

- [ ] **Step 3: Verify**

Run: `cd frontend && npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add "frontend/src/app/(farm)/expenses/page.tsx"
git commit -m "fix: mobile card view for expenses table, larger close button touch target

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Flocks page — mobile card fallback for the closed-flocks table

**Files:**
- Modify: `frontend/src/app/(farm)/flocks/page.tsx:175-213`

**Interfaces:** None — uses existing imports (`Link` from `next/link`, `FlockStatusBadge` from `@/components/flocks/FlockStatusBadge`, `formatDate`, `formatNumber` from `@/lib/utils`). No new imports needed.

- [ ] **Step 1: Replace the closed-flocks table with a card+table split**

Replace this exact block (lines 175–213):

```tsx
              <div className="overflow-hidden rounded-2xl border border-slate-200/60 bg-white" style={{ boxShadow: 'var(--shadow-card)' }}>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                      <th className="px-5 py-3">الفوج</th>
                      <th className="px-5 py-3">تاريخ البدء</th>
                      <th className="px-5 py-3">تاريخ الإغلاق</th>
                      <th className="px-5 py-3">العدد الأولي</th>
                      <th className="px-5 py-3">الحالة</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {closedFlocks.map((flock) => (
                      <tr key={flock.id} className="transition-colors duration-200 hover:bg-slate-50">
                        <td className="px-5 py-3">
                          <Link
                            href={`/flocks/${flock.id}`}
                            className="font-medium text-slate-800 hover:text-primary-600 transition-colors duration-200"
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
```

with:

```tsx
              {/* Mobile cards */}
              <div className="space-y-2 sm:hidden">
                {closedFlocks.map((flock) => (
                  <Link
                    key={flock.id}
                    href={`/flocks/${flock.id}`}
                    className="block rounded-2xl border border-slate-200/60 bg-white p-3.5"
                    style={{ boxShadow: 'var(--shadow-card)' }}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <p className="truncate text-sm font-bold text-slate-800">{flock.name}</p>
                      <FlockStatusBadge status={flock.status} />
                    </div>
                    <div className="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                      <span>{formatDate(flock.start_date)} → {flock.end_date ? formatDate(flock.end_date) : '—'}</span>
                      <span className="font-bold text-slate-700">{formatNumber(flock.initial_count)} طائر</span>
                    </div>
                  </Link>
                ))}
              </div>

              {/* Desktop table */}
              <div className="hidden overflow-hidden rounded-2xl border border-slate-200/60 bg-white sm:block" style={{ boxShadow: 'var(--shadow-card)' }}>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-slate-100 bg-slate-50 text-right text-xs font-semibold text-slate-500">
                      <th className="px-5 py-3">الفوج</th>
                      <th className="px-5 py-3">تاريخ البدء</th>
                      <th className="px-5 py-3">تاريخ الإغلاق</th>
                      <th className="px-5 py-3">العدد الأولي</th>
                      <th className="px-5 py-3">الحالة</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {closedFlocks.map((flock) => (
                      <tr key={flock.id} className="transition-colors duration-200 hover:bg-slate-50">
                        <td className="px-5 py-3">
                          <Link
                            href={`/flocks/${flock.id}`}
                            className="font-medium text-slate-800 hover:text-primary-600 transition-colors duration-200"
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
```

- [ ] **Step 2: Verify**

Run: `cd frontend && npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add "frontend/src/app/(farm)/flocks/page.tsx"
git commit -m "fix: add mobile card view for closed-flocks table

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Workers page — bigger delete-button touch target

**Files:**
- Modify: `frontend/src/app/(farm)/workers/page.tsx:116-121`

**Interfaces:** None — className-only change, no logic touched.

- [ ] **Step 1: Enlarge the icon-only delete button's hit area**

Replace:

```tsx
                <button
                  onClick={() => handleDelete(worker.id, worker.name)}
                  disabled={isDeleting === worker.id}
                  className="p-2 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                  title="حذف العامل"
                >
```

with:

```tsx
                <button
                  onClick={() => handleDelete(worker.id, worker.name)}
                  disabled={isDeleting === worker.id}
                  className="flex h-10 w-10 shrink-0 items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all"
                  title="حذف العامل"
                >
```

(The button's children — the `Loader2`/`Trash2` icon — are unchanged; only the wrapping `<button>`'s className changes from padding-based sizing to an explicit 40×40px flex-centered box.)

- [ ] **Step 2: Verify**

Run: `cd frontend && npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add "frontend/src/app/(farm)/workers/page.tsx"
git commit -m "fix: enlarge worker delete button touch target

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Post-implementation

After all 6 tasks are committed, update the spec's status is not required (specs are point-in-time design docs, not living trackers). No further pages need changes per the approved scope — `dashboard/page.tsx`, `workers/new/page.tsx`, and `flocks/[id]/page.tsx` were audited and found to have no issues of the fixed classes (see spec's referenced recon).
