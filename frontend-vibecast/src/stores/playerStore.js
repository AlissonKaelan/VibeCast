import { ref } from 'vue'
import { defineStore } from 'pinia'

export const usePlayerStore = defineStore('player', () => {
  // 1. Variáveis do Player
  const tracks = ref([])
  const currentTrack = ref(null)
  const isPlaying = ref(false)
  const currentTime = ref(0)
  const duration = ref(0)
  const volume = ref(0.5)
  const isSeeking = ref(false)
  let audioPlayer = null 

  // ==========================================
  // NOVIDADES: Estados da Fila e Modos de Play
  // ==========================================
  const queue = ref([])             // A fila real que vai tocar
  const originalQueue = ref([])     // O backup da ordem original
  const currentIndex = ref(-1)      // Posição atual na fila
  const isShuffle = ref(false)      // Botão de Aleatório
  const loopMode = ref(0)           // 0: Desativado, 1: Repetir Tudo, 2: Repetir Atual

  // 2. Variáveis da Biblioteca
  const savedPlaylists = ref([])
  const currentPlaylistId = ref(null)

  // 3. Variáveis de Notificação Global
  const notification = ref({ show: false, text: '', type: 'success' })
  
  const notify = (text, type = 'success') => {
    notification.value = { show: true, text, type }
    setTimeout(() => {
      notification.value.show = false
    }, 4000)
  }

  // 4. Funções da API (Laravel)
  const loadLibrary = async () => {
    try {
      const response = await fetch('http://localhost:8000/api/playlists')
      const data = await response.json()
      savedPlaylists.value = data.playlists
    } catch (error) {
      console.error("Erro ao carregar biblioteca:", error)
    }
  }

  const loadAllTracks = async () => {
    try {
      const response = await fetch('http://localhost:8000/api/tracks')
      const data = await response.json()
      tracks.value = data.tracks
      currentPlaylistId.value = null 
    } catch (error) {
      console.error("Erro ao carregar todas as músicas:", error)
    }
  }

  // 5. Funções de Áudio
  const getAudioUrl = (filePath) => {
    if (!filePath) return ''
    if (filePath.startsWith('http')) return filePath
    return `http://localhost:8000/api/stream?path=${filePath}`
  }

  // Função utilitária para embaralhar array
  const shuffleArray = (array) => {
    const newArray = [...array]
    for (let i = newArray.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1))
      ;[newArray[i], newArray[j]] = [newArray[j], newArray[i]]
    }
    return newArray
  }

  const playTrack = (track, playlistTracks = [], forcePlay = false) => {
    // Só pausa se NÃO for um comando forçado do sistema
    if (currentTrack.value && currentTrack.value.id === track.id && !forcePlay) {
      if (isPlaying.value) {
        audioPlayer.pause()
        isPlaying.value = false
      } else {
        audioPlayer.play()
        isPlaying.value = true
      }
      return
    }

    if (playlistTracks.length > 0) {
      const playableTracks = playlistTracks.filter(t => t.file_path)
      originalQueue.value = [...playableTracks]
      
      if (isShuffle.value) {
        const remaining = playableTracks.filter(t => t.id !== track.id)
        queue.value = [track, ...shuffleArray(remaining)]
      } else {
        queue.value = [...playableTracks]
      }
    } else if (queue.value.length === 0) {
      queue.value = [track]
      originalQueue.value = [track]
    }

    currentIndex.value = queue.value.findIndex(t => t.id === track.id)

    if (audioPlayer) audioPlayer.pause()

    currentTrack.value = track
    audioPlayer = new Audio(getAudioUrl(track.file_path))
    audioPlayer.volume = volume.value


    audioPlayer.addEventListener('error', async () => {
      notify('Arquivo não encontrado no PC. Sincronizando...', 'error')
      isPlaying.value = false
      const trackInList = tracks.value.find(t => t.id === track.id)
      if (trackInList) trackInList.file_path = null
      try {
        await fetch(`http://localhost:8000/api/tracks/${track.id}/reset-file`, { method: 'POST' })
      } catch (e) {}
    })
    
    audioPlayer.addEventListener('timeupdate', () => {
      if (!isSeeking.value) currentTime.value = audioPlayer.currentTime
    })

    audioPlayer.addEventListener('loadedmetadata', () => {
      duration.value = audioPlayer.duration
    })

    audioPlayer.play()
    isPlaying.value = true

    // O SEGREDO: Controle manual do Loop ao terminar a música!
    audioPlayer.onended = () => {
      if (loopMode.value === 2) {
        // Se for "Repetir Música Atual", voltamos o tempo para o zero e damos play!
        audioPlayer.currentTime = 0
        audioPlayer.play()
      } else {
        // Caso contrário, tenta ir para a próxima (que pode ter o Loop da Fila)
        nextTrack()
      }
    }
  }

  // 2. nextTrack e prevTrack agora avisam que é "forcePlay = true"
  const nextTrack = () => {
    if (queue.value.length === 0) return

    let nextIndex = currentIndex.value + 1

    if (nextIndex >= queue.value.length) {
      if (loopMode.value === 1) { // 1 = Repetir Fila (Playlist)
        nextIndex = 0 // Volta para a primeira música da fila!
      } else {
        isPlaying.value = false // Sem repetição, apenas para de tocar.
        return
      }
    }

    playTrack(queue.value[nextIndex], [], true)
  }

  const prevTrack = () => {
    if (queue.value.length === 0) return

    if (audioPlayer && audioPlayer.currentTime > 3) {
      audioPlayer.currentTime = 0
      return
    }

    let prevIdx = currentIndex.value - 1
    
    if (prevIdx < 0) {
      if (loopMode.value === 1) {
        prevIdx = queue.value.length - 1 
      } else {
        prevIdx = 0
      }
    }
    // Passamos `true` no terceiro parâmetro!
    playTrack(queue.value[prevIdx], [], true)
  }

  // 3. Atualizando o Toggle Loop para refletir no áudio na mesma hora
  const toggleLoop = () => {
    // Alterna os Modos: 0 (Desligado) -> 1 (Playlist) -> 2 (Atual) -> 0...
    loopMode.value = (loopMode.value + 1) % 3
  }

  // ===============================
  // NOVOS CONTROLES DE REPRODUÇÃO
  // ===============================
  const toggleShuffle = () => {
    isShuffle.value = !isShuffle.value
    
    if (isShuffle.value) {
      const current = queue.value[currentIndex.value]
      let remaining = queue.value.filter(t => t.id !== current?.id)
      queue.value = [current, ...shuffleArray(remaining)]
      currentIndex.value = 0
    } else {
      queue.value = [...originalQueue.value]
      currentIndex.value = queue.value.findIndex(t => t.id === currentTrack.value?.id)
    }
  }

  const setVolume = (newVol) => {
    volume.value = newVol
    if (audioPlayer) audioPlayer.volume = newVol
  }

  const toggleMute = () => {
    volume.value = volume.value > 0 ? 0 : 1
    if (audioPlayer) audioPlayer.volume = volume.value
  }

  const setSeek = (newTime) => {
    const timeAsNumber = Number(newTime)
    currentTime.value = timeAsNumber
    if (audioPlayer) {
      audioPlayer.currentTime = timeAsNumber
    }
  }
  return {
    tracks, currentTrack, isPlaying, currentTime, duration, volume, isSeeking,
    queue, originalQueue, currentIndex, isShuffle, loopMode, // Exportamos as novas variáveis
    savedPlaylists, currentPlaylistId, loadLibrary, loadAllTracks,
    notification, notify,
    playTrack, nextTrack, prevTrack, toggleShuffle, toggleLoop, setVolume, toggleMute, setSeek // Exportamos os novos métodos
  }
})