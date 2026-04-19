'use client'

import * as React from "react"
import { cn } from "@/lib/utils"

interface TabsContextType {
  value: string
  onValueChange: (value: string) => void
}

const TabsContext = React.createContext<TabsContextType | undefined>(undefined)

interface TabsProps {
  value?: string
  defaultValue?: string
  onValueChange?: (value: string) => void
  children: React.ReactNode
  className?: string
}

export const Tabs = ({ value: controlledValue, defaultValue, onValueChange, children, className }: TabsProps) => {
  const [uncontrolledValue, setUncontrolledValue] = React.useState(defaultValue ?? "")
  
  const isControlled = controlledValue !== undefined
  const value = isControlled ? controlledValue : uncontrolledValue

  const handleValueChange = (newValue: string) => {
    if (!isControlled) {
      setUncontrolledValue(newValue)
    }
    onValueChange?.(newValue)
  }

  return (
    <TabsContext.Provider value={{ value, onValueChange: handleValueChange }}>
      <div className={cn("flex flex-col gap-4", className)}>
        {children}
      </div>
    </TabsContext.Provider>
  )
}

export const TabsList = ({ children, className }: { children: React.ReactNode, className?: string }) => {
  return (
    <div className={cn(
      "flex gap-1 overflow-x-auto p-1 bg-slate-100 rounded-xl no-scrollbar",
      className
    )}>
      {children}
    </div>
  )
}

export const TabsTrigger = ({ value, children, className }: { value: string, children: React.ReactNode, className?: string }) => {
  const context = React.useContext(TabsContext)
  if (!context) throw new Error("TabsTrigger must be used within Tabs")

  const isActive = context.value === value

  return (
    <button
      onClick={() => context.onValueChange(value)}
      className={cn(
        "flex-1 whitespace-nowrap px-4 py-2 text-sm font-semibold transition-all duration-200 rounded-lg",
        isActive 
          ? "bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/60" 
          : "text-slate-500 hover:text-slate-700 hover:bg-white/50",
        className
      )}
    >
      {children}
    </button>
  )
}

export const TabsContent = ({ value, children }: { value: string, children: React.ReactNode }) => {
  const context = React.useContext(TabsContext)
  if (!context) throw new Error("TabsContent must be used within Tabs")

  if (context.value !== value) return null

  return <div className="animate-in fade-in duration-200">{children}</div>
}

