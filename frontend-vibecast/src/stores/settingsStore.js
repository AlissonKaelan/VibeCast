import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useSettingsStore = defineStore('settings', () => {
  const isSettingsModalOpen = ref(false)
  const primaryColor = ref(localStorage.getItem('vibecast_color') || 'blue')
  const useGlassmorphism = ref(localStorage.getItem('vibecast_glass') !== 'false')

  // AS NOVAS FUNÇÕES BLINDADAS:
  const openSettingsModal = () => { isSettingsModalOpen.value = true }
  const closeSettingsModal = () => { isSettingsModalOpen.value = false }

  const setPrimaryColor = (color) => {
    primaryColor.value = color
    localStorage.setItem('vibecast_color', color)
  }

  const toggleGlassmorphism = () => {
    useGlassmorphism.value = !useGlassmorphism.value
    localStorage.setItem('vibecast_glass', useGlassmorphism.value)
  }

  const theme = computed(() => {
    const colors = {
      blue: { text: 'text-blue-500', textLight: 'text-blue-400', bg: 'bg-blue-600', hoverBg: 'hover:bg-blue-500', border: 'border-blue-500/30', focusBorder: 'focus:border-blue-500/50', ring: 'focus:ring-blue-500/50', glow: 'shadow-[0_0_15px_rgba(59,130,246,0.15)]' },
      purple: { text: 'text-purple-500', textLight: 'text-purple-400', bg: 'bg-purple-600', hoverBg: 'hover:bg-purple-500', border: 'border-purple-500/30', focusBorder: 'focus:border-purple-500/50', ring: 'focus:ring-purple-500/50', glow: 'shadow-[0_0_15px_rgba(147,51,234,0.15)]' },
      emerald: { text: 'text-emerald-500', textLight: 'text-emerald-400', bg: 'bg-emerald-600', hoverBg: 'hover:bg-emerald-500', border: 'border-emerald-500/30', focusBorder: 'focus:border-emerald-500/50', ring: 'focus:ring-emerald-500/50', glow: 'shadow-[0_0_15px_rgba(16,185,129,0.15)]' },
      rose: { text: 'text-rose-500', textLight: 'text-rose-400', bg: 'bg-rose-600', hoverBg: 'hover:bg-rose-500', border: 'border-rose-500/30', focusBorder: 'focus:border-rose-500/50', ring: 'focus:ring-rose-500/50', glow: 'shadow-[0_0_15px_rgba(244,63,94,0.15)]' },
      amber: { text: 'text-amber-500', textLight: 'text-amber-400', bg: 'bg-amber-600', hoverBg: 'hover:bg-amber-500', border: 'border-amber-500/30', focusBorder: 'focus:border-amber-500/50', ring: 'focus:ring-amber-500/50', glow: 'shadow-[0_0_15px_rgba(245,158,11,0.15)]' }
    }
    return colors[primaryColor.value] || colors.blue
  })

  const glassClass = computed(() => {
    return useGlassmorphism.value 
      ? 'backdrop-blur-md bg-neutral-900/75' 
      : 'bg-neutral-900 border-neutral-800'
  })

  return {
    isSettingsModalOpen,
    primaryColor,
    useGlassmorphism,
    theme,
    glassClass,
    openSettingsModal, // <-- Exportamos as novas
    closeSettingsModal, // <-- Exportamos as novas
    setPrimaryColor,
    toggleGlassmorphism
  }
})