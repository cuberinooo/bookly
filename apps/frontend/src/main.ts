import './styles.scss';
import 'primeicons/primeicons.css';
import router from './router';
import { createApp } from 'vue';
import App from './app/App.vue';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';

// PrimeVue Components
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Card from 'primevue/card';
import DatePicker from 'primevue/datepicker';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import InputNumber from 'primevue/inputnumber';
import {Tooltip} from "primevue";

const app = createApp(App);

app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            prefix: 'p',
            darkModeSelector: 'system',
            cssLayer: false
        }
    }
});

app.use(ToastService);
app.use(ConfirmationService);
app.use(router);

app.component('Button', Button);
app.component('InputText', InputText);
app.component('Card', Card);
app.component('DatePicker', DatePicker);
app.component('DataTable', DataTable);
app.component('Column', Column);
app.component('Toast', Toast);
app.component('ConfirmDialog', ConfirmDialog);
app.component('Dialog', Dialog);
app.component('Select', Select);
app.component('Textarea', Textarea);
app.directive('tooltip', Tooltip);
app.component('InputNumber', InputNumber);

app.mount('#root');
