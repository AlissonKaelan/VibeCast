<script setup>
import { ref } from 'vue'
import { Download, Loader2, Music, Play, Pause } from 'lucide-vue-next'

// Variáveis exclusivas da lista de músicas
import { usePlayerStore } from '../stores/playerStore'
const playerStore = usePlayerStore()
const downloadingTracks = ref({})
const currentPlaylistId = ref(null)
const isDownloadingAll = ref(false)
let pollingInterval = null

// Variáveis do Player que precisaremos puxar do Cérebro Global depois
const currentTrack = ref(null)
const isPlaying = ref(false)

const downloadAudio = async (trackId) => {
  downloadingTracks.value[trackId] = true
  try {
    const response = await fetch(`http://localhost:8000/api/tracks/${trackId}/download`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' }
    })
    const data = await response.json()
    if (!response.ok) throw new Error(data.error || 'Erro no download')

    const trackIndex = tracks.value.findIndex(t => t.id === trackId)
    if (trackIndex !== -1) {
      tracks.value[trackIndex].file_path = data.file_path
    }
  } catch (error) {
    alert("Ops! " + error.message)
  } finally {
    downloadingTracks.value[trackId] = false
  }
}

const downloadAll = async () => {
  if (!currentPlaylistId.value) return
  isDownloadingAll.value = true
  try {
    await fetch(`http://localhost:8000/api/playlists/${currentPlaylistId.value}/download-all`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' }
    })
    startPolling()
  } catch (error) {
    alert("Erro ao iniciar download: " + error.message)
    isDownloadingAll.value = false
  }
}

const startPolling = () => {
  if (pollingInterval) clearInterval(pollingInterval)
  pollingInterval = setInterval(async () => {
    try {
      const response = await fetch(`http://localhost:8000/api/playlists/${currentPlaylistId.value}`)
      const data = await response.json()
      tracks.value = data.tracks
      if (data.tracks.every(t => t.file_path)) {
        clearInterval(pollingInterval)
        isDownloadingAll.value = false
      }
    } catch (error) {
      console.error(error)
    }
  }, 3000)
}

// O playTrack virá do nosso Store Global em breve
const playTrack = (track) => {
  console.log("Música clicada:", track.title)
}
</script>

<template>
  <div v-if="playerStore.tracks.length > 0" class="w-full mt-12 px-4">
    <div class="mb-6 border-b border-neutral-700 pb-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
        <h3 class="text-2xl font-bold">Faixas Musicais</h3>
        
        <button 
          @click="downloadAll"
          :disabled="isDownloadingAll"
          class="bg-white text-black font-bold py-2 px-6 rounded-full hover:scale-105 transition-transform disabled:opacity-50 disabled:hover:scale-100 flex items-center gap-2 text-sm shadow-md"
        >
          <Loader2 v-if="isDownloadingAll" class="w-4 h-4 animate-spin" />
          <Download v-else class="w-4 h-4" />
          {{ isDownloadingAll ? 'Baixando em Segundo Plano...' : 'Baixar Todas' }}
        </button>
      </div>

      <div v-if="isDownloadingAll" class="w-full bg-neutral-800 rounded-full h-2 overflow-hidden border border-neutral-700">
        <div 
          class="bg-blue-500 h-2 rounded-full transition-all duration-500 ease-out" 
          :style="{ width: `${(playerStore.tracks.filter(t => t.file_path).length / playerStore.tracks.length) * 100}%` }"
        ></div>
      </div>
      <p v-if="isDownloadingAll" class="text-xs text-neutral-400 mt-1 text-right">
        {{ tracks.filter(t => t.file_path).length }} de {{ tracks.length }} salvas localmente
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="(track, index) in playerStore.tracks" :key="track.id"
        class="bg-neutral-800 p-4 rounded-xl flex items-center gap-4 hover:bg-neutral-700 transition-colors border border-neutral-700/50 group"
        :class="playerStore.currentTrack?.id === track.id ? 'border-blue-500/60 bg-neutral-700/40' : ''"
      >
        <img v-if="track.cover_url" :src="track.cover_url" alt="Capa" class="w-14 h-14 rounded-md object-cover shadow-md" />
        <div v-else class="w-14 h-14 rounded-md bg-neutral-700 flex items-center justify-center shadow-md">
          <Music class="w-5 h-5 text-neutral-400" />
        </div>

        <div class="flex-1 overflow-hidden">
          <h4 class="font-bold text-sm text-white truncate" :class="currentTrack?.id === track.id ? 'text-blue-400' : ''">
            {{ track.title }}
          </h4>
          <p class="text-xs text-neutral-400 truncate mt-0.5">{{ track.artist }}</p>
        </div>

        <div class="flex gap-2">
          <button 
            v-if="!track.file_path"
            @click="downloadAudio(track.id)"
            :disabled="downloadingTracks[track.id]"
            class="w-9 h-9 rounded-full bg-neutral-700 hover:bg-neutral-600 text-white flex items-center justify-center transition-all disabled:opacity-50"
            title="Baixar para o PC"
          >
            <Loader2 v-if="downloadingTracks[track.id]" class="w-4 h-4 animate-spin" />
            <Download v-else class="w-4 h-4" />
          </button>

          <button 
            v-if="track.file_path"
            @click="playerStore.playTrack(track)"
            class="w-9 h-9 rounded-full bg-blue-500 text-black flex items-center justify-center transition-transform hover:scale-105 shadow-[0_0_10px_rgba(34,197,94,0.3)]"
          >
            <Pause v-if="currentTrack?.id === track.id && isPlaying" class="w-4 h-4 fill-black" />
            <Play v-else class="w-4 h-4 fill-black ml-0.5" /> 
          </button>
        </div>
      </div>
    </div>
  </div>
</template>