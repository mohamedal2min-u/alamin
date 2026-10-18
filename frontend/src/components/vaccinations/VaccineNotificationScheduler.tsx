'use client'

import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { vaccinationsApi, vaccineKeys } from '@/lib/api/vaccinations'
import { syncVaccineNotifications } from '@/lib/vaccines/native'
import { useFarmStore } from '@/stores/farm.store'

/**
 * يُركَّب في تخطيط المزرعة: عند فتح التطبيق أو تغيّر لقاحات الفوج النشط يعيد جدولة
 * الإشعارات المحلية على الأندرويد. لا استطلاع — يعتمد على تحديث React Query عند الحفظ.
 */
export function VaccineNotificationScheduler() {
  const farmId = useFarmStore((s) => s.currentFarm?.id)

  const { data } = useQuery({
    queryKey: vaccineKeys.active(farmId),
    queryFn: vaccinationsApi.active,
    enabled: !!farmId,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  })

  useEffect(() => {
    if (!data) return
    void syncVaccineNotifications(data.flock?.name ?? null, data.data)
  }, [data])

  return null
}
