import { useState, useEffect } from 'react'

// مستمع تمرير خفيف: passive + مرة واحدة لكل إطار (rAF) + لا يُعاد تسجيله، حتى لا يستهلك البطارية أثناء التمرير
export function useScrollDirection() {
  const [scrollDirection, setScrollDirection] = useState<'up' | 'down' | null>(null)
  const [isAtTop, setIsAtTop] = useState(true)

  useEffect(() => {
    let lastScrollY = window.pageYOffset
    let direction: 'up' | 'down' | null = null
    let frame = 0

    const update = () => {
      frame = 0
      const scrollY = window.pageYOffset
      if (Math.abs(scrollY - lastScrollY) > 10) {
        const next = scrollY > lastScrollY ? 'down' : 'up'
        if (next !== direction) {
          direction = next
          setScrollDirection(next)
        }
        lastScrollY = scrollY > 0 ? scrollY : 0
      }
      setIsAtTop(scrollY < 10)
    }

    const onScroll = () => {
      if (!frame) frame = window.requestAnimationFrame(update)
    }

    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      window.removeEventListener('scroll', onScroll)
      if (frame) window.cancelAnimationFrame(frame)
    }
  }, [])

  return { scrollDirection, isAtTop }
}
