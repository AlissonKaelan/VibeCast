import { ref } from 'vue'
import { defineStore } from 'pinia'

export const usePlayerStore = defineStore('player', () => {
  // 1. Variáveis do Player
  const tracks = ref([])
  const currentTrack = ref(null)
  const isPlaying = ref(false)
  const currentTime = ref(0)
  const duration = ref(0)
  const volume = ref(1)
  const isSeeking = ref(false)
  let audioPlayer = null 

  // 2. Variáveis da Biblioteca (Estas que estavam faltando!)
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

  // 4. Função que busca as pastas no Laravel
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
      currentPlaylistId.value = null // Desmarca a playlist no menu esquerdo
    } catch (error) {
      console.error("Erro ao carregar todas as músicas:", error)
    }
  }

  // 5. Funções de Áudio
  const getAudioUrl = (filePath) => {
    if (!filePath) return ''
    if (filePath.startsWith('http')) return filePath
    return `http://localhost:8000/storage/${filePath}`
  }

  const playTrack = (track) => {
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

    if (audioPlayer) audioPlayer.pause()

    currentTrack.value = track
    audioPlayer = new Audio(getAudioUrl(track.file_path))
    audioPlayer.volume = volume.value

    audioPlayer.addEventListener('error', async () => {
      notify('Arquivo não encontrado no PC. Sincronizando...', 'error')
      isPlaying.value = false
      
      // Tira o caminho do arquivo visualmente na hora para o botão de download voltar
      const trackInList = tracks.value.find(t => t.id === track.id)
      if (trackInList) trackInList.file_path = null
      
      // Avisa o Laravel para corrigir no banco de dados
      try {
        await fetch(`http://localhost:8000/api/tracks/${track.id}/reset-file`, { method: 'POST' })
      } catch (e) {}
    })
    
    audioPlayer.addEventListener('timeupdate', () => {
      if (!isSeeking.value) {
        currentTime.value = audioPlayer.currentTime
      }
    })

    audioPlayer.addEventListener('loadedmetadata', () => {
      duration.value = audioPlayer.duration
    })

    audioPlayer.play()
    isPlaying.value = true

    audioPlayer.onended = () => nextTrack()
  }

  const nextTrack = () => {
    if (!currentTrack.value || tracks.value.length === 0) return
    const currentIndex = tracks.value.findIndex(t => t.id === currentTrack.value.id)
    for (let i = currentIndex + 1; i < tracks.value.length; i++) {
      if (tracks.value[i].file_path) {
        playTrack(tracks.value[i])
        return
      }
    }
  }

  const prevTrack = () => {
    if (!currentTrack.value || tracks.value.length === 0) return
    const currentIndex = tracks.value.findIndex(t => t.id === currentTrack.value.id)
    for (let i = currentIndex - 1; i >= 0; i--) {
      if (tracks.value[i].file_path) {
        playTrack(tracks.value[i])
        return
      }
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
    currentTime.value = newTime
    if (audioPlayer) audioPlayer.currentTime = newTime
  }

  // EXPORTANDO TUDO
  return {
    tracks, currentTrack, isPlaying, currentTime, duration, volume, isSeeking,
    savedPlaylists, currentPlaylistId, loadLibrary, loadAllTracks,
    notification, notify,
    playTrack, nextTrack, prevTrack, setVolume, toggleMute, setSeek
  }
})