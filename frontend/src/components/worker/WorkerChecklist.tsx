'use client'

import { useState } from 'react'
import { ChecklistItem } from './ChecklistItem'
import { MortalitySheet } from './sheets/MortalitySheet'
import { FeedSheet } from './sheets/FeedSheet'
import { TemperatureSheet } from './sheets/TemperatureSheet'
import { MedicineSheet } from './sheets/MedicineSheet'
import { NoteSheet } from './sheets/NoteSheet'
import { WorkerProgressBar } from './WorkerProgressBar'
import { Button } from '@/components/ui/Button'


export interface WorkerChecklistProps {
  flockId: number
}

// Temporary in-memory state representation for the MVP
export function WorkerChecklist({ flockId }: WorkerChecklistProps) {
  // States to keep track of completed tasks
  const [mortality, setMortality] = useState<number | null>(null)
  const [feed, setFeed] = useState<number | null>(null)
  const [tempMorning, setTempMorning] = useState<number | null>(null)
  const [tempAfternoon, setTempAfternoon] = useState<number | null>(null)
  const [tempEvening, setTempEvening] = useState<number | null>(null)
  const [medicine, setMedicine] = useState<string | null>(null)
  const [note, setNote] = useState<string | null>(null)
  
  const [dayClosed, setDayClosed] = useState(false)

  // Modals state
  const [openSheet, setOpenSheet] = useState<string | null>(null)

  // Calculate progress
  // Mandatory: Mortality, Feed, Morning Temp
  let completedMandatory = 0
  if (mortality !== null) completedMandatory++
  if (feed !== null) completedMandatory++
  if (tempMorning !== null) completedMandatory++
  
  const totalMandatory = 3
  const isMandatoryComplete = completedMandatory === totalMandatory

  const handleEndDay = () => {
    // API call placeholder
    setDayClosed(true)
  }

  if (dayClosed) {
    return (
      <div className="bg-primary-50 border border-primary-200 rounded-2xl p-6 text-center shadow-sm">
        <div className="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <span className="text-3xl">✓</span>
        </div>
        <h2 className="text-xl font-bold text-primary-900 mb-2">تم تسجيل الوردية</h2>
        <p className="text-primary-700">لقد تم إنهاء اليوم بنجاح ولا يمكن تعديل الإدخالات.</p>
      </div>
    )
  }

  return (
    <div>
      <WorkerProgressBar completed={completedMandatory} total={totalMandatory} />

      <div className="space-y-3 mb-8">
        <ChecklistItem
          label="النفوق اليوم"
          isRequired={true}
          isCompleted={mortality !== null}
          displayValue={mortality !== null ? `${mortality} طيور` : undefined}
          onClick={() => setOpenSheet('mortality')}
        />

        <ChecklistItem
          label="العلف الفعلي"
          isRequired={true}
          isCompleted={feed !== null}
          displayValue={feed !== null ? `${feed} كجم` : undefined}
          onClick={() => setOpenSheet('feed')}
        />

        <ChecklistItem
          label="حرارة الصباح"
          isRequired={true}
          isCompleted={tempMorning !== null}
          displayValue={tempMorning !== null ? `${tempMorning}°C` : undefined}
          onClick={() => setOpenSheet('temp_morning')}
        />

        <ChecklistItem
          label="حرارة الظهر"
          isRequired={false}
          isCompleted={tempAfternoon !== null}
          displayValue={tempAfternoon !== null ? `${tempAfternoon}°C` : undefined}
          onClick={() => setOpenSheet('temp_afternoon')}
        />

        <ChecklistItem
          label="حرارة المساء"
          isRequired={false}
          isCompleted={tempEvening !== null}
          displayValue={tempEvening !== null ? `${tempEvening}°C` : undefined}
          onClick={() => setOpenSheet('temp_evening')}
        />

        <ChecklistItem
          label="دواء اليوم"
          isRequired={false}
          isCompleted={medicine !== null}
          displayValue={medicine !== null ? medicine : undefined}
          onClick={() => setOpenSheet('medicine')}
        />

        <ChecklistItem
          label="ملاحظة طارئة"
          isRequired={false}
          isCompleted={note !== null}
          displayValue={note !== null ? 'تمت الإضافة' : undefined}
          onClick={() => setOpenSheet('note')}
        />
      </div>

      {isMandatoryComplete && (
        <div className="fixed bottom-6 left-4 right-4 z-40 animate-in slide-in-from-bottom flex justify-center">
          <Button
            onClick={handleEndDay}
            className="w-full max-w-sm h-14 text-lg font-bold shadow-xl shadow-primary-500/20"
          >
            🔒 إنهاء يوم اليوم
          </Button>
        </div>
      )}

      {/* Sheets */}
      <MortalitySheet 
        flockId={flockId} 
        isOpen={openSheet === 'mortality'} 
        onClose={() => setOpenSheet(null)} 
        onSaved={(qty) => setMortality(qty)} 
      />
      
      <FeedSheet 
        flockId={flockId} 
        isOpen={openSheet === 'feed'} 
        onClose={() => setOpenSheet(null)} 
        onSaved={(qty) => setFeed(qty)} 
      />
      
      <TemperatureSheet 
        flockId={flockId} 
        isOpen={openSheet?.startsWith('temp_') || false} 
        onClose={() => setOpenSheet(null)} 
        timeOfDay={openSheet === 'temp_afternoon' ? 'afternoon' : openSheet === 'temp_evening' ? 'evening' : 'morning'}
        title={openSheet === 'temp_afternoon' ? 'حرارة الظهر' : openSheet === 'temp_evening' ? 'حرارة المساء' : 'حرارة الصباح'}
        onSaved={(temp) => {
          if (openSheet === 'temp_afternoon') setTempAfternoon(temp)
          else if (openSheet === 'temp_evening') setTempEvening(temp)
          else setTempMorning(temp)
        }} 
      />
      
      <MedicineSheet 
        flockId={flockId} 
        isOpen={openSheet === 'medicine'} 
        onClose={() => setOpenSheet(null)} 
        onSaved={(has, lbl) => setMedicine(has ? (lbl || 'تم التسجيل') : 'لا يوجد')} 
      />
      
      <NoteSheet 
        flockId={flockId} 
        isOpen={openSheet === 'note'} 
        onClose={() => setOpenSheet(null)} 
        onSaved={(n) => setNote(n)} 
      />
    </div>
  )
}

