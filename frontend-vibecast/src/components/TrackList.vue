<script setup>
import { ref, computed, onMounted, watch } from 'vue' 
import { Download, Loader2, Music, Play, Pause, FolderPlus, X, DownloadCloud, Search, Pencil, Trash2 } from 'lucide-vue-next'
import { usePlayerStore } from '../stores/playerStore'
import { useSettingsStore } from '../stores/settingsStore'

const settingsStore = useSettingsStore()
const playerStore = usePlayerStore()

onMounted(() => {
  playerStore.loadAllTracks()
})

const showMoveModal = ref(false)
const trackToMove = ref(null)
const isMoving = ref(false)

// === VARIÁVEIS DE EDIÇÃO E EXCLUSÃO ===
const showEditModal = ref(false)
const trackToEdit = ref(null)
const isSavingEdit = ref(false)
const editForm = ref({ title: '', artist: '' })

const showDeleteModal = ref(false)
const trackToDelete = ref(null)
const isDeleting = ref(false)

// === CONTADOR INTELIGENTE DE DOWNLOADS ===
const downloadingTracks = ref({})
const activeDownloadIds = ref([]) 

const batchTotal = computed(() => activeDownloadIds.value.length)

const batchCompleted = computed(() => {
  // Conta quantas das músicas que estão na fila já receberam o arquivo (file_path)
  return activeDownloadIds.value.filter(id => {
    const track = playerStore.tracks.find(t => t.id === id)
    return track && track.file_path
  }).length
})

// A barra azul só aparece se tivermos músicas na fila e nem todas terminaram
const isDownloadingGlobal = computed(() => activeDownloadIds.value.length > 0 && batchCompleted.value < batchTotal.value)

// Limpa o contador automaticamente 3 segundos após finalizar, para a próxima vez começar do 0/1 de novo
watch(isDownloadingGlobal, (isDownloading) => {
  if (!isDownloading && activeDownloadIds.value.length > 0) {
    setTimeout(() => {
      activeDownloadIds.value = []
    }, 3000)
  }
})

const searchQuery = ref('')

const filteredTracks = computed(() => {
  if (!searchQuery.value.trim()) return playerStore.tracks

  const query = searchQuery.value.toLowerCase()
  return playerStore.tracks.filter(track => {
    const titleMatch = track.title?.toLowerCase().includes(query)
    const artistMatch = track.artist?.toLowerCase().includes(query)
    return titleMatch || artistMatch
  })
})

// === FUNÇÕES DE EDIÇÃO ===
const openEditModal = (track) => {
  trackToEdit.value = track
  editForm.value = { title: track.title, artist: track.artist || '' }
  showEditModal.value = true
}

const saveTrackEdit = async () => {
  if (!editForm.value.title.trim()) {
    playerStore.notify('O título não pode estar vazio!', 'error')
    return
  }
  
  isSavingEdit.value = true
  try {
    const response = await fetch(`http://localhost:8000/api/tracks/${trackToEdit.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(editForm.value)
    })

    if (!response.ok) throw new Error('Erro ao atualizar música')

    const index = playerStore.tracks.findIndex(t => t.id === trackToEdit.value.id)
    if (index !== -1) {
      playerStore.tracks[index].title = editForm.value.title
      playerStore.tracks[index].artist = editForm.value.artist
    }
    
    if (playerStore.currentTrack?.id === trackToEdit.value.id) {
      playerStore.currentTrack.title = editForm.value.title
      playerStore.currentTrack.artist = editForm.value.artist
    }

    playerStore.notify('Música atualizada!', 'success')
    showEditModal.value = false
  } catch (error) {
    playerStore.notify('Erro ao salvar as alterações', 'error')
  } finally {
    isSavingEdit.value = false
  }
}

// === FUNÇÕES DE EXCLUSÃO ===
const openDeleteModal = (track) => {
  trackToDelete.value = track
  showDeleteModal.value = true
}

const confirmDeleteTrack = async () => {
  if (!trackToDelete.value) return
  isDeleting.value = true

  try {
    const response = await fetch(`http://localhost:8000/api/tracks/${trackToDelete.value.id}`, {
      method: 'DELETE',
      headers: { 'Accept': 'application/json' }
    })

    if (!response.ok) throw new Error('Erro ao excluir música')

    playerStore.notify('Música excluída com sucesso!', 'success')
    playerStore.tracks = playerStore.tracks.filter(t => t.id !== trackToDelete.value.id)
    
    if (playerStore.currentPlaylistId) {
      playerStore.loadLibrary()
    }
    showDeleteModal.value = false
  } catch (error) {
    playerStore.notify('Erro ao excluir música', 'error')
  } finally {
    isDeleting.value = false
  }
}

// === DOWNLOAD & PLAYLISTS ===
const downloadAudio = async (trackId) => {
  downloadingTracks.value[trackId] = true 
  if (!activeDownloadIds.value.includes(trackId)) {
    activeDownloadIds.value.push(trackId)
  }
  
  try {
    const response = await fetch(`http://localhost:8000/api/tracks/${trackId}/download`, { 
      method: 'POST',
      headers: { 'Accept': 'application/json' }
    })
    
    const data = await response.json()

    if (response.ok) {
      playerStore.notify('Download adicionado à fila!', 'success')
      playerStore.startPlaylistStatusPolling(playerStore.currentPlaylistId)
    } else {
      downloadingTracks.value[trackId] = false
      
      // Intercepta o erro 503 de atualização
      if (response.status === 503 || data.error_type === 'updating') {
         playerStore.notify('Atualização em andamento. Tente novamente em 1 minuto.', 'error');
         // Opcional: pode até tirar o ID da activeDownloadIds aqui se quiser limpar a barra azul
      } else {
         throw new Error(data.message || 'Falha ao processar')
      }
    }
  } catch (error) {
    downloadingTracks.value[trackId] = false
    playerStore.notify(error.message || 'Erro ao enviar para fila', 'error')
  }
}

const downloadAll = async () => {
  if (!playerStore.currentPlaylistId) {
    playerStore.notify('Selecione uma playlist no menu primeiro!', 'error');
    return;
  }

  const tracksToDownload = playerStore.tracks.filter(t => !t.file_path)
  
  if (tracksToDownload.length === 0) {
    playerStore.notify('Todas as músicas já estão baixadas!', 'success')
    return
  }

  // Adiciona o lote todo ao contador inteligente
  const newIds = tracksToDownload.map(t => t.id)
  activeDownloadIds.value = [...new Set([...activeDownloadIds.value, ...newIds])]

  try {
    const response = await fetch(`http://localhost:8000/api/playlists/${playerStore.currentPlaylistId}/download`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' }
    });

    if (response.ok) {
      playerStore.notify('Downloads em lote adicionados à fila!', 'success');
      playerStore.startPlaylistStatusPolling(playerStore.currentPlaylistId);
    }
  } catch (error) {
    playerStore.notify('Erro ao processar lote', 'error');
    activeDownloadIds.value = []; // Limpa se der erro grave
  }
}

const openMoveModal = async (track) => {
  trackToMove.value = track
  await playerStore.loadLibrary()
  showMoveModal.value = true
}

const moveToPlaylist = async (playlistId) => {
  if (!trackToMove.value) return
  isMoving.value = true

  try {
    const response = await fetch(`http://localhost:8000/api/tracks/${trackToMove.value.id}/move`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ playlist_id: playlistId })
    })

    const data = await response.json()
    if (!response.ok) throw new Error(data.error || 'Erro ao adicionar a música')

    await playerStore.loadLibrary()
    showMoveModal.value = false
    playerStore.notify('Música adicionada à playlist!', 'success')
  } catch (error) {
    playerStore.notify(error.message, 'error')
  } finally {
    isMoving.value = false
    trackToMove.value = null
  }
}

const exportToPendrive = () => {
  if (!playerStore.currentPlaylistId) {
    alert("ERRO: Selecione uma playlist no menu lateral primeiro!");
    return;
  }
  playerStore.notify('A compactar músicas. O download vai começar em breve!', 'success');
  window.open(`http://localhost:8000/api/playlists/${playerStore.currentPlaylistId}/export`, '_blank');
}
</script>

<template>
  <div class="w-full mt-12 px-4">
    <div class="mb-6 border-b border-neutral-800 pb-4">
      <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4 mb-4">
        
        <h3 class="text-2xl font-bold flex-shrink-0">Faixas Musicais</h3>
        
        <div class="relative w-full max-w-md xl:mx-auto">
          <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <Search class="w-4 h-4 text-neutral-500" />
          </div>
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Buscar por título ou artista..." 
            class="w-full bg-neutral-900/80 border border-neutral-800 text-white rounded-full py-2.5 pl-11 pr-10 focus:outline-none focus:border-blue-500/50 focus:bg-neutral-900 focus:ring-1 focus:ring-blue-500/50 transition-all text-sm placeholder-neutral-500 shadow-inner"
          />
          <button 
            v-if="searchQuery" 
            @click="searchQuery = ''" 
            class="absolute inset-y-0 right-0 pr-4 flex items-center text-neutral-500 hover:text-white transition-colors"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="flex gap-3 flex-shrink-0">
          <button 
            @click="exportToPendrive"
            class="bg-emerald-600 text-white font-bold py-2 px-6 rounded-full hover:scale-105 transition-transform flex items-center gap-2 text-sm shadow-lg shadow-emerald-900/20"
          >
            <DownloadCloud class="w-4 h-4" />
            <span class="hidden sm:inline">Exportar (.zip)</span>
          </button>

          <button 
            @click="downloadAll"
            :disabled="isDownloadingGlobal"
            class="bg-blue-600 text-white font-bold py-2 px-6 rounded-full hover:scale-105 transition-transform disabled:opacity-50 disabled:hover:scale-100 flex items-center gap-2 text-sm shadow-lg shadow-blue-900/20"
          >
            <Loader2 v-if="isDownloadingGlobal" class="w-4 h-4 animate-spin" />
            <Download v-else class="w-4 h-4" />
            <span class="hidden sm:inline">{{ isDownloadingGlobal ? 'Na Fila de Download...' : 'Transferir Todas' }}</span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="playerStore.isLoadingTracks" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 9" :key="i" class="bg-neutral-900/50 p-4 rounded-xl flex items-center gap-4 border border-neutral-800/50 animate-pulse">
        <div class="w-14 h-14 rounded-md bg-neutral-800 flex-shrink-0"></div>
        <div class="flex-1 space-y-3 py-1">
          <div class="h-3.5 bg-neutral-800 rounded-full w-3/4"></div>
          <div class="h-2.5 bg-neutral-800 rounded-full w-1/2"></div>
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="(track, index) in filteredTracks" :key="track.id"
        class="bg-neutral-900/50 p-3 rounded-xl flex items-center gap-3 hover:bg-neutral-800 transition-colors border border-neutral-800/50 group relative"
        :class="playerStore.currentTrack?.id === track.id ? 'border-blue-500/60 bg-blue-900/10' : ''"
      >
        <img 
          v-if="track.cover_url" 
          :src="track.cover_url" 
          @error="$event.target.src = 'https://placehold.co/100x100/262626/888?text=🎵'" 
          alt="Capa" 
          class="w-12 h-12 rounded-md object-cover shadow-md flex-shrink-0" 
        />
        <div v-else class="w-12 h-12 rounded-md bg-neutral-800 flex items-center justify-center shadow-md flex-shrink-0">
          <Music class="w-5 h-5 text-neutral-500" />
        </div>

        <div class="flex-1 min-w-0 pr-2">
          <h4 class="font-bold text-sm text-white truncate" :class="playerStore.currentTrack?.id === track.id ? 'text-blue-400' : ''">
            {{ track.title }}
          </h4>
          <p class="text-xs text-neutral-400 truncate mt-0.5">{{ track.artist }}</p>
        </div>

        <div class="flex gap-1 items-center bg-transparent xl:opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
          
          <button 
            @click="openEditModal(track)"
            class="w-8 h-8 rounded-full text-neutral-400 hover:text-blue-400 hover:bg-neutral-800 flex items-center justify-center transition-all"
            title="Editar Música"
          >
            <Pencil class="w-4 h-4" />
          </button>
          
          <button 
            @click="openDeleteModal(track)"
            class="w-8 h-8 rounded-full text-neutral-400 hover:text-red-400 hover:bg-neutral-800 flex items-center justify-center transition-all"
            title="Excluir Música"
          >
            <Trash2 class="w-4 h-4" />
          </button>

          <button 
            v-if="track.file_path"
            @click="openMoveModal(track)"
            class="w-8 h-8 rounded-full text-neutral-400 hover:text-white hover:bg-neutral-800 flex items-center justify-center transition-all"
            title="Adicionar à Playlist"
          >
            <FolderPlus class="w-4 h-4" />
          </button>

          <button 
            v-if="!track.file_path"
            @click="downloadAudio(track.id)"
            :disabled="downloadingTracks[track.id]"
            class="w-8 h-8 rounded-full text-white hover:bg-neutral-800 flex items-center justify-center transition-all disabled:opacity-50"
          >
            <Loader2 v-if="downloadingTracks[track.id]" class="w-4 h-4 animate-spin text-blue-400" />
            <Download v-else class="w-4 h-4" />
          </button>
        </div>

        <button 
          v-if="track.file_path"
          @click="playerStore.playTrack(track, filteredTracks)"
          class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center transition-transform hover:scale-105 shadow-[0_0_10px_rgba(37,99,235,0.3)] flex-shrink-0"
        >
          <Pause v-if="playerStore.currentTrack?.id === track.id && playerStore.isPlaying" class="w-4 h-4 fill-white" />
          <Play v-else class="w-4 h-4 fill-white ml-0.5" /> 
        </button>

      </div>
    </div>

    <div v-if="!playerStore.isLoadingTracks && playerStore.tracks.length === 0" class="flex flex-col items-center justify-center py-20 text-center w-full">
      <div class="w-20 h-20 bg-neutral-900 rounded-full flex items-center justify-center mb-4 shadow-inner">
        <Music class="w-10 h-10 text-neutral-600" />
      </div>
      <h3 class="text-xl font-bold text-white mb-2">Nada por aqui!</h3>
      <p class="text-sm text-neutral-400 max-w-sm">
        Esta playlist está vazia ou você ainda não importou nenhuma música. Vá em "Todas as Músicas" para adicionar.
      </p>
    </div>

    <div v-if="showEditModal" @click.self="showEditModal = false" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity">
      <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-transform scale-100 animate-slide-up">
        
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-lg font-bold text-white flex items-center gap-2">
            <Pencil class="w-5 h-5 text-blue-500" />
            Editar Música
          </h3>
          <button @click="showEditModal = false" class="text-neutral-400 hover:text-white transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="flex flex-col gap-4 mb-6">
          <div>
            <label class="block text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Título da Música</label>
            <input 
              v-model="editForm.title" 
              type="text" 
              class="w-full bg-neutral-950 border border-neutral-800 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
            />
          </div>
          <div>
            <label class="block text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Artista</label>
            <input 
              v-model="editForm.artist" 
              type="text" 
              class="w-full bg-neutral-950 border border-neutral-800 text-white rounded-lg px-4 py-2.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all text-sm"
            />
          </div>
        </div>

        <div class="flex gap-3">
          <button @click="showEditModal = false" class="flex-1 bg-neutral-800 hover:bg-neutral-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm">
            Cancelar
          </button>
          <button @click="saveTrackEdit" :disabled="isSavingEdit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-xl transition-colors disabled:opacity-50 text-sm flex items-center justify-center gap-2">
            <Loader2 v-if="isSavingEdit" class="w-4 h-4 animate-spin" /> Salvar
          </button>
        </div>

      </div>
    </div>

    <div v-if="showDeleteModal" @click.self="showDeleteModal = false" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity">
      <div class="bg-neutral-900 border border-red-900/50 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-transform scale-100 animate-slide-up">
        
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-white flex items-center gap-2">
            <Trash2 class="w-5 h-5 text-red-500" />
            Excluir Música
          </h3>
          <button @click="showDeleteModal = false" class="text-neutral-400 hover:text-white transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-sm text-neutral-300 mb-6 leading-relaxed">
          Tem certeza que deseja excluir permanentemente <strong>{{ trackToDelete?.title }}</strong>?<br>
          <span class="text-red-400 text-xs mt-2 block">Esta ação apagará o ficheiro de áudio do seu computador. Não pode ser desfeita.</span>
        </p>

        <div class="flex gap-3">
          <button @click="showDeleteModal = false" class="flex-1 bg-neutral-800 hover:bg-neutral-700 text-white font-bold py-2.5 rounded-xl transition-colors text-sm">
            Cancelar
          </button>
          <button @click="confirmDeleteTrack" :disabled="isDeleting" class="flex-1 bg-red-600 hover:bg-red-500 text-white font-bold py-2.5 rounded-xl transition-colors disabled:opacity-50 text-sm flex items-center justify-center gap-2">
            <Loader2 v-if="isDeleting" class="w-4 h-4 animate-spin" />
            Sim, Excluir
          </button>
        </div>

      </div>
    </div>
    
    <div v-if="showMoveModal" @click.self="showMoveModal = false" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity">
      <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-transform scale-100 animate-slide-up">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-white">Adicionar à Playlist</h3>
          <button @click="showMoveModal = false" class="text-neutral-400 hover:text-white transition-colors"><X class="w-5 h-5" /></button>
        </div>
        <p class="text-sm text-neutral-400 mb-4 truncate">Mover <strong class="text-white">{{ trackToMove?.title }}</strong> para:</p>
        <div class="max-h-60 overflow-y-auto invisible-scrollbar flex flex-col gap-2 mb-6">
          <button v-for="pl in playerStore.savedPlaylists" :key="pl.id" @click="moveToPlaylist(pl.id)" :disabled="isMoving" class="flex items-center gap-3 p-3 rounded-xl bg-neutral-800/50 hover:bg-neutral-800 transition-colors border border-transparent hover:border-neutral-700 w-full text-left">
            <div class="w-10 h-10 bg-neutral-950 rounded-md flex items-center justify-center flex-shrink-0"><FolderPlus class="w-4 h-4 text-blue-500" /></div>
            <div class="flex-1 overflow-hidden">
              <h4 class="font-bold text-sm text-white truncate">{{ pl.name }}</h4>
              <p class="text-[11px] text-neutral-500">{{ pl.tracks_count }} faixas</p>
            </div>
          </button>
        </div>
      </div>
    </div>

    <div v-if="isDownloadingGlobal" class="fixed bottom-28 right-6 z-[100] bg-neutral-900 border border-neutral-800 p-4 rounded-2xl shadow-2xl flex items-center gap-4 w-72 animate-slide-up">
      <div class="w-10 h-10 rounded-full bg-blue-600/20 flex items-center justify-center flex-shrink-0 border border-blue-500/30">
        <Loader2 class="w-5 h-5 text-blue-400 animate-spin" />
      </div>
      <div class="flex-1">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-bold text-white">Na fila do Servidor</span>
          <span class="text-xs text-blue-400 font-bold">{{ batchCompleted }} / {{ batchTotal }}</span>
        </div>
        <div class="w-full bg-neutral-800 rounded-full h-1.5 overflow-hidden">
          <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-500 ease-out" :style="{ width: `${(batchCompleted / batchTotal) * 100}%` }"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.invisible-scrollbar::-webkit-scrollbar { display: none; }
.invisible-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
@keyframes slideUp {
  from { opacity: 0; transform: translateY(10px); scale: 0.95; }
  to { opacity: 1; transform: translateY(0); scale: 1; }
}
.animate-slide-up {
  animation: slideUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>