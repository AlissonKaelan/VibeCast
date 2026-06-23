<script setup>
import { computed } from 'vue'
import { X, Music, Play, ListMusic } from 'lucide-vue-next'
import { usePlayerStore } from '../stores/playerStore'

const playerStore = usePlayerStore()

// Calcula apenas as músicas que vêm DEPOIS da música atual
const nextTracks = computed(() => {
  if (!playerStore.queue || playerStore.queue.length === 0) return []
  return playerStore.queue.slice(playerStore.currentIndex + 1)
})
</script>

<template>
  <div 
    v-if="playerStore.isQueueOpen" 
    @click="playerStore.toggleQueue" 
    class="fixed inset-0 bg-black/40 z-[120] lg:hidden transition-opacity backdrop-blur-sm"
  ></div>

  <div 
    class="fixed top-0 right-0 h-full w-full sm:w-80 bg-neutral-900 border-l border-neutral-800 z-[130] flex flex-col transition-transform duration-300 shadow-2xl"
    :class="playerStore.isQueueOpen ? 'translate-x-0' : 'translate-x-full'"
  >
    
    <div class="p-5 flex items-center justify-between border-b border-neutral-800">
      <div class="flex items-center gap-2">
        <ListMusic class="w-5 h-5 text-neutral-400" />
        <h3 class="font-bold text-lg text-white">Fila de Reprodução</h3>
      </div>
      <button 
        @click="playerStore.toggleQueue" 
        class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-neutral-800 text-neutral-400 hover:text-white transition-colors"
      >
        <X class="w-5 h-5" />
      </button>
    </div>

    <div class="flex-1 overflow-y-auto p-4 invisible-scrollbar flex flex-col gap-6 mb-24">
      
      <div v-if="playerStore.queue.length === 0" class="text-center text-neutral-500 mt-10 text-sm">
        A sua fila está vazia.<br>Dê play numa música!
      </div>

      <div v-else>
        <div>
          <h4 class="text-xs font-extrabold text-neutral-500 uppercase tracking-wider mb-3">A Tocar Agora</h4>
          <div 
            v-if="playerStore.currentTrack"
            class="flex items-center gap-3 p-2 rounded-xl bg-blue-900/10 border border-blue-500/30"
          >
            <img 
              :src="playerStore.currentTrack.cover_url || 'https://placehold.co/100x100/262626/888?text=🎵'" 
              @error="$event.target.src = 'https://placehold.co/100x100/262626/888?text=🎵'"
              class="w-12 h-12 rounded shadow-md object-cover" 
            />
            <div class="flex-1 overflow-hidden">
              <h5 class="text-sm font-bold text-blue-400 truncate">{{ playerStore.currentTrack.title }}</h5>
              <p class="text-xs text-neutral-400 truncate">{{ playerStore.currentTrack.artist }}</p>
            </div>
            <div class="w-6 h-6 flex items-center justify-center">
              <div class="flex gap-0.5 items-end h-3">
                <div class="w-0.5 bg-blue-500 animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="w-0.5 h-3 bg-blue-500 animate-bounce" style="animation-delay: 0.2s"></div>
                <div class="w-0.5 bg-blue-500 animate-bounce" style="animation-delay: 0.3s"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-6" v-if="nextTracks.length > 0">
          <h4 class="text-xs font-extrabold text-neutral-500 uppercase tracking-wider mb-3 flex items-center justify-between">
            <span>A Seguir</span>
            <span class="text-[10px] bg-neutral-800 px-2 py-0.5 rounded-full">{{ nextTracks.length }}</span>
          </h4>
          
          <div class="flex flex-col gap-1">
            <div 
              v-for="(track, index) in nextTracks" 
              :key="track.id + '-' + index"
              @click="playerStore.jumpToQueueIndex(playerStore.currentIndex + 1 + index)"
              class="flex items-center gap-3 p-2 rounded-lg hover:bg-neutral-800 transition-colors cursor-pointer group"
            >
              <div class="relative w-10 h-10 flex-shrink-0">
                <img 
                  :src="track.cover_url || 'https://placehold.co/100x100/262626/888?text=🎵'" 
                  @error="$event.target.src = 'https://placehold.co/100x100/262626/888?text=🎵'"
                  class="w-10 h-10 rounded object-cover shadow-sm group-hover:opacity-50 transition-opacity" 
                />
                <Play class="absolute inset-0 m-auto w-4 h-4 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
              </div>
              <div class="flex-1 overflow-hidden">
                <h5 class="text-sm font-semibold text-neutral-200 group-hover:text-white truncate">{{ track.title }}</h5>
                <p class="text-[11px] text-neutral-500 truncate">{{ track.artist }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.invisible-scrollbar::-webkit-scrollbar { display: none; }
.invisible-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>