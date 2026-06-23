<script setup>
import { computed } from 'vue'
import { Play, Pause, SkipBack, SkipForward, Repeat, Shuffle, Volume2, VolumeX, Music } from 'lucide-vue-next'
import { usePlayerStore } from '../stores/playerStore'

const playerStore = usePlayerStore()

// Formata os segundos para mm:ss
const formatTime = (time) => {
  if (isNaN(time)) return '0:00'
  const minutes = Math.floor(time / 60)
  const seconds = Math.floor(time % 60)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

// Calcula a percentagem da barra de progresso (0 a 100)
const progressPercentage = computed(() => {
  if (!playerStore.duration) return 0
  return (playerStore.currentTime / playerStore.duration) * 100
})

// Funções para quando o utilizador arrasta a barra
const onSeekStart = () => {
  playerStore.isSeeking = true
}

const onSeekInput = (event) => {
  // Atualiza apenas o número visual enquanto arrasta
  playerStore.currentTime = Number(event.target.value)
}

const onSeekEnd = (event) => {
  playerStore.isSeeking = false
  playerStore.setSeek(Number(event.target.value))
}

const updateVolume = (event) => {
  playerStore.setVolume(Number(event.target.value))
}
</script>

<template>
  <div class="h-24 bg-neutral-900 border-t border-neutral-800 px-6 flex items-center justify-between select-none shadow-[0_-10px_30px_rgba(0,0,0,0.3)]">
    
    <div class="flex items-center gap-4 w-1/4 min-w-[200px]">
      <div v-if="playerStore.currentTrack" class="flex items-center gap-4 group cursor-pointer">
        <img 
          :src="playerStore.currentTrack.cover_url || 'https://placehold.co/100x100/262626/888?text=🎵'" 
          @error="$event.target.src = 'https://placehold.co/100x100/262626/888?text=🎵'"
          alt="Capa" 
          class="w-14 h-14 rounded-md object-cover shadow-lg bg-neutral-800" 
        />
        <div class="overflow-hidden">
          <h4 class="font-bold text-sm text-white truncate group-hover:underline">
            {{ playerStore.currentTrack.title }}
          </h4>
          <p class="text-xs text-neutral-400 truncate mt-0.5">{{ playerStore.currentTrack.artist }}</p>
        </div>
      </div>
      <div v-else class="flex items-center gap-4 opacity-40">
        <div class="w-14 h-14 rounded-md bg-neutral-800 flex items-center justify-center">
          <Music class="w-6 h-6 text-neutral-500" />
        </div>
        <div>
          <h4 class="font-bold text-sm text-white">Nenhuma faixa</h4>
          <p class="text-xs text-neutral-400">Selecione uma música</p>
        </div>
      </div>
    </div>

    <div class="flex flex-col items-center flex-1 max-w-2xl px-8">
      
      <div class="flex items-center gap-6 mb-2">
        <button 
          @click="playerStore.toggleShuffle" 
          :class="playerStore.isShuffle ? 'text-blue-500 hover:text-blue-400' : 'text-neutral-400 hover:text-white'"
          class="transition-colors"
          title="Modo Aleatório"
        >
          <Shuffle class="w-4 h-4" />
        </button>

        <button @click="playerStore.prevTrack" class="text-neutral-400 hover:text-white transition-colors" title="Música Anterior">
          <SkipBack class="w-5 h-5 fill-current" />
        </button>

        <button 
          @click="playerStore.playTrack(playerStore.currentTrack)"
          :disabled="!playerStore.currentTrack"
          class="w-10 h-10 rounded-full bg-white text-black flex items-center justify-center hover:scale-105 transition-transform disabled:opacity-50 disabled:hover:scale-100 disabled:cursor-not-allowed"
          title="Tocar/Pausar"
        >
          <Pause v-if="playerStore.isPlaying" class="w-5 h-5 fill-current" />
          <Play v-else class="w-5 h-5 fill-current ml-1" />
        </button>

        <button @click="playerStore.nextTrack" class="text-neutral-400 hover:text-white transition-colors" title="Próxima Música">
          <SkipForward class="w-5 h-5 fill-current" />
        </button>

        <button 
          @click="playerStore.toggleLoop" 
          :class="playerStore.loopMode > 0 ? 'text-blue-500 hover:text-blue-400' : 'text-neutral-400 hover:text-white'"
          class="transition-colors relative"
          title="Modo de Repetição"
        >
          <Repeat class="w-4 h-4" />
          <span v-if="playerStore.loopMode === 2" class="absolute -top-1.5 -right-1.5 text-[8px] font-bold bg-blue-600 text-white rounded-full w-3 h-3 flex items-center justify-center">1</span>
        </button>
      </div>

      <div class="w-full flex items-center gap-3 text-xs font-medium text-neutral-400">
        <span class="w-10 text-right">{{ formatTime(playerStore.currentTime) }}</span>
        
        <div class="relative flex-1 h-1.5 group flex items-center">
          <input 
            type="range" 
            min="0" 
            :max="playerStore.duration || 100" 
            :value="playerStore.currentTime"
            @mousedown="onSeekStart"
            @touchstart="onSeekStart"
            @input="onSeekInput"
            @change="onSeekEnd"
            @touchend="onSeekEnd"
            :disabled="!playerStore.currentTrack"
            class="absolute z-20 w-full h-full opacity-0 cursor-pointer disabled:cursor-not-allowed"
          />
          
          <div class="absolute w-full h-full bg-neutral-700 rounded-full"></div>
          
          <div 
            class="absolute h-full bg-white group-hover:bg-blue-500 rounded-full transition-colors pointer-events-none"
            :style="{ width: `${progressPercentage}%` }"
          ></div>

          <div 
            class="absolute w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none -ml-1.5 shadow"
            :style="{ left: `${progressPercentage}%` }"
          ></div>
        </div>
        
        <span class="w-10">{{ formatTime(playerStore.duration) }}</span>
      </div>
    </div>

    <div class="flex items-center gap-3 w-1/4 justify-end min-w-[150px]">
      <button @click="playerStore.toggleMute" class="text-neutral-400 hover:text-white transition-colors focus:outline-none">
        <VolumeX v-if="playerStore.volume === 0" class="w-4 h-4" />
        <Volume2 v-else class="w-4 h-4" />
      </button>

      <div class="w-24 relative flex items-center group h-1.5">
        <input 
          type="range" 
          min="0" 
          max="1" 
          step="0.01" 
          :value="playerStore.volume"
          @input="updateVolume"
          class="absolute z-20 w-full h-full opacity-0 cursor-pointer"
        />
        
        <div class="absolute w-full h-full bg-neutral-700 rounded-full"></div>
        
        <div 
          class="absolute h-full bg-white group-hover:bg-blue-500 rounded-full transition-colors pointer-events-none"
          :style="{ width: `${playerStore.volume * 100}%` }"
        ></div>

        <div 
          class="absolute w-3 h-3 bg-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none -ml-1.5 shadow"
          :style="{ left: `${playerStore.volume * 100}%` }"
        ></div>
      </div>
    </div>

  </div>
</template>