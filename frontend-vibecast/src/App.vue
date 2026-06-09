<script setup>
import { ref } from 'vue'

// Variáveis reativas para controlar o estado da tela
const spotifyUrl = ref('')
const isLoading = ref(false)
const message = ref('')
const isError = ref(false)

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
    // Comunicação com o nosso Backend Core
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
      throw new Error(data.message || 'Erro ao importar playlist do servidor.')
    }

    message.value = 'Sucesso! Playlist salva no VibeCast.'
    isError.value = false
    spotifyUrl.value = '' // Limpa o campo após o sucesso
  } catch (error) {
    message.value = error.message
    isError.value = true
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-neutral-900 text-white flex flex-col items-center pt-20 font-sans">
    
    <h1 class="text-5xl font-extrabold text-green-500 mb-8 tracking-tighter">VibeCast</h1>

    <div class="bg-neutral-800 p-8 rounded-2xl shadow-2xl w-full max-w-md border border-neutral-700">
      <h2 class="text-xl font-semibold mb-2">Importar Playlist</h2>
      <p class="text-neutral-400 text-sm mb-6">Cole o link público do Spotify para extrair as músicas diretamente para o seu banco de dados.</p>

      <form @submit.prevent="importPlaylist" class="flex flex-col gap-4">
        <input
          v-model="spotifyUrl"
          type="url"
          placeholder="https://open.spotify.com/playlist/..."
          class="w-full bg-neutral-900 border border-neutral-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:border-green-500 transition-colors"
          :disabled="isLoading"
          required
        />

        <button
          type="submit"
          class="w-full bg-green-500 hover:bg-green-400 text-black font-bold py-3 px-4 rounded-lg transition-colors flex justify-center items-center disabled:bg-neutral-600 disabled:text-neutral-400"
          :disabled="isLoading"
        >
          <span v-if="isLoading">Processando...</span>
          <span v-else>Importar Músicas</span>
        </button>
      </form>

      <div 
        v-if="message" 
        class="mt-4 text-center text-sm font-medium p-3 rounded-lg transition-all"
        :class="isError ? 'bg-red-900/50 text-red-400 border border-red-800' : 'bg-green-900/50 text-green-400 border border-green-800'"
      >
        {{ message }}
      </div>
    </div>
    
    
  </div>
</template>