import { redirect } from 'next/navigation'

// Root "/" redirects to flocks; proxy handles unauth redirect to /login
export default function RootPage() {
  redirect('/flocks')
}
