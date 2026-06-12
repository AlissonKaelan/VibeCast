<script setup>
import { Splitpanes, Pane } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'

// Importando a nossa nova armada de componentes fatiados!
import Sidebar from './components/Sidebar.vue'
import Import from './components/Import.vue'
import TrackList from './components/TrackList.vue'
import PlayerBar from './components/PlayerBar.vue'
</script>

<template>
  <div class="h-screen w-screen bg-neutral-900 text-white flex flex-col overflow-hidden font-sans select-none">
    
    <div class="flex-1 overflow-hidden pb-24">
      <Splitpanes class="default-theme">
        
        <Pane min-size="15" size="22" max-size="35" class="bg-neutral-950 border-r border-neutral-800/80">
          <Sidebar />
        </Pane>
        
        <Pane class="bg-neutral-900 overflow-y-auto p-8 flex flex-col items-center">
          
          <Import />
          
          <TrackList />
          
        </Pane>

      </Splitpanes>
    </div>

    <div class="fixed bottom-0 left-0 right-0 h-24 bg-neutral-950 border-t border-neutral-800 z-50">
      <PlayerBar />
    </div>

  </div>
</template>

<style>
/* Estilização para deixar o divisor do Splitpanes sutil (Estilo Dark/Spotify) */
.splitpanes__splitter {
  background-color: transparent !important;
  border-left: 1px solid #262626 !important;
  width: 3px !important;
  cursor: col-resize;
}

/* Quando o usuário passa o mouse em cima da linha divisória, ela brilha em verde */
.splitpanes__splitter:hover {
  background-color: #3b82f6 !important;
  transition: background-color 0.3s ease;
}
</style>