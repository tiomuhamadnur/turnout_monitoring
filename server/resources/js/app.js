import '../scss/app.scss';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'bootstrap';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

useThemeStore().init();
useAuthStore().hydrate();

app.mount('#app');
