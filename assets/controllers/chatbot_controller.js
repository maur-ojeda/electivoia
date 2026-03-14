import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'messages', 'widget', 'toggle'];
    static values = { url: String, clearUrl: String };

    toggle() {
        this.widgetTarget.classList.toggle('hidden');
        if (!this.widgetTarget.classList.contains('hidden')) {
            this.inputTarget.focus();
        }
    }

    async send(event) {
        event.preventDefault();
        const message = this.inputTarget.value.trim();
        if (!message) return;

        this.inputTarget.value = '';
        this.addMessage(message, 'user');
        this.setLoading(true);

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message }),
            });
            const data = await response.json();
            this.addMessage(data.message || 'Error al obtener respuesta.', 'bot');
        } catch {
            this.addMessage('Error de conexión.', 'bot');
        } finally {
            this.setLoading(false);
        }
    }

    async clearHistory() {
        await fetch(this.clearUrlValue, { method: 'POST' });
        this.messagesTarget.innerHTML = '';
    }

    addMessage(text, role) {
        const div = document.createElement('div');
        div.className = `chat ${role === 'user' ? 'chat-end' : 'chat-start'} mb-2`;
        div.innerHTML = `<div class="chat-bubble ${role === 'user' ? 'chat-bubble-primary' : 'chat-bubble-ghost'} text-sm">${text}</div>`;
        this.messagesTarget.appendChild(div);
        this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
    }

    setLoading(loading) {
        this.inputTarget.disabled = loading;
    }
}
