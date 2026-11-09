import { generateCsrfToken } from './controllers/csrf_protection_controller.js';

document.addEventListener('submit', function (event) {
    generateCsrfToken(event.target);
}, true);
