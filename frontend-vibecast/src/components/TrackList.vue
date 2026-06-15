<script setup>
import { ref } from 'vue'
import { Download, Loader2, Music, Play, Pause, FolderPlus, X } from 'lucide-vue-next'
import { usePlayerStore } from '../stores/playerStore'

const playerStore = usePlayerStore()

// 🟢 AS VARIÁVEIS QUE FALTAVAM (CORRIGE A TELA BRANCA)
const showMoveModal = ref(false)
const trackToMove = ref(null)
const isMoving = ref(false)

// Variáveis de Download
const downloadingTracks = ref({})
const isDownloadingAll = ref(false)
const batchTotal = ref(0)
const batchCompleted = ref(0)

// ---------------------------------------------------------
// FUNÇÕES DE DOWNLOAD (COM MOTOR DE FILA - WORKERS)
// ---------------------------------------------------------
const downloadAudio = async (trackId, isBatch = false) => {
  downloadingTracks.value[trackId] = true // Ícone girando
  try {
    const response = await fetch(`http://localhost:8000/api/tracks/${trackId}/download`, { 
      method: 'POST',
      headers: { 'Accept': 'application/json' }
    })
    
    if (!response.ok) throw new Error('Falha')
    
    // Ao terminar, atualiza o caminho do arquivo na mesma hora na tela!
    const data = await response.json()
    const track = playerStore.tracks.find(t => t.id === trackId)
    if (track) track.file_path = data.file_path

    if (!isBatch) playerStore.notify('Download concluído!', 'success')
  } catch (error) {
    if (!isBatch) playerStore.notify('Erro ao baixar música', 'error')
  } finally {
    downloadingTracks.value[trackId] = false // Para de girar
  }
}

const downloadAll = async () => {
  const tracksToDownload = playerStore.tracks.filter(t => !t.file_path)
  
  if (tracksToDownload.length === 0) {
    playerStore.notify('Todas as músicas já estão baixadas!', 'success')
    return
  }

  batchTotal.value = tracksToDownload.length
  batchCompleted.value = 0
  isDownloadingAll.value = true

  // MOTOR DE FILA (Evita travar o navegador)
  // Ele vai baixar apenas 3 músicas simultaneamente
  const concurrency = 3; 
  let index = 0;

  const downloadWorker = async () => {
    while (index < tracksToDownload.length) {
      const currentIndex = index++;
      const track = tracksToDownload[currentIndex];
      
      // Espera a música baixar...
      await downloadAudio(track.id, true);
      
      // ...e só então avança a barra de progresso!
      batchCompleted.value++; 
    }
  }

  // Cria os 3 "trabalhadores" invisíveis
  const workers = [];
  for (let i = 0; i < concurrency; i++) {
    workers.push(downloadWorker());
  }

  // Espera todos os trabalhadores terminarem o serviço
  await Promise.all(workers);

  // Finaliza
  isDownloadingAll.value = false;
  playerStore.notify(`Sucesso! ${batchTotal.value} músicas baixadas.`, 'success');
}


// ---------------------------------------------------------
// FUNÇÕES DE MOVER PARA PLAYLIST
// ---------------------------------------------------------
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
</script>

<template>
  <div class="w-full mt-12 px-4">
    <div class="mb-6 border-b border-neutral-800 pb-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
        <h3 class="text-2xl font-bold">Faixas Musicais</h3>
        <button 
          @click="downloadAll"
          :disabled="isDownloadingAll"
          class="bg-blue-600 text-white font-bold py-2 px-6 rounded-full hover:scale-105 transition-transform disabled:opacity-50 disabled:hover:scale-100 flex items-center gap-2 text-sm shadow-lg shadow-blue-900/20"
        >
          <Loader2 v-if="isDownloadingAll" class="w-4 h-4 animate-spin" />
          <Download v-else class="w-4 h-4" />
          {{ isDownloadingAll ? 'A Transferir...' : 'Transferir Todas' }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="(track, index) in playerStore.tracks" :key="track.id"
        class="bg-neutral-900/50 p-4 rounded-xl flex items-center gap-4 hover:bg-neutral-800 transition-colors border border-neutral-800/50 group"
        :class="playerStore.currentTrack?.id === track.id ? 'border-blue-500/60 bg-blue-900/10' : ''"
      >
        <img v-if="track.cover_url" :src="track.cover_url" alt="Capa" class="w-14 h-14 rounded-md object-cover shadow-md" />
        <div v-else class="w-14 h-14 rounded-md bg-neutral-800 flex items-center justify-center shadow-md">
          <Music class="w-5 h-5 text-neutral-500" />
        </div>

        <div class="flex-1 overflow-hidden">
          <h4 class="font-bold text-sm text-white truncate" :class="playerStore.currentTrack?.id === track.id ? 'text-blue-400' : ''">
            {{ track.title }}
          </h4>
          <p class="text-xs text-neutral-400 truncate mt-0.5">{{ track.artist }}</p>
        </div>

        <div class="flex gap-2 items-center">
          <button 
            v-if="track.file_path"
            @click="openMoveModal(track)"
            class="w-9 h-9 rounded-full bg-neutral-800 hover:bg-neutral-700 text-neutral-400 hover:text-white flex items-center justify-center transition-all"
            title="Adicionar à Playlist"
          >
            <FolderPlus class="w-4 h-4" />
          </button>

          <button 
            v-if="!track.file_path"
            @click="downloadAudio(track.id)"
            :disabled="downloadingTracks[track.id]"
            class="w-9 h-9 rounded-full bg-neutral-800 hover:bg-neutral-700 text-white flex items-center justify-center transition-all disabled:opacity-50"
          >
            <Loader2 v-if="downloadingTracks[track.id]" class="w-4 h-4 animate-spin" />
            <Download v-else class="w-4 h-4" />
          </button>

          <button 
            v-if="track.file_path"
            @click="playerStore.playTrack(track, playerStore.tracks)"
            class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center transition-transform hover:scale-105 shadow-[0_0_10px_rgba(37,99,235,0.3)]"
          >
            <Pause v-if="playerStore.currentTrack?.id === track.id && playerStore.isPlaying" class="w-4 h-4 fill-white" />
            <Play v-else class="w-4 h-4 fill-white ml-0.5" /> 
          </button>
        </div>
      </div>
    </div>

    <div v-if="playerStore.tracks.length === 0" class="flex flex-col items-center justify-center py-20 text-center w-full">
      <div class="w-20 h-20 bg-neutral-900 rounded-full flex items-center justify-center mb-4 shadow-inner">
        <Music class="w-10 h-10 text-neutral-600" />
      </div>
      <h3 class="text-xl font-bold text-white mb-2">Nada por aqui!</h3>
      <p class="text-sm text-neutral-400 max-w-sm">
        Esta playlist está vazia ou você ainda não importou nenhuma música. Vá em "Todas as Músicas" para adicionar.
      </p>
    </div>

    <div v-if="showMoveModal" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity">
      <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-transform scale-100 animate-slide-up">
        
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-white">Adicionar à Playlist</h3>
          <button @click="showMoveModal = false" class="text-neutral-400 hover:text-white transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-sm text-neutral-400 mb-4 truncate">
          Mover <strong class="text-white">{{ trackToMove?.title }}</strong> para:
        </p>

        <div class="max-h-60 overflow-y-auto invisible-scrollbar flex flex-col gap-2 mb-6">
          <button 
            v-for="pl in playerStore.savedPlaylists" 
            :key="pl.id"
            @click="moveToPlaylist(pl.id)"
            :disabled="isMoving"
            class="flex items-center gap-3 p-3 rounded-xl bg-neutral-800/50 hover:bg-neutral-800 transition-colors border border-transparent hover:border-neutral-700 w-full text-left"
          >
            <div class="w-10 h-10 bg-neutral-950 rounded-md flex items-center justify-center flex-shrink-0">
              <FolderPlus class="w-4 h-4 text-blue-500" />
            </div>
            <div class="flex-1 overflow-hidden">
              <h4 class="font-bold text-sm text-white truncate">{{ pl.name }}</h4>
              <p class="text-[11px] text-neutral-500">{{ pl.tracks_count }} faixas</p>
            </div>
          </button>
        </div>

      </div>
    </div>
    <div v-if="isDownloadingAll" class="fixed bottom-28 right-6 z-[100] bg-neutral-900 border border-neutral-800 p-4 rounded-2xl shadow-2xl flex items-center gap-4 w-72 animate-slide-up">
      <div class="w-10 h-10 rounded-full bg-blue-600/20 flex items-center justify-center flex-shrink-0 border border-blue-500/30">
        <Download class="w-5 h-5 text-blue-400 animate-pulse" />
      </div>
      <div class="flex-1">
        <div class="flex justify-between items-center mb-1">
          <span class="text-sm font-bold text-white">Baixando Lote</span>
          <span class="text-xs text-blue-400 font-bold">{{ batchCompleted }} / {{ batchTotal }}</span>
        </div>
        <div class="w-full bg-neutral-800 rounded-full h-1.5 overflow-hidden">
          <div 
            class="bg-blue-500 h-1.5 rounded-full transition-all duration-500 ease-out" 
            :style="{ width: `${(batchCompleted / batchTotal) * 100}%` }"
          ></div>
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