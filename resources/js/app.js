import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import '../css/app.css';

import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import { initTilt } from './tilt';

window.bootstrap = bootstrap;
window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => initTilt());
