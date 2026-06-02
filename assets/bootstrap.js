import { startStimulusApp } from '@symfony/stimulus-bundle';

const app = startStimulusApp();

// Register local controllers manually
import ChatbotController from './controllers/chatbot_controller.js';
import ConfirmController from './controllers/confirm_controller.js';
import FlashController from './controllers/flash_controller.js';

app.register('chatbot', ChatbotController);
app.register('confirm', ConfirmController);
app.register('flash', FlashController);
