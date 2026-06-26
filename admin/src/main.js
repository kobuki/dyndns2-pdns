import { createApp } from 'vue'
import { createVuestic } from 'vuestic-ui'
import 'vuestic-ui/css'
import App from './App.vue'
import router from './router/index.js'

const app = createApp(App)
app.use(router)
app.use(createVuestic())
app.mount('#app')
