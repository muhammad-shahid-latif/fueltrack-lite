import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';

// Fonts are bundled with the app so they work without internet at the station.
import '@fontsource/figtree/400.css';
import '@fontsource/figtree/500.css';
import '@fontsource/figtree/600.css';
import '@fontsource/figtree/700.css';
import '@fontsource/chakra-petch/500.css';
import '@fontsource/chakra-petch/600.css';
import '@fontsource/chakra-petch/700.css';
import '../css/app.css';

import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import { initTilt } from './tilt';

window.bootstrap = bootstrap;
window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => initTilt());
