import { create } from 'zustand'

interface LayoutState {
  pageTitle: string
  pageSubtitle: string | null
  setPageTitle: (title: string) => void
  setPageSubtitle: (subtitle: string | null) => void
  resetHeader: () => void
}

export const useLayoutStore = create<LayoutState>((set) => ({
  pageTitle: 'الياسين',
  pageSubtitle: null,
  setPageTitle: (title) => set({ pageTitle: title }),
  setPageSubtitle: (subtitle) => set({ pageSubtitle: subtitle }),
  resetHeader: () => set({ pageTitle: 'الياسين', pageSubtitle: null }),
}))
