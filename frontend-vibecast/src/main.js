import { createApp } from 'vue'
import { createPinia } from 'pinia' // Importando o Pinia
import './style.css'
import App from './App.vue'

const app = createApp(App)
const pinia = createPinia() // Criando a instância do Pinia

app.use(pinia) // Injetando o cérebro global na aplicação
app.mount('#app')