# Project Plan: Professional Global Header

## Goal
Implement a professional green global header consistent across the entire platform, featuring a custom chicken logo and a "LIVE" status indicator.

## Proposed Strategy
1.  **Refactor Layouts**: Consolidate the two separate headers into a unified design or shared component.
2.  **Brand Identity**: Integration of the provided chicken logo and professional emerald-based color scheme.
3.  **Live Presence**: Add a high-visibility "LIVE" badge to give an operational, real-time feel to the dashboard and administration.

## Component Breakdown

### 1. Logo Asset
- Save the provided chicken outline as `public/logo-chicken.png`.
- Ensure it has a transparent background for flexible theme support.

### 2. LiveStatus Component
- A reusable component that shows a pulsing green dot + "LIVE" text.
- Used in both Desktop and Mobile headers.

### 3. Professional Header (Unified)
- **Background**: `bg-emerald-950` (Deep professional green) or `bg-slate-900` with emerald accents.
- **RTL Support**: Logo on the right, interactions on the left.

## Task List
- [ ] Save chicken logo to `public/logo-chicken.png`.
- [ ] Create `LiveStatus.tsx` component.
- [ ] Redesign `Header.tsx` (Desktop/Super Admin).
- [ ] Redesign mobile header in `(farm)/layout.tsx`.
- [ ] Verify responsiveness and RTL alignment.

## Open Questions for the user
1. **Logo Text**: Use only the chicken icon, or add the word "دجاجاتي" next to it?
2. **Sticky Behavior**: Should the header be fixed at the top while scrolling?
3. **Indicator Placement**: Where on the header should the "LIVE" badge appear? (next to logo, or near the user menu?)
