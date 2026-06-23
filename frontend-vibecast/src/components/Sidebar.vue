<script setup>
import { ref, onMounted } from 'vue'
import { Library, ListMusic, Plus, X, Loader2, Pencil, Trash2, AlertTriangle, Home } from 'lucide-vue-next'
import { usePlayerStore } from '../stores/playerStore'

const playerStore = usePlayerStore()

// Controle do Modal Inteligente (Criação/Edição)
const showModal = ref(false)
const modalMode = ref('create') 
const playlistIdToEdit = ref(null)
const playlistName = ref('')
const playlistDescription = ref('')
const isProcessing = ref(false)

// Controle do Modal de Exclusão
const showDeleteModal = ref(false)
const playlistToDelete = ref(null)

// 1. Função para abrir uma Playlist e gravar o ID
const selectPlaylist = async (playlistId) => {
  playerStore.currentPlaylistId = playlistId; // <-- ATIVA O BOTÃO DO ZIP
  
  try {
    const response = await fetch(`http://localhost:8000/api/playlists/${playlistId}`);
    const data = await response.json();
    playerStore.tracks = data.tracks; 
  } catch (error) {
    console.error("Erro ao carregar músicas:", error);
  }
}

// 2. Função para o botão "Todas as Músicas" (Limpa o ID)
const loadAll = () => {
  playerStore.currentPlaylistId = null; // <-- DESATIVA O BOTÃO DO ZIP
  playerStore.loadAllTracks();
}

const openCreateModal = () => {
  modalMode.value = 'create'
  playlistName.value = ''
  playlistDescription.value = ''
  showModal.value = true
}

const openEditModal = (pl, event) => {
  event.stopPropagation() 
  modalMode.value = 'edit'
  playlistIdToEdit.value = pl.id
  playlistName.value = pl.name
  playlistDescription.value = pl.description || ''
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  playlistName.value = ''
  playlistDescription.value = ''
  playlistIdToEdit.value = null
}

const handleSave = async () => {
  if (!playlistName.value.trim()) return

  isProcessing.value = true
  
  const url = modalMode.value === 'create' 
    ? 'http://localhost:8000/api/playlists' 
    : `http://localhost:8000/api/playlists/${playlistIdToEdit.value}`
    
  const method = modalMode.value === 'create' ? 'POST' : 'PUT'

  try {
    const response = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ 
        name: playlistName.value,
        description: playlistDescription.value 
      })
    })

    if (!response.ok) throw new Error('Erro ao processar requisição')

    await playerStore.loadLibrary()
    closeModal()
  } catch (error) {
    alert("Erro: " + error.message)
  } finally {
    isProcessing.value = false
  }
}

// Prepara a exclusão e abre o Modal
const confirmDeletePrompt = (pl, event) => {
  event.stopPropagation()
  playlistToDelete.value = pl
  showDeleteModal.value = true
}

// Executa a exclusão de fato
const executeDelete = async () => {
  if (!playlistToDelete.value) return
  isProcessing.value = true

  try {
    const response = await fetch(`http://localhost:8000/api/playlists/${playlistToDelete.value.id}`, {
      method: 'DELETE',
      headers: { 'Accept': 'application/json' }
    })

    if (!response.ok) throw new Error('Erro ao excluir playlist')

    if (playerStore.currentPlaylistId === playlistToDelete.value.id) {
      playerStore.currentPlaylistId = null
      playerStore.tracks = []
    }

    await playerStore.loadLibrary()
    showDeleteModal.value = false
  } catch (error) {
    alert("Erro ao excluir: " + error.message)
  } finally {
    isProcessing.value = false
  }
}

onMounted(() => {
  playerStore.loadLibrary()
  playerStore.loadAllTracks()
})
</script>

<template>
  <div class="h-full p-4 flex flex-col gap-6 select-none relative font-sans">
    
    <div class="flex items-center justify-between px-2">
      <div class="flex items-center gap-2">
        <Library class="w-6 h-6 text-blue-500" />
        <span class="font-extrabold text-lg tracking-tight">Biblioteca</span>
      </div>
      
      <button 
        @click="openCreateModal"
        class="w-8 h-8 rounded-full bg-neutral-800/80 hover:bg-neutral-700 flex items-center justify-center transition-colors shadow-sm"
        title="Criar Nova Playlist"
      >
        <Plus class="w-5 h-5 text-neutral-300" />
      </button>
    </div>

    <div 
      @click="loadAll"
      class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-all mb-2 mt-2"
      :class="playerStore.currentPlaylistId === null ? 'bg-blue-600/20 border border-blue-500/50' : 'hover:bg-neutral-900/50'"
    >
      <div class="w-10 h-10 rounded-md flex items-center justify-center shadow flex-shrink-0"
           :class="playerStore.currentPlaylistId === null ? 'bg-blue-500/20 text-blue-400' : 'bg-neutral-800/80 text-neutral-400'">
        <Home class="w-5 h-5" />
      </div>
      <div class="flex-1 font-bold text-sm" :class="playerStore.currentPlaylistId === null ? 'text-blue-400' : 'text-white'">
        Todas as Músicas
      </div>
    </div>

    <button 
      @click="playerStore.openImportModal()" 
      class="w-full flex items-center gap-3 p-3 mt-4 mb-2 rounded-lg cursor-pointer transition-all bg-blue-600/20 border border-blue-500/50 hover:bg-blue-600/40 text-blue-100 shadow-[0_0_15px_rgba(59,130,246,0.15)]"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
      <span class="font-bold">Nova Importação</span>
    </button>

    <div class="h-px w-full bg-neutral-800/50 my-1"></div>

    <div class="flex-1 overflow-y-auto flex flex-col gap-2 invisible-scrollbar">
      <div v-if="playerStore.savedPlaylists.length === 0" class="text-neutral-500 text-xs px-2 py-4">
        Nenhuma playlist salva.
      </div>

      <div 
        v-for="pl in playerStore.savedPlaylists" 
        :key="pl.id"
        @click="selectPlaylist(pl.id)"
        class="flex items-center justify-between p-2 rounded-lg cursor-pointer transition-all group"
        :class="playerStore.currentPlaylistId === pl.id ? 'bg-neutral-800/60 border border-neutral-700/50' : 'hover:bg-neutral-900/50'"
      >
        <div class="flex items-center gap-3 flex-1 overflow-hidden">
          <div class="w-12 h-12 bg-neutral-800/80 rounded-md flex items-center justify-center shadow flex-shrink-0 transition-colors group-hover:bg-neutral-700">
            <ListMusic class="w-5 h-5 text-neutral-400 group-hover:text-blue-400 transition-colors" />
          </div>
          
          <div class="flex-1 overflow-hidden">
            <h4 class="font-bold text-xs truncate text-white" :class="playerStore.currentPlaylistId === pl.id ? 'text-blue-500' : ''">
              {{ pl.name }}
            </h4>
            <p v-if="pl.description" class="text-[10px] text-neutral-500 truncate italic mt-0.5">
              {{ pl.description }}
            </p>
            <p class="text-[11px] text-neutral-400 mt-0.5">{{ pl.tracks_count }} faixas</p>
          </div>
        </div>

        <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-opacity pl-2">
          <button 
            @click="openEditModal(pl, $event)"
            class="w-7 h-7 rounded-full hover:bg-neutral-800 flex items-center justify-center text-neutral-400 hover:text-white transition-colors"
            title="Editar detalhes"
          >
            <Pencil class="w-3.5 h-3.5" />
          </button>
          
          <button 
            @click="confirmDeletePrompt(pl, $event)"
            class="w-7 h-7 rounded-full hover:bg-neutral-900/80 flex items-center justify-center text-neutral-500 hover:text-red-400 transition-colors"
            title="Excluir playlist"
          >
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>

      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 backdrop-blur-sm transition-opacity">
      <div class="bg-neutral-900 border border-neutral-700 rounded-2xl p-6 w-full max-w-sm shadow-2xl transform transition-transform scale-100 animate-slide-up">
        <div class="flex justify-between items-center mb-5">
          <h3 class="text-lg font-bold text-white">{{ modalMode === 'create' ? 'Nova Playlist' : 'Editar Detalhes' }}</h3>
          <button @click="closeModal" class="text-neutral-400 hover:text-white transition-colors"><X class="w-5 h-5" /></button>
        </div>
        <input v-model="playlistName" @keyup.enter="handleSave" type="text" placeholder="Nome da playlist..." class="w-full bg-neutral-950 border border-neutral-700 text-white rounded-lg px-4 py-3 mb-4 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder-neutral-600 font-sans text-sm" autofocus />
        <textarea v-model="playlistDescription" placeholder="Descrição opcional..." rows="3" class="w-full bg-neutral-950 border border-neutral-700 text-white rounded-lg px-4 py-3 mb-6 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder-neutral-600 font-sans text-xs resize-none"></textarea>
        <div class="flex justify-end gap-3">
          <button @click="closeModal" class="px-4 py-2 rounded-lg font-semibold text-sm text-neutral-400 hover:text-white hover:bg-neutral-800 transition-colors">Cancelar</button>
          <button @click="handleSave" :disabled="isProcessing || !playlistName.trim()" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-5 rounded-lg transition-colors disabled:opacity-50 flex items-center justify-center min-w-[100px]">
            <Loader2 v-if="isProcessing" class="w-4 h-4 animate-spin" /><span v-else>{{ modalMode === 'create' ? 'Criar' : 'Salvar' }}</span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="showDeleteModal" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/80 backdrop-blur-sm transition-opacity">
      <div class="bg-neutral-900 border border-red-900/50 rounded-3xl p-6 w-full max-w-sm shadow-2xl transform transition-transform scale-100 animate-slide-up">
        
        <div class="flex flex-col items-center text-center mb-6 mt-2">
          <div class="w-14 h-14 bg-red-500/10 rounded-full flex items-center justify-center mb-4">
            <AlertTriangle class="w-7 h-7 text-red-500" />
          </div>
          <h3 class="text-xl font-bold text-white mb-2">Excluir Playlist?</h3>
          <p class="text-sm text-neutral-400">
            Tem certeza que deseja excluir <strong class="text-white">"{{ playlistToDelete?.name }}"</strong>?<br>Esta ação não pode ser desfeita.
          </p>
        </div>

        <div class="flex justify-between gap-3 w-full">
          <button @click="showDeleteModal = false" class="flex-1 py-3 rounded-xl font-semibold text-sm text-neutral-400 hover:text-white bg-neutral-800/50 hover:bg-neutral-800 transition-colors">
            Cancelar
          </button>
          <button @click="executeDelete" :disabled="isProcessing" class="flex-1 bg-red-600 hover:bg-red-500 text-white font-bold py-3 rounded-xl transition-colors disabled:opacity-50 flex items-center justify-center shadow-[0_0_15px_rgba(220,38,38,0.4)]">
            <Loader2 v-if="isProcessing" class="w-5 h-5 animate-spin" />
            <span v-else>Sim, Excluir</span>
          </button>
        </div>

      </div>
    </div>
    
  </div>
</template>

<style scoped>
.invisible-scrollbar::-webkit-scrollbar {
  display: none;
}
.invisible-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(10px); scale: 0.95; }
  to { opacity: 1; transform: translateY(0); scale: 1; }
}
.animate-slide-up {
  animation: slideUp 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>