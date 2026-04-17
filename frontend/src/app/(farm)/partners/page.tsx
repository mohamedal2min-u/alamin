'use client'

import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import { partnersApi, Partner } from '@/lib/api/partners'
import { flocksApi } from '@/lib/api/flocks'
import type { Flock } from '@/types/flock'
import { useFarmStore } from '@/stores/farm.store'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { PartnerModal } from '@/components/partners/PartnerModal'
import { PartnerLedgerModal } from '@/components/partners/PartnerLedgerModal'
import {
  Users, UserPlus, Phone, Mail, Edit2, Search,
  Wallet, Printer, TrendingUp, Shield
} from 'lucide-react'

export default function PartnersPage() {
  const queryClient = useQueryClient()
  const { currentFarm } = useFarmStore()
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isLedgerOpen, setIsLedgerOpen] = useState(false)
  const [selectedPartner, setSelectedPartner] = useState<Partner | null>(null)
  const [searchQuery, setSearchQuery] = useState('')

  const { data: partners, isLoading } = useQuery({
    queryKey: ['partners', currentFarm?.id],
    queryFn: () => partnersApi.getAll(),
    staleTime: 2 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
    placeholderData: keepPreviousData,
  })

  // Reuses the same cache entry as dashboard/worker/FlockGlobalHeader
  const { data: flocksList = [] } = useQuery<Flock[]>({
    queryKey: ['flocks', currentFarm?.id],
    queryFn: () => flocksApi.list().then(res => res.data),
    staleTime: 2 * 60 * 1000,
    gcTime: 10 * 60 * 1000,
    enabled: !!currentFarm,
  })


  const createMutation = useMutation({
    mutationFn: (data: any) => partnersApi.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partners', currentFarm?.id] })
      setIsModalOpen(false)
    }
  })

  const updateMutation = useMutation({
    mutationFn: (data: any) => partnersApi.update(selectedPartner!.id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['partners', currentFarm?.id] })
      setIsModalOpen(false)
    }
  })

  const handleSave = async (data: any) => {
    if (selectedPartner) {
      await updateMutation.mutateAsync(data)
    } else {
      await createMutation.mutateAsync(data)
    }
  }

  const handleEdit = (partner: Partner) => {
    setSelectedPartner(partner)
    setIsModalOpen(true)
  }

  const handleOpenLedger = (partner: Partner) => {
    setSelectedPartner(partner)
    setIsLedgerOpen(true)
  }

  const filteredPartners = React.useMemo(() => {
    if (!partners) return []
    return partners.filter(p => 
      p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.whatsapp?.includes(searchQuery)
    )
  }, [partners, searchQuery])

  // Stats
  const { totalPartners, activePartners, totalSharesAllocated } = React.useMemo(() => {
    if (!partners) return { totalPartners: 0, activePartners: 0, totalSharesAllocated: 0 }
    
    const total = partners.length
    const active = partners.filter(p => p.status === 'active').length
    const sharesSum = partners.reduce((sum, p) => {
      const share = p.shares?.[0]?.share_percent || 0
      return sum + share
    }, 0)
    
    return {
      totalPartners: total,
      activePartners: active,
      totalSharesAllocated: sharesSum
    }
  }, [partners])

  const hasActiveFlock = React.useMemo(() => 
    flocksList.some(f => f.status === 'active'),
  [flocksList])

  return (
    <div className="space-y-5 pb-24 sm:pb-8" dir="rtl">
      {/* ─── Page Actions ─── */}
      <div className="flex items-center justify-between no-print">
        <div className="flex items-center gap-2">
          <button 
            onClick={() => window.print()}
            className="h-10 w-10 flex items-center justify-center bg-slate-100 border border-slate-200/60 rounded-xl text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition-colors duration-200"
            title="طباعة كشف الشركاء"
          >
            <Printer className="w-4 h-4" />
          </button>
        </div>
        <Button 
          onClick={() => { setSelectedPartner(null); setIsModalOpen(true); }}
          className="h-10 px-5 rounded-xl"
        >
          <UserPlus className="w-4 h-4 ml-1.5" />
          إضافة شريك
        </Button>
      </div>

      {/* ─── Stats Row ─── */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 no-print">
        <StatCard icon={Users} label="إجمالي الشركاء" value={totalPartners} accent="bg-sky-500" />
        <StatCard icon={Shield} label="الشركاء النشطون" value={activePartners} accent="bg-emerald-500" />
        <StatCard icon={TrendingUp} label="الحصص الموزّعة" value={`${totalSharesAllocated}%`} accent="bg-amber-500" />
        <div className="relative group">
          <div className="h-full flex items-center bg-white border border-slate-200/60 rounded-2xl overflow-hidden" style={{ boxShadow: 'var(--shadow-card)' }}>
            <Search className="absolute right-4 w-4 h-4 text-slate-300 group-focus-within:text-sky-500 transition-colors duration-200" />
            <input
              type="text"
              placeholder="بحث..."
              className="w-full h-full min-h-[72px] pr-11 pl-4 bg-transparent text-sm font-bold text-slate-900 placeholder:text-slate-300 focus:outline-none"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
        </div>
      </div>

      {/* ─── Partners Table ─── */}
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
                            <Phone className="w-3.5 h-3.5 text-emerald-500" />
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
                          className="h-8 px-2.5 flex items-center gap-1.5 bg-emerald-50 border border-emerald-100 rounded-lg text-emerald-600 hover:bg-emerald-100 text-xs font-bold transition-colors duration-200"
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

      {/* ─── Modals ─── */}
      <PartnerModal 
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSave={handleSave}
        initialData={selectedPartner}
        hasActiveFlock={hasActiveFlock}
      />

      <PartnerLedgerModal
        isOpen={isLedgerOpen}
        onClose={() => setIsLedgerOpen(false)}
        partner={selectedPartner}
      />
    </div>
  )
}

/* ─── Stat Card Component ─── */
function StatCard({ icon: Icon, label, value, accent }: { icon: any; label: string; value: string | number; accent: string }) {
  return (
    <div className="flex items-center gap-3 bg-white border border-slate-200/60 rounded-2xl p-4" style={{ boxShadow: 'var(--shadow-card)' }}>
      <div className={`w-9 h-9 rounded-xl ${accent} flex items-center justify-center text-white shrink-0`}>
        <Icon className="w-4 h-4" />
      </div>
      <div className="min-w-0">
        <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate">{label}</p>
        <p className="text-xl font-bold text-slate-900 mt-0.5 leading-none">{value}</p>
      </div>
    </div>
  )
}
