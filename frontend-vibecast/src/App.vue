<script setup>
import { ref } from 'vue'

// Variáveis reativas para controlar o estado da tela
const spotifyUrl = ref('')
const isLoading = ref(false)
const message = ref('')
const isError = ref(false)
const tracks = ref([])
const downloadingTracks = ref({})
const currentTrack = ref(null)
const isPlaying = ref(false)
let audioPlayer = null // Objeto de áudio nativo

// Função que dispara a comunicação com o Laravel
const importPlaylist = async () => {
  if (!spotifyUrl.value) {
    message.value = 'Por favor, insira um link válido.'
    isError.value = true
    return
  }

  isLoading.value = true
  message.value = 'Importando... O Python está analisando a página.'
  isError.value = false

  try {
    const response = await fetch('http://localhost:8000/api/playlist/import', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ url: spotifyUrl.value })
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || 'Erro ao importar playlist.')
    }

    message.value = 'Sucesso! Músicas extraídas com sucesso.'
    isError.value = false
    spotifyUrl.value = ''

    tracks.value = data.tracks || [];

  } catch (error) {
    message.value = error.message
    isError.value = true
  } finally {
    isLoading.value = false
  }
}

// NOVA FUNÇÃO: O Botão de Download chamará esta função!
const downloadAudio = async (trackId) => {
  // Ativa a "rodinha girando" só para essa música específica
  downloadingTracks.value[trackId] = true

  try {
    const response = await fetch(`http://localhost:8000/api/tracks/${trackId}/download`, {
      method: 'POST',
      headers: { 'Accept': 'application/json' }
    })
    const data = await response.json()

    if (!response.ok) throw new Error(data.error || 'Erro no download')

    // Mágica Visual: Atualiza a música na tela avisando que ela já tem arquivo físico!
    const trackIndex = tracks.value.findIndex(t => t.id === trackId)
    if (trackIndex !== -1) {
      tracks.value[trackIndex].file_path = data.file_path
    }
  } catch (error) {
    alert("Ops! " + error.message)
  } finally {
    downloadingTracks.value[trackId] = false // Para a rodinha
  }
}

// NOVA FUNÇÃO: Executada ao clicar no botão verde ▶
const playTrack = (track) => {
  // Se o usuário clicar na mesma música que já está tocando, ele pausa
  if (currentTrack.value && currentTrack.value.id === track.id) {
    if (isPlaying.value) {
      audioPlayer.pause()
      isPlaying.value = false
    } else {
      audioPlayer.play()
      isPlaying.value = true
    }
    return
  }

  // Se já tinha outra música tocando, para ela primeiro
  if (audioPlayer) {
    audioPlayer.pause()
  }

  currentTrack.value = track

  // Criamos o reprodutor apontando para a URL pública gerada pelo Laravel Symlink
  // O data.file_path que vem do Laravel já é o link completo "http://localhost:8000/storage/musicas/..."
  audioPlayer = new Audio(track.file_path)

  audioPlayer.play()
  isPlaying.value = true

  // Ouvinte para quando a música terminar sozinha
  audioPlayer.onended = () => {
    isPlaying.value = false
    currentTrack.value = null
  }
}

</script>

<template>
  <div class="min-h-screen bg-neutral-900 text-white flex flex-col items-center pt-20 font-sans">

    <h1 class="text-5xl font-extrabold text-green-500 mb-8 tracking-tighter">VibeCast</h1>

    <div class="bg-neutral-800 p-8 rounded-2xl shadow-2xl w-full max-w-md border border-neutral-700">
      <h2 class="text-xl font-semibold mb-2">Importar Playlist</h2>
      <p class="text-neutral-400 text-sm mb-6">Cole o link público do Spotify para extrair as músicas diretamente para o
        seu banco de dados.</p>

      <form @submit.prevent="importPlaylist" class="flex flex-col gap-4">
        <input v-model="spotifyUrl" type="url" placeholder="https://open.spotify.com/playlist/..."
          class="w-full bg-neutral-900 border border-neutral-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:border-green-500 transition-colors"
          :disabled="isLoading" required />

        <button type="submit"
          class="w-full bg-green-500 hover:bg-green-400 text-black font-bold py-3 px-4 rounded-lg transition-colors flex justify-center items-center disabled:bg-neutral-600 disabled:text-neutral-400"
          :disabled="isLoading">
          <span v-if="isLoading">Processando...</span>
          <span v-else>Importar Músicas</span>
        </button>
      </form>

      <div v-if="message" class="mt-4 text-center text-sm font-medium p-3 rounded-lg transition-all"
        :class="isError ? 'bg-red-900/50 text-red-400 border border-red-800' : 'bg-green-900/50 text-green-400 border border-green-800'">
        {{ message }}
      </div>
    </div>

    <div v-if="tracks.length > 0" class="w-full max-w-4xl mt-12 mb-32 pb-8">
      <h3 class="text-2xl font-bold mb-6 border-b border-neutral-700 pb-2">Músicas da Playlist</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

        <div v-for="(track, index) in tracks" :key="index"
          class="bg-neutral-800 p-4 rounded-xl flex items-center gap-4 hover:bg-neutral-700 transition-colors border border-neutral-700/50 group">
          <img v-if="track.cover_url" :src="track.cover_url" alt="Capa"
            class="w-16 h-16 rounded-md object-cover shadow-md" />
          <div v-else class="w-16 h-16 rounded-md bg-neutral-600 flex items-center justify-center shadow-md">
            🎵
          </div>

          <div class="flex-1 overflow-hidden">
            <h4 class="font-bold text-white truncate">{{ track.title || track.name || 'Faixa Desconhecida' }}</h4>
            <p class="text-sm text-neutral-400 truncate">{{ track.artist || 'Artista Desconhecido' }}</p>
          </div>

          <div class="flex gap-2">

            <button v-if="!track.file_path" @click="downloadAudio(track.id)" :disabled="downloadingTracks[track.id]"
              class="w-10 h-10 rounded-full bg-neutral-700 hover:bg-neutral-600 text-white flex items-center justify-center transition-all disabled:opacity-50"
              title="Baixar MP3">
              <span v-if="downloadingTracks[track.id]" class="animate-spin">⏳</span>
              <span v-else>⬇️</span>
            </button>

            <button v-if="track.file_path" @click="playTrack(track)"
              class="w-10 h-10 rounded-full bg-green-500 text-black flex items-center justify-center transition-transform hover:scale-105 shadow-[0_0_15px_rgba(34,197,94,0.4)]"
              :title="currentTrack?.id === track.id && isPlaying ? 'Pausar' : 'Tocar Música'">
              <span v-if="currentTrack?.id === track.id && isPlaying">⏸</span>
              <span v-else>▶</span>
            </button>

          </div>
        </div>
      </div>
    </div>
    <div 
      v-if="currentTrack" 
      class="fixed bottom-0 left-0 right-0 bg-neutral-950/95 backdrop-blur-md border-t border-neutral-800 p-4 flex items-center justify-between px-8 transition-all animate-slide-up shadow-[0_-10px_25px_rgba(0,0,0,0.5)] z-50"
    >
      <div class="flex items-center gap-4 max-w-sm">
        <img 
          :src="currentTrack.cover_url || 'https://images.unsplash.com/photo-1614680376593-902f74fa0d41?w=100&auto=format&fit=crop'" 
          class="w-14 h-14 rounded-md object-cover"
        />
        <div class="overflow-hidden">
          <h4 class="font-bold text-sm truncate text-white">{{ currentTrack.title }}</h4>
          <p class="text-xs text-neutral-400 truncate">{{ currentTrack.artist }}</p>
        </div>
      </div>

      <div class="flex flex-col items-center gap-2 flex-1 max-w-xl px-4">
        <button 
          @click="playTrack(currentTrack)"
          class="w-10 h-10 rounded-full bg-white text-black flex items-center justify-center hover:scale-105 transition-transform font-bold"
        >
          <span v-if="isPlaying">⏸</span>
          <span v-else>▶</span>
        </button>
        <span class="text-[10px] text-green-500 font-mono tracking-widest uppercase">Modo Local Streaming Activo</span>
      </div>

      <div class="w-32 hidden md:block text-right">
        <span class="text-xs text-neutral-500 font-mono">VibeCast Player v1</span>
      </div>
    </div>

  </div> 
</template>