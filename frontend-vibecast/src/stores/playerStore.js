import { ref } from 'vue'
import { defineStore } from 'pinia'

export const usePlayerStore = defineStore('player', () => {
  const tracks = ref([])
  const currentTrack = ref(null)
  const isPlaying = ref(false)
  const currentTime = ref(0)
  const duration = ref(0)
  const volume = ref(1)
  const isSeeking = ref(false) 
  let audioPlayer = null 

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

  // NOVAS FUNÇÕES: O Store controla o próprio áudio!
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

  return {
    tracks, currentTrack, isPlaying, currentTime, duration, volume, isSeeking,
    playTrack, nextTrack, prevTrack, setVolume, toggleMute, setSeek
  }
})