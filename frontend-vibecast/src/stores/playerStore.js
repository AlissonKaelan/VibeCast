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
  let playlistStatusInterval = null
  
  // NOVA VARIÁVEL: Controle se é Rádio Ao Vivo
  const isRadio = ref(false) 
  
  let audioPlayer = null 

  // Variável para controlar o Modal de Importação
  const isImportModalOpen = ref(false)
  const isQueueOpen = ref(false)
  const toggleQueue = () => isQueueOpen.value = !isQueueOpen.value
  
  const jumpToQueueIndex = (index) => {
    if (index >= 0 && index < queue.value.length) {
      currentIndex.value = index
      playTrack(queue.value[index], [], true)
    }
  }
  const removeFromQueue = (absoluteIndex) => {
    queue.value.splice(absoluteIndex, 1)
  }
  const reorderQueue = (oldAbsoluteIndex, newAbsoluteIndex) => {
    const item = queue.value.splice(oldAbsoluteIndex, 1)[0]
    queue.value.splice(newAbsoluteIndex, 0, item)
  }
  const openImportModal = () => isImportModalOpen.value = true
  const closeImportModal = () => isImportModalOpen.value = false

  // Estados da Fila e Modos de Play
  const queue = ref([])             
  const originalQueue = ref([])    
  const currentIndex = ref(-1)      
  const isShuffle = ref(false)      
  const loopMode = ref(0)        

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
      startPlaylistStatusPolling('all')
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

  const shuffleArray = (array) => {
    const newArray = [...array]
    for (let i = newArray.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1))
      ;[newArray[i], newArray[j]] = [newArray[j], newArray[i]]
    }
    return newArray
  }

  // Tocar Rádio Ao Vivo (Agora com Cache-Busting Automático)
  const playRadio = (radio) => {
    isRadio.value = true;
    
    currentTrack.value = {
      title: radio.name,
      artist: 'Transmissão Ao Vivo (Web Rádio)',
      cover_url: radio.logo_url || `https://ui-avatars.com/api/?name=${radio.name}&background=1db954&color=fff`,
      youtube_id: 'stream_ao_vivo',
      duration_seconds: 0
    };

    if (audioPlayer) audioPlayer.pause();
    
    // TRUQUE DE ENGENHARIA: CACHE-BUSTING
    try {
      // 1. Criamos um objeto de URL inteligente
      let streamUrl = new URL(radio.stream_url);
      
      // 2. Injetamos um parâmetro 'cb' (Cache-Buster) com a hora exata do seu PC em milissegundos
      streamUrl.searchParams.set('cb', Date.now());
      
      // 3. O link final ficará algo como: https://.../stream?cb=1718929384758
      const finalLiveUrl = streamUrl.toString();

      audioPlayer = new Audio(finalLiveUrl);
    } catch (e) {
      // Fallback: Se a URL que o usuário cadastrou for inválida, tenta usar como está
      audioPlayer = new Audio(radio.stream_url);
    }

    audioPlayer.volume = volume.value;
    audioPlayer.play();
    isPlaying.value = true;
  }

  const playTrack = (track, playlistTracks = [], forcePlay = false) => {
    isRadio.value = false; // DESLIGA O MODO RÁDIO AO TOCAR MÚSICA NORMAL

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

    audioPlayer.onended = () => {
      if (loopMode.value === 2) {
        audioPlayer.currentTime = 0
        audioPlayer.play()
      } else {
        nextTrack()
      }
    }
  }

  const nextTrack = () => {
    if (isRadio.value || queue.value.length === 0) return

    let nextIndex = currentIndex.value + 1

    if (nextIndex >= queue.value.length) {
      if (loopMode.value === 1) { 
        nextIndex = 0 
      } else {
        isPlaying.value = false 
        return
      }
    }

    playTrack(queue.value[nextIndex], [], true)
  }

  const prevTrack = () => {
    if (isRadio.value || queue.value.length === 0) return

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
    playTrack(queue.value[prevIdx], [], true)
  }

  const toggleLoop = () => {
    loopMode.value = (loopMode.value + 1) % 3
  }

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
    if (isRadio.value) return; // Não faz Seek em rádio ao vivo
    const timeAsNumber = Number(newTime)
    currentTime.value = timeAsNumber
    if (audioPlayer) {
      audioPlayer.currentTime = timeAsNumber
    }
  }

  // FUNÇÃO MÁGICA: Fica checando o banco em background para atualizar as faixas na tela
  const startPlaylistStatusPolling = (playlistId) => {
    // Se já tiver um radar ligado, desliga para não duplicar
    if (playlistStatusInterval) clearInterval(playlistStatusInterval)

    const targetId = playlistId || 'all'

    playlistStatusInterval = setInterval(async () => {
      try {
        const response = await fetch(`http://localhost:8000/api/playlists/${targetId}/status`)
        const data = await response.json()

        if (data.success) {
          let hasPendingDownloads = false

          // Varre as músicas que vieram do banco e atualiza na tela do usuário imediatamente
          data.tracks.forEach(updatedTrack => {
            const currentTrackInStore = tracks.value.find(t => t.id === updatedTrack.id)
            if (currentTrackInStore) {
              // Se o arquivo físico acabou de aparecer no banco, injeta na tela ao vivo!
              if (!currentTrackInStore.file_path && updatedTrack.file_path) {
                currentTrackInStore.file_path = updatedTrack.file_path
                currentTrackInStore.duration_seconds = updatedTrack.duration_seconds
              }
            }

            // Se ainda existir alguma música sem caminho de arquivo, o download continua ativo
            if (!updatedTrack.file_path) {
              hasPendingDownloads = true
            }
          })

          // SE CONCLUIU TUDO: Desliga o radar automaticamente para poupar bateria/processador
          if (!hasPendingDownloads) {
            clearInterval(playlistStatusInterval)
            playlistStatusInterval = null
            notify('Todos os downloads em lote foram concluídos!')
          }
        }
      } catch (error) {
        console.error("Erro no radar de downloads:", error)
      }
    }, 4000) // Verifica a cada 4 segundos
  }

  return {
    isImportModalOpen, openImportModal, closeImportModal,
    tracks, currentTrack, isPlaying, currentTime, duration, volume, isSeeking, isRadio,
    queue, originalQueue, currentIndex, isShuffle, loopMode,
    savedPlaylists, currentPlaylistId, loadLibrary, loadAllTracks,
    notification, notify, isQueueOpen, toggleQueue, jumpToQueueIndex, removeFromQueue, reorderQueue,
    playTrack, playRadio, nextTrack, prevTrack, toggleShuffle, toggleLoop, setVolume, toggleMute, setSeek,
    startPlaylistStatusPolling
  }
})