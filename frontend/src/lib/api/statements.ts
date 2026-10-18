import { apiClient } from './client'

export type StatementKind = 'flock' | 'partner' | 'sale'

export interface StatementLink {
  url: string
  /** مسار نسبي موقّع: يُكمَل بعنوان الـ API العام فيعمل خلف الوكيل العكسي */
  path: string
  expires_in: number
}

export const statementsApi = {
  link: (kind: StatementKind, id: number) =>
    apiClient.get<StatementLink>(`/statements/${kind}/${id}`).then((r) => r.data),
}

/** الرابط المطلق الصالح للفتح من المتصفح أو واتساب (بلا ترويسات مصادقة) */
export function statementUrl(link: StatementLink): string {
  const base = process.env.NEXT_PUBLIC_API_URL?.replace(/\/+$/, '')
  return base ? `${base}${link.path}` : link.url
}
