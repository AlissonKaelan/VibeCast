<script setup>
import { ref, onMounted } from 'vue'
import { Music, Library, ListMusic } from 'lucide-vue-next'
import { usePlayerStore } from '../stores/playerStore' // Importa o cérebro global

// Instancia o store para podermos mandar dados para ele
const playerStore = usePlayerStore()

const savedPlaylists = ref([])
const currentPlaylistId = ref(null)

// Carrega as playlists salvas assim que o menu lateral aparece na tela
const loadLibrary = async () => {
  try {
    const response = await fetch('http://localhost:8000/api/playlists')
    const data = await response.json()
    savedPlaylists.value = data.playlists
  } catch (error) {
    console.error("Erro ao carregar biblioteca:", error)
  }
}

// Abre uma playlist e joga as músicas dela dentro do Store Global!
const selectPlaylist = async (playlistId) => {
  currentPlaylistId.value = playlistId
  try {
    const response = await fetch(`http://localhost:8000/api/playlists/${playlistId}`)
    const data = await response.json()
    
    // MÁGICA DA ARQUITETURA:
    // Salvamos na store global. A tela central (TrackList) vai ler isso automaticamente e se atualizar!
    playerStore.tracks = data.tracks 
  } catch (error) {
    console.error("Erro ao carregar músicas da playlist:", error)
  }
}

// Executa a carga inicial automática ao montar o componente
onMounted(() => {
  loadLibrary()
})
</script>

<template>
  <div class="h-full bg-neutral-950 p-4 flex flex-col gap-6 select-none">
    <div class="flex items-center gap-2 px-2">
      <Library class="w-6 h-6 text-blue-500" />
      <span class="font-extrabold text-lg tracking-tight">Sua Biblioteca</span>
    </div>

    <div class="flex-1 overflow-y-auto flex flex-col gap-2 invisible-scrollbar">
      <div v-if="savedPlaylists.length === 0" class="text-neutral-500 text-xs px-2 py-4">
        Nenhuma playlist salva. Importe um link para começar!
      </div>

      <div 
        v-for="pl in savedPlaylists" 
        :key="pl.id"
        @click="selectPlaylist(pl.id)"
        class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-all group"
        :class="currentPlaylistId === pl.id ? 'bg-neutral-800 border border-neutral-700/50' : 'hover:bg-neutral-900'"
      >
        <div class="w-12 h-12 bg-neutral-800 rounded-md flex items-center justify-center shadow transition-colors group-hover:bg-neutral-700">
          <ListMusic class="w-5 h-5 text-neutral-400 group-hover:text-blue-400 transition-colors" />
        </div>
        
        <div class="flex-1 overflow-hidden">
          <h4 class="font-bold text-xs truncate text-white" :class="currentPlaylistId === pl.id ? 'text-blue-500' : ''">
            {{ pl.name }}
          </h4>
          <p class="text-[11px] text-neutral-400 mt-0.5">{{ pl.tracks_count }} faixas</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Remove a barra de rolagem visual mantendo o poder de scroll */
.invisible-scrollbar::-webkit-scrollbar {
  display: none;
}
.invisible-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>