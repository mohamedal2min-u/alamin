'use client';
import { useEffect } from 'react';

export function ArabicDigitsSanitizer() {
  useEffect(() => {
    const handleInput = (e: Event) => {
      const target = e.target as HTMLInputElement | HTMLTextAreaElement;
      if (!target || typeof target.value !== 'string') return;
      
      const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
      const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
      
      let val = target.value;
      let hasChanges = false;
      for (let i = 0; i < 10; i++) {
        if (val.includes(arabicNumbers[i]) || val.includes(persianNumbers[i])) {
           val = val.replaceAll(arabicNumbers[i], i.toString());
           val = val.replaceAll(persianNumbers[i], i.toString());
           hasChanges = true;
        }
      }
      
      if (hasChanges && val !== target.value) {
        const nativeSetter = Object.getOwnPropertyDescriptor(
          Object.getPrototypeOf(target),
          'value'
        )?.set;
        
        if (nativeSetter) {
          nativeSetter.call(target, val);
        }
      }
    };
    
    // Capture phase intercepts before React's bubble listeners
    document.addEventListener('input', handleInput, true);
    return () => document.removeEventListener('input', handleInput, true);
  }, []);

  return null;
}
