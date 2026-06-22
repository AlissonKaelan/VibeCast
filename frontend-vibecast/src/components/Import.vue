<script setup>
import { ref } from 'vue'
import { usePlayerStore } from '../stores/playerStore'
import { X, Music, Youtube, FolderDot, ArrowLeft } from 'lucide-vue-next' // Ajuste os ícones conforme o que você tem

const playerStore = usePlayerStore()

// null = Mostra as opções | 'spotify' = Mostra o input | 'youtube' | 'local'
const selectedSource = ref(null) 
const playlistUrl = ref('')
const isLoading = ref(false)

const importPlaylist = async () => {
  if (!playlistUrl.value) {
    playerStore.notify('Cole um link válido!', 'error')
    return
  }

  isLoading.value = true
  try {
    const response = await fetch('http://localhost:8000/api/import-playlist', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: playlistUrl.value })
    })

    const data = await response.json()
    if (response.ok) {
      playerStore.notify(data.message, 'success')
      playerStore.loadLibrary() 
      closeModal() // Fecha a janela após o sucesso
    } else {
      playerStore.notify(data.error || 'Erro na importação', 'error')
    }
  } catch (error) {
    playerStore.notify('Erro ao conectar com o servidor.', 'error')
  } finally {
    isLoading.value = false
    playlistUrl.value = ''
  }
}

const closeModal = () => {
  playerStore.closeImportModal()
  setTimeout(() => { selectedSource.value = null }, 300) // Reseta a tela ao fechar
}
</script>

<template>
  <div 
    v-if="playerStore.isImportModalOpen"
    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity"
    @click.self="closeModal"
  >
    <div class="bg-neutral-900 border border-neutral-800 shadow-2xl rounded-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      
      <div class="flex items-center justify-between p-6 border-b border-neutral-800">
        <div class="flex items-center gap-3">
          <button v-if="selectedSource" @click="selectedSource = null" class="text-neutral-400 hover:text-white transition-colors">
            <ArrowLeft class="w-5 h-5" />
          </button>
          <h2 class="text-xl font-bold text-white">Importar Músicas</h2>
        </div>
        <button @click="closeModal" class="text-neutral-400 hover:text-red-500 transition-colors rounded-full p-1 hover:bg-neutral-800">
          <X class="w-6 h-6" />
        </button>
      </div>

      <div v-if="!selectedSource" class="p-8">
        <p class="text-neutral-400 mb-6 text-center">Escolha a origem das músicas que deseja trazer para o VibeCast.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <button @click="selectedSource = 'spotify'" class="flex flex-col items-center gap-4 p-6 rounded-xl border border-neutral-800 bg-neutral-800/30 hover:bg-green-500/10 hover:border-green-500/50 transition-all group">
            <div class="w-16 h-16 rounded-full bg-green-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Music class="w-8 h-8 text-green-500" />
            </div>
            <span class="font-bold text-white">Spotify</span>
          </button>

          <button disabled class="flex flex-col items-center gap-4 p-6 rounded-xl border border-neutral-800 bg-neutral-800/10 opacity-50 cursor-not-allowed">
            <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center">
              <Youtube class="w-8 h-8 text-red-500" />
            </div>
            <span class="font-bold text-white">YouTube</span>
            <span class="text-[10px] bg-neutral-700 px-2 py-1 rounded text-white font-bold uppercase tracking-wider absolute mt-28">Em breve</span>
          </button>

          <button disabled class="flex flex-col items-center gap-4 p-6 rounded-xl border border-neutral-800 bg-neutral-800/10 opacity-50 cursor-not-allowed">
            <div class="w-16 h-16 rounded-full bg-blue-500/20 flex items-center justify-center">
              <FolderDot class="w-8 h-8 text-blue-500" />
            </div>
            <span class="font-bold text-white">PC Local</span>
            <span class="text-[10px] bg-neutral-700 px-2 py-1 rounded text-white font-bold uppercase tracking-wider absolute mt-28">Em breve</span>
          </button>
        </div>
      </div>

      <div v-else-if="selectedSource === 'spotify'" class="p-8">
        <div class="flex flex-col gap-6">
          <div class="flex items-center gap-4 text-green-400 bg-green-500/10 p-4 rounded-lg border border-green-500/20">
            <Music class="w-6 h-6" />
            <p class="text-sm font-medium text-green-100">Cole o link público de qualquer Playlist do Spotify abaixo.</p>
          </div>

          <div class="flex gap-3">
            <input 
              v-model="playlistUrl" 
              type="text" 
              placeholder="Ex: https://open.spotify.com/playlist/..." 
              class="flex-1 bg-black/50 border border-neutral-700 text-white rounded-lg px-4 py-3 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-all"
              @keyup.enter="importPlaylist"
            >
            <button 
              @click="importPlaylist" 
              :disabled="isLoading"
              class="bg-green-600 hover:bg-green-500 text-white px-8 py-3 rounded-lg font-bold transition-all disabled:opacity-50 disabled:cursor-not-allowed min-w-[140px]"
            >
              <span v-if="!isLoading">Importar</span>
              <span v-else class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Lendo...
              </span>
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>