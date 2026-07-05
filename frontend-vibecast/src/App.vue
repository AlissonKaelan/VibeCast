<script setup>
import { ref } from 'vue'
import { Splitpanes, Pane } from 'splitpanes'
import 'splitpanes/dist/splitpanes.css'

import Sidebar from './components/Sidebar.vue'
import Import from './components/Import.vue'
import TrackList from './components/TrackList.vue'
import PlayerBar from './components/PlayerBar.vue'
import QueuePanel from './components/QueuePanel.vue'
import Radios from './components/Radios.vue'
import SettingsModal from './components/SettingsModal.vue'

import { usePlayerStore } from './stores/playerStore'
const playerStore = usePlayerStore()

const currentView = ref('library')
</script>

<template>
  <div class="h-screen w-screen bg-gradient-to-br from-slate-950 to-black text-white flex flex-col overflow-hidden font-sans select-none">
    
    <div 
      v-if="playerStore.notification.show" 
      class="fixed top-6 right-6 z-[200] px-6 py-4 rounded-xl shadow-2xl flex items-center gap-3 transform transition-all animate-slide-left"
      :class="playerStore.notification.type === 'error' ? 'bg-red-600/90 border border-red-500 text-white' : 'bg-green-500/90 border border-green-400 text-black'"
    >
      <span class="font-bold text-sm tracking-wide">{{ playerStore.notification.text }}</span>
    </div>

    <div class="flex-1 overflow-hidden pb-24">
      <Splitpanes class="h-full w-full">
        
        <Pane min-size="15" size="22" max-size="35" class="bg-black/20 backdrop-blur-md border-r border-neutral-800/80">
          <Sidebar :currentView="currentView" @change-view="currentView = $event" />
        </Pane>
        
        <Pane class="bg-transparent">
          <div class="h-full w-full overflow-y-auto p-8 flex flex-col items-center invisible-scrollbar">
            <TrackList v-if="currentView === 'library'" />
            <Radios v-else-if="currentView === 'radios'" />
          </div>
        </Pane>

      </Splitpanes>
    </div>

    <div class="fixed bottom-0 left-0 right-0 h-24 bg-black/80 backdrop-blur-lg border-t border-neutral-800 z-50">
      <PlayerBar />
    </div>

    <Import />
    <QueuePanel />
    <SettingsModal />
  </div>
</template>

<style>
.splitpanes__splitter {
  background-color: transparent !important;
  border-left: 1px solid #262626 !important;
  width: 3px !important;
  cursor: col-resize;
}
.splitpanes__splitter:hover {
  background-color: #3b82f6 !important;
  transition: background-color 0.3s ease;
}
@keyframes slideLeft { 
  from { opacity: 0; transform: translateX(20px); } 
  to { opacity: 1; transform: translateX(0); } 
} 
.animate-slide-left { 
  animation: slideLeft 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
}
</style>