export type NoteType = 'general' | 'instruction' | 'operational' | 'alert'

export interface FlockNote {
  id: number
  flock_id: number
  note_type: NoteType
  note_text: string
  entry_date: string | null  // YYYY-MM-DD
  created_at: string
}

export interface CreateFlockNotePayload {
  note_text: string
  note_type?: NoteType
  entry_date?: string
}
