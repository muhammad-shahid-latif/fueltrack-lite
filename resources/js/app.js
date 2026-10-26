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
import * as Turbo from '@hotwired/turbo';
import Alpine from 'alpinejs';
import { http, HttpError } from './http';
import { armAll, toast } from './toast';
import { initTilt } from './tilt';
import { customerFilter, receivePayment } from './khata';
import owedGive from './owed-give';
import owedSplit from './owed-split';
import shiftPlanner from './shift-planner';
import pumpBuilder from './pump-builder';

window.bootstrap = bootstrap;
window.Alpine = Alpine;
// Helpers for the small inline Alpine components in Blade views.
window.FT = { http, HttpError, toast };

// Turbo turns every link and form into a background request and swaps the page
// in place: no white flash, no re-downloading CSS/JS, and links prefetch on hover.
Turbo.config.drive.progressBarDelay = 120;

Alpine.data('owedSplit', owedSplit);
Alpine.data('owedGive', owedGive);
Alpine.data('receivePayment', receivePayment);
Alpine.data('customerFilter', customerFilter);
Alpine.data('shiftPlanner', shiftPlanner);
Alpine.data('pumpBuilder', pumpBuilder);

Alpine.start();

// A form that returns to the same page updates it in place, without the entrance animation.
document.addEventListener('turbo:before-render', (event) => {
    if (document.documentElement.dataset.turboVisitDirection === 'none') {
        event.detail.newBody.classList.add('is-refresh');
    }
});

// Runs on the first load and after every Turbo page swap.
document.addEventListener('turbo:load', () => {
    initTilt();
    armAll();
});

