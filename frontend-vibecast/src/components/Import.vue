<script setup>
import { ref } from 'vue'
import { Loader2 } from 'lucide-vue-next'

// Variáveis exclusivas da importação
const spotifyUrl = ref('')
const isLoading = ref(false)
const message = ref('')
const isError = ref(false)

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
    if (!response.ok) throw new Error(data.message || 'Erro ao importar playlist.')

    message.value = 'Sucesso! Músicas extraídas com sucesso.'
    isError.value = false
    spotifyUrl.value = ''

    // NOTA: No próximo passo (Pinia), nós vamos mandar os dados (data.tracks) para o cérebro global aqui!

  } catch (error) {
    message.value = error.message
    isError.value = true
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="bg-neutral-800 p-8 rounded-2xl shadow-2xl w-full max-w-md border border-neutral-700">
    <h2 class="text-xl font-semibold mb-2">Importar Playlist</h2>
    <p class="text-neutral-400 text-sm mb-6">Cole o link público do Spotify para extrair os metadados para a base local.</p>

    <form @submit.prevent="importPlaylist" class="flex flex-col gap-4">
      <input v-model="spotifyUrl" type="url" placeholder="Cole o link da playlist aqui..."
        class="w-full bg-neutral-900 border border-neutral-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:border-blue-500 transition-colors"
        :disabled="isLoading" required />

      <button type="submit"
        class="w-full bg-blue-500 hover:bg-blue-400 text-black font-bold py-3 px-4 rounded-lg transition-colors flex justify-center items-center disabled:bg-neutral-600 disabled:text-neutral-400"
        :disabled="isLoading">
        <Loader2 v-if="isLoading" class="w-5 h-5 animate-spin mr-2" />
        <span>{{ isLoading ? 'Processando...' : 'Importar Músicas' }}</span>
      </button>
    </form>

    <div v-if="message" class="mt-4 text-center text-sm font-medium p-3 rounded-lg transition-all"
      :class="isError ? 'bg-red-900/50 text-red-400 border border-red-800' : 'bg-blue-900/50 text-blue-400 border border-blue-800'">
      {{ message }}
    </div>
  </div>
</template>