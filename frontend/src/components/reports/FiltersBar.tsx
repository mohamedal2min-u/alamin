'use client'

import { Input } from "@/components/ui/Input"
import { Button } from "@/components/ui/Button"
import { Search, Filter, Printer, Download, Calendar } from "lucide-react"
import { useQuery } from "@tanstack/react-query"
import { flocksApi } from "@/lib/api/flocks"

interface FiltersBarProps {
  onFilterChange: (filters: any) => void
  filters: any
}

export const FiltersBar = ({ onFilterChange, filters }: FiltersBarProps) => {
  const { data: flocks } = useQuery({
    queryKey: ['flocks'],
    queryFn: () => flocksApi.list()
  })

  return (
    <div className="flex flex-col gap-4 bg-white p-4 rounded-2xl border border-slate-200/60" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Flock Selector */}
        <div className="space-y-1.5">
          <label className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
            <Filter className="w-3 h-3" /> اختيار الفوج
          </label>
          <select 
            className="w-full h-10 px-3 text-sm rounded-xl border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-colors duration-200"
            value={filters.flock_id || ''}
            onChange={(e) => onFilterChange({ ...filters, flock_id: e.target.value })}
          >
            <option value="">جميع الأفواج</option>
            {flocks?.data?.map((flock: any) => (
              <option key={flock.id} value={flock.id}>{flock.name}</option>
            ))}
          </select>
        </div>

        {/* Start Date */}
        <div className="space-y-1.5">
          <label className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
            <Calendar className="w-3 h-3" /> من تاريخ
          </label>
          <Input 
            type="date" 
            className="h-10 bg-slate-50"
            value={filters.start_date || ''}
            onChange={(e) => onFilterChange({ ...filters, start_date: e.target.value })}
          />
        </div>

        {/* End Date */}
        <div className="space-y-1.5">
          <label className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
            <Calendar className="w-3 h-3" /> إلى تاريخ
          </label>
          <Input 
            type="date" 
            className="h-10 bg-slate-50"
            value={filters.end_date || ''}
            onChange={(e) => onFilterChange({ ...filters, end_date: e.target.value })}
          />
        </div>

        {/* Search */}
        <div className="space-y-1.5">
          <label className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
            <Search className="w-3 h-3" /> بحث سريع
          </label>
          <Input 
            placeholder="بحث..." 
            className="h-10 bg-slate-50"
            value={filters.query || ''}
            onChange={(e) => onFilterChange({ ...filters, query: e.target.value })}
          />
        </div>
      </div>

    </div>
  )
}
