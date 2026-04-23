import './styles.scss';
import router from './router';
import { createApp } from 'vue';
import App from './app/App.vue';
import PrimeVue from 'primevue/config';

const app = createApp(App);
app.use(PrimeVue);
app.use(router);
app.mount('#root');
