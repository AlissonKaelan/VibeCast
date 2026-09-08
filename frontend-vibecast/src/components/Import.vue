<script setup>
import { ref } from 'vue'
import { usePlayerStore } from '../stores/playerStore'
import { X, Music, Youtube, FolderDot, ArrowLeft, Cloud } from 'lucide-vue-next'

const playerStore = usePlayerStore()

// null = Mostra as opções | 'spotify', 'soundcloud', 'youtube' ou 'local'
const selectedSource = ref(null) 
const playlistUrl = ref('')
const isLoading = ref(false)

const isDragging = ref(false)
const fileInput = ref(null)

// 1. Processa Importações via Link (Spotify, YouTube, SoundCloud)
const processImport = async () => {
  if (!playlistUrl.value) {
    playerStore.notify('Cole um link válido!', 'error')
    return
  }

  isLoading.value = true
  
  let endpoint = 'http://localhost:8000/api/import-playlist' // Padrão (Spotify)
  
  if (selectedSource.value === 'soundcloud') {
    endpoint = 'http://localhost:8000/api/import-soundcloud'
  } else if (selectedSource.value === 'youtube') {
    endpoint = 'http://localhost:8000/api/import/youtube'
  }

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: playlistUrl.value })
    })

    const data = await response.json()
    if (response.ok) {
      playerStore.notify(data.message, 'success')
      playerStore.loadLibrary() 
      closeModal()
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

// 2. Processa Importações Físicas (Drag & Drop ou Clique)
const handleFiles = async (event) => {
  isDragging.value = false
  const files = event.dataTransfer ? event.dataTransfer.files : event.target.files
  
  if (!files || files.length === 0) return

  const formData = new FormData()
  let audioCount = 0

  for (let i = 0; i < files.length; i++) {
    if (files[i].type.startsWith('audio/') || files[i].name.match(/\.(mp3|m4a|wav|flac)$/i)) {
      formData.append('files[]', files[i])
      audioCount++
    }
  }

  if (audioCount === 0) {
    playerStore.notify('Nenhum arquivo de áudio suportado foi solto.', 'error')
    return
  }

  isLoading.value = true
  playerStore.notify(`Enviando ${audioCount} música(s)...`, 'success')

  try {
    const response = await fetch('http://localhost:8000/api/import/web-upload', {
      method: 'POST',
      body: formData
    })
    
    const data = await response.json()
    if (data.success) {
      playerStore.notify(data.message, 'success')
      playerStore.loadAllTracks()
      closeModal()
    } else {
      playerStore.notify(data.error || 'Erro ao importar arquivos', 'error')
    }
  } catch (error) {
    playerStore.notify('Falha ao enviar os arquivos para o servidor.', 'error')
  } finally {
    isLoading.value = false
    if (fileInput.value) fileInput.value.value = '' // Reseta o input invisível
  }
}

const closeModal = () => {
  playerStore.closeImportModal()
  setTimeout(() => { selectedSource.value = null; playlistUrl.value = '' }, 300) 
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
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button @click="selectedSource = 'spotify'" class="flex flex-col items-center gap-4 p-6 rounded-xl border border-neutral-800 bg-neutral-800/30 hover:bg-green-500/10 hover:border-green-500/50 transition-all group">
            <div class="w-16 h-16 rounded-full bg-green-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Music class="w-8 h-8 text-green-500" />
            </div>
            <span class="font-bold text-white">Spotify</span>
          </button>

          <button @click="selectedSource = 'soundcloud'" class="flex flex-col items-center gap-4 p-6 rounded-xl border border-neutral-800 bg-neutral-800/30 hover:bg-orange-500/10 hover:border-orange-500/50 transition-all group">
            <div class="w-16 h-16 rounded-full bg-orange-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Cloud class="w-8 h-8 text-orange-500" />
            </div>
            <span class="font-bold text-white">SoundCloud</span>
          </button>

          <button @click="selectedSource = 'youtube'" class="flex flex-col items-center gap-4 p-6 rounded-xl border border-neutral-800 bg-neutral-800/30 hover:bg-red-500/10 hover:border-red-500/50 transition-all group">
            <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Youtube class="w-8 h-8 text-red-500" />
            </div>
            <span class="font-bold text-white">YouTube</span>
          </button>

          <button @click="selectedSource = 'local'" class="flex flex-col items-center gap-4 p-6 rounded-xl border border-neutral-800 bg-neutral-800/30 hover:bg-blue-500/10 hover:border-blue-500/50 transition-all group">
            <div class="w-16 h-16 rounded-full bg-blue-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
              <FolderDot class="w-8 h-8 text-blue-500" />
            </div>
            <span class="font-bold text-white">PC Local</span>
          </button>
        </div>
      </div>

      <div v-else class="p-8">
        <div class="flex flex-col gap-6">
          
          <div v-if="selectedSource === 'spotify'" class="flex items-center gap-4 text-green-400 bg-green-500/10 p-4 rounded-lg border border-green-500/20">
            <Music class="w-6 h-6" />
            <p class="text-sm font-medium text-green-100">Cole o link público de qualquer Playlist ou Música do Spotify.</p>
          </div>

          <div v-if="selectedSource === 'soundcloud'" class="flex items-center gap-4 text-orange-400 bg-orange-500/10 p-4 rounded-lg border border-orange-500/20">
            <Cloud class="w-6 h-6" />
            <p class="text-sm font-medium text-orange-100">Cole o link de uma Música ou Set do SoundCloud abaixo.</p>
          </div>

          <div v-if="selectedSource === 'youtube'" class="flex items-center gap-4 text-red-400 bg-red-500/10 p-4 rounded-lg border border-red-500/20">
            <Youtube class="w-6 h-6" />
            <p class="text-sm font-medium text-red-100">Cole o link de um Vídeo ou Playlist do YouTube.</p>
          </div>

          <div v-if="selectedSource === 'local'" class="flex items-center gap-4 text-blue-400 bg-blue-500/10 p-4 rounded-lg border border-blue-500/20">
            <FolderDot class="w-6 h-6" />
            <p class="text-sm font-medium text-blue-100">Arraste seus arquivos de áudio direto do computador.</p>
          </div>

          <div v-if="selectedSource === 'local'">
            <div 
              @dragover.prevent="isDragging = true" 
              @dragleave.prevent="isDragging = false" 
              @drop.prevent="handleFiles"
              @click="$refs.fileInput.click()"
              :class="isDragging ? 'border-blue-500 bg-blue-500/10' : 'border-neutral-700 bg-black/50'"
              class="border-2 border-dashed rounded-xl p-10 flex flex-col items-center justify-center transition-all cursor-pointer hover:border-neutral-500 relative"
            >
              <input type="file" ref="fileInput" multiple accept="audio/*,.mp3,.m4a,.wav,.flac" class="hidden" @change="handleFiles" />
              
              <div v-if="isLoading" class="flex flex-col items-center gap-4">
                <svg class="animate-spin h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span class="text-white font-bold">Processando arquivos e lendo nomes...</span>
              </div>
              <div v-else class="flex flex-col items-center">
                <div class="w-16 h-16 bg-neutral-800 rounded-full flex items-center justify-center mb-4 transition-colors">
                  <span class="text-3xl">📥</span>
                </div>
                <h3 class="text-white font-bold mb-1 text-lg">Solte suas músicas aqui</h3>
                <p class="text-neutral-400 text-sm mb-4">Ou clique para procurar as pastas no seu PC</p>
                <p class="text-neutral-500 text-xs">Suporta .mp3, .m4a, .wav, .flac</p>
              </div>
            </div>
          </div>

          <div v-else class="flex gap-3">
            <input 
              v-model="playlistUrl" 
              type="text" 
              :placeholder="selectedSource === 'youtube' ? 'Ex: https://www.youtube.com/watch?v=...' : (selectedSource === 'spotify' ? 'Ex: https://open.spotify.com/...' : 'Ex: https://soundcloud.com/...')" 
              class="flex-1 bg-black/50 border border-neutral-700 text-white rounded-lg px-4 py-3 focus:outline-none transition-all focus:ring-1"
              :class="{
                'focus:border-green-500 focus:ring-green-500': selectedSource === 'spotify', 
                'focus:border-orange-500 focus:ring-orange-500': selectedSource === 'soundcloud',
                'focus:border-red-500 focus:ring-red-500': selectedSource === 'youtube'
              }"
              @keyup.enter="processImport"
            >
            <button 
              @click="processImport" 
              :disabled="isLoading"
              class="text-white px-8 py-3 rounded-lg font-bold transition-all disabled:opacity-50 disabled:cursor-not-allowed min-w-[140px]"
              :class="{
                'bg-green-600 hover:bg-green-500': selectedSource === 'spotify', 
                'bg-orange-600 hover:bg-orange-500': selectedSource === 'soundcloud',
                'bg-red-600 hover:bg-red-500': selectedSource === 'youtube'
              }"
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