<script setup>
import { usePlayerStore } from '../stores/playerStore'
// Novos ícones importados: Shuffle, Repeat, Repeat1 (Para repetir a mesma música)
import { Play, Pause, Volume2, VolumeX, SkipBack, SkipForward, Shuffle, Repeat, Repeat1 } from 'lucide-vue-next'

const playerStore = usePlayerStore()

const formatTime = (time) => {
  if (!time || isNaN(time)) return '0:00'
  const minutes = Math.floor(time / 60)
  const seconds = Math.floor(time % 60)
  return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`
}
const updateVolume = (event) => { playerStore.setVolume(Number(event.target.value)) }
</script>

<template>
  <div 
    v-if="playerStore.currentTrack" 
    class="w-full h-full bg-neutral-950/95 backdrop-blur-md flex items-center justify-between px-6 shadow-[0_-10px_30px_rgba(0,0,0,0.6)]"
  >
    <div class="flex items-center gap-4 w-1/4 min-w-[180px]">
      <img 
        :src="playerStore.currentTrack.cover_url || 'https://images.unsplash.com/photo-1614680376593-902f74fa0d41?w=100&auto=format&fit=crop'" 
        class="w-14 h-14 rounded-md object-cover shadow" 
      />
      <div class="overflow-hidden">
        <h4 class="font-bold text-sm truncate text-white">{{ playerStore.currentTrack.title }}</h4>
        <p class="text-xs text-neutral-400 truncate mt-0.5">{{ playerStore.currentTrack.artist }}</p>
      </div>
    </div>

    <div class="flex flex-col items-center gap-2 flex-1 max-w-xl">
      <div class="flex items-center gap-6">
        
        <button 
          @click="playerStore.toggleShuffle" 
          :class="playerStore.isShuffle ? 'text-blue-500 hover:text-blue-400' : 'text-neutral-400 hover:text-white'" 
          class="transition-colors"
          title="Aleatório"
        >
          <Shuffle class="w-4 h-4" />
        </button>

        <button @click="playerStore.prevTrack" class="text-neutral-400 hover:text-white transition-colors">
          <SkipBack class="w-5 h-5 fill-current" />
        </button>
        
        <button 
          @click="playerStore.playTrack(playerStore.currentTrack)" 
          class="w-9 h-9 rounded-full bg-white text-black flex items-center justify-center hover:scale-105 transition-transform shadow-md"
        >
          <Pause v-if="playerStore.isPlaying" class="w-4 h-4 fill-black" />
          <Play v-else class="w-4 h-4 fill-black ml-0.5" />
        </button>

        <button @click="playerStore.nextTrack" class="text-neutral-400 hover:text-white transition-colors">
          <SkipForward class="w-5 h-5 fill-current" />
        </button>

        <button 
          @click="playerStore.toggleLoop" 
          :class="playerStore.loopMode > 0 ? 'text-blue-500 hover:text-blue-400' : 'text-neutral-400 hover:text-white'" 
          class="transition-colors"
          title="Modo de Repetição"
        >
          <Repeat1 v-if="playerStore.loopMode === 2" class="w-4 h-4" />
          <Repeat v-else class="w-4 h-4" />
        </button>

      </div>

      <div class="flex items-center gap-2 w-full mt-1">
        <span class="text-[10px] text-neutral-400 font-mono w-8 text-right">{{ formatTime(playerStore.currentTime) }}</span>
        <input 
          type="range" 
          min="0" 
          :max="playerStore.duration || 100" 
          step="1"
          :value="playerStore.currentTime"
          @mousedown="playerStore.isSeeking = true" 
          @touchstart="playerStore.isSeeking = true" 
          @input="playerStore.currentTime = Number($event.target.value)" 
          @change="playerStore.setSeek($event.target.value); playerStore.isSeeking = false" 
          class="w-full h-1 bg-neutral-700 rounded-lg appearance-none cursor-pointer hover:h-1.5 transition-all accent-blue-500"
        />
        <span class="text-[10px] text-neutral-400 font-mono w-8">{{ formatTime(playerStore.duration) }}</span>
      </div>
    </div>

    <div class="flex items-center justify-end gap-2 w-1/4 min-w-[120px]">
      <button @click="playerStore.toggleMute" class="text-neutral-400 hover:text-white transition-colors">
        <VolumeX v-if="playerStore.volume == 0" class="w-4 h-4" />
        <Volume2 v-else class="w-4 h-4" />
      </button>
      <input 
        type="range" min="0" max="1" step="0.01" :value="playerStore.volume" @input="updateVolume"
        class="w-20 h-1 bg-neutral-700 rounded-lg appearance-none cursor-pointer accent-white hover:accent-blue-500"
      />
    </div>
  </div>
</template>