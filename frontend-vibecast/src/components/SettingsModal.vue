<script setup>
import { X, Palette, Eye } from 'lucide-vue-next'
import { useSettingsStore } from '../stores/settingsStore'

const settingsStore = useSettingsStore()

const colorOptions = [
  { id: 'blue', name: 'Azul Vibe', class: ':class="settingsStore.theme.bg"' },
  { id: 'purple', name: 'Roxo Sunset', class: 'bg-purple-500 shadow-purple-500/30' },
  { id: 'emerald', name: 'Verde Cyber', class: 'bg-emerald-500 shadow-emerald-500/30' },
  { id: 'rose', name: 'Rosa Pop', class: 'bg-rose-500 shadow-rose-500/30' },
  { id: 'amber', name: 'Ouro Neon', class: 'bg-amber-500 shadow-amber-500/30' }
]
</script>

<template>
  <div v-if="settingsStore.isSettingsModalOpen" @click.self="settingsStore.closeSettingsModal()" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity">
    <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-transform scale-100 animate-slide-up">
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-bold text-white flex items-center gap-2">
          <Palette class="w-5 h-5" :class="settingsStore.theme.text" /> Personalização
        </h3>
        <button @click="settingsStore.closeSettingsModal()" class="text-neutral-400 hover:text-white transition-colors"><X class="w-5 h-5" /></button>
      </div>

      <div class="mb-6">
        <label class="block text-xs font-bold text-neutral-500 uppercase tracking-wider mb-3">Cor de Destaque</label>
        <div class="flex flex-wrap gap-3">
          <button v-for="color in colorOptions" :key="color.id" @click="settingsStore.setPrimaryColor(color.id)" class="w-10 h-10 rounded-full transition-all flex items-center justify-center relative hover:scale-110 shadow-lg" :class="[color.class, settingsStore.primaryColor === color.id ? 'ring-4 ring-white' : '']" :title="color.name">
            <svg v-if="settingsStore.primaryColor === color.id" class="w-5 h-5 text-neutral-900 font-bold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
          </button>
        </div>
      </div>

      <div class="border-t border-neutral-800/60 pt-4 mb-4">
        <div class="flex items-center justify-between">
          <div class="flex flex-col gap-0.5">
            <span class="text-sm font-bold text-white flex items-center gap-2"><Eye class="w-4 h-4 text-neutral-400" />Efeito Vidro Fosco</span>
            <span class="text-xs text-neutral-500">Transparência nas barras e painéis.</span>
          </div>
          <button @click="settingsStore.toggleGlassmorphism()" class="w-12 h-6 flex items-center rounded-full p-1 transition-colors duration-300 focus:outline-none" :class="settingsStore.useGlassmorphism ? settingsStore.theme.bg : 'bg-neutral-800'">
            <div class="bg-white w-4 h-4 rounded-full shadow-md transform transition-transform duration-300" :class="settingsStore.useGlassmorphism ? 'translate-x-6' : 'translate-x-0'"></div>
          </button>
        </div>
      </div>

      <button @click="settingsStore.closeSettingsModal()" class="w-full mt-4 bg-neutral-800 hover:bg-neutral-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm">Concluído</button>
    </div>
  </div>
</template>

<style scoped>
@keyframes slideUp { from { opacity: 0; transform: translateY(10px); scale: 0.95; } to { opacity: 1; transform: translateY(0); scale: 1; } }
.animate-slide-up { animation: slideUp 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
</style>