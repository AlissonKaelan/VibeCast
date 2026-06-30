<template>
  <div class="p-6">
    <h1 class="text-3xl font-bold text-white mb-6">Web Rádios</h1>

    <div class="bg-zinc-800/50 p-6 rounded-xl mb-8 border border-zinc-700/50">
      <h2 class="text-xl font-semibold text-white mb-4">Adicionar Nova Estação</h2>
      <form @submit.prevent="addRadio" class="flex flex-col md:flex-row gap-4">
        <input v-model="newRadio.name" type="text" placeholder="Nome (Ex: Lofi Girl 24/7)" class="flex-1 bg-zinc-900 border border-zinc-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-green-500 transition" required>
        <input v-model="newRadio.stream_url" type="url" placeholder="URL do Stream (.mp3, .m3u8)" class="flex-1 bg-zinc-900 border border-zinc-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-green-500 transition" required>
        <input v-model="newRadio.logo_url" type="url" placeholder="URL da Logo (Opcional)" class="flex-1 bg-zinc-900 border border-zinc-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-green-500 transition">
        
        <button type="submit" class="bg-green-500 text-black font-bold px-8 py-3 rounded-lg hover:bg-green-400 hover:scale-105 transition-all shadow-lg">
          Salvar
        </button>
      </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <div v-for="radio in radios" :key="radio.id" class="bg-zinc-800/40 hover:bg-zinc-800 p-4 rounded-xl transition-all group relative border border-zinc-700/30 hover:border-zinc-600">
        
        <div class="relative w-full aspect-square mb-4 rounded-lg overflow-hidden shadow-lg bg-zinc-900 flex items-center justify-center">
            <img v-if="radio.logo_url" :src="radio.logo_url" class="w-full h-full object-cover">
            <img v-else :src="'https://ui-avatars.com/api/?name=' + radio.name + '&background=1db954&color=fff&size=256'" class="w-full h-full object-cover">
        </div>

        <button @click="playRadio(radio)" class="absolute bottom-20 right-6 w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-black opacity-0 group-hover:opacity-100 transition-all shadow-xl hover:scale-105 hover:bg-green-400 z-10">
          <Play class="w-6 h-6 fill-current ml-1" />
        </button>

        <div class="flex justify-between items-start mt-2">
          <div class="overflow-hidden">
            <h3 class="text-white font-bold truncate text-lg">{{ radio.name }}</h3>
            <p class="text-zinc-400 text-sm flex items-center gap-1 mt-1">
              <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span> Ao Vivo
            </p>
          </div>
          <button @click="deleteRadio(radio.id)" class="text-zinc-500 hover:text-red-500 transition-colors p-1" title="Excluir Rádio">
            <Trash2 class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Play, Trash2 } from 'lucide-vue-next';
import { usePlayerStore } from '../stores/playerStore';
const playerStore = usePlayerStore();
const radios = ref([]);
const newRadio = ref({ name: '', stream_url: '', logo_url: '' });

// 1. Busca as rádios do Laravel
const fetchRadios = async () => {
  try {
    const res = await fetch('http://localhost:8000/api/radios');
    radios.value = await res.json();
  } catch (error) {
    console.error("Erro ao buscar rádios:", error);
  }
};

// 2. Salva uma rádio nova
const addRadio = async () => {
  try {
    const res = await fetch('http://localhost:8000/api/radios', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newRadio.value)
    });
    
    if (res.ok) {
      newRadio.value = { name: '', stream_url: '', logo_url: '' }; // Limpa o formulário
      fetchRadios(); // Recarrega a lista
    } else {
      alert("Erro! Verifique se a URL já não foi adicionada.");
    }
  } catch (error) {
    console.error("Erro ao adicionar:", error);
  }
};

// 3. Exclui uma rádio
const deleteRadio = async (id) => {
  if (!confirm("Tem certeza que deseja apagar esta estação?")) return;
  try {
    await fetch(`http://localhost:8000/api/radios/${id}`, { method: 'DELETE' });
    fetchRadios(); // Recarrega a lista
  } catch (error) {
    console.error("Erro ao deletar:", error);
  }
};

// 4. Manda o Player tocar a rádio!
const playRadio = (radio) => {
  playerStore.playRadio(radio);
};

onMounted(() => {
  fetchRadios();
});
</script>