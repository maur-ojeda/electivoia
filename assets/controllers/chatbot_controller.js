import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'messages', 'widget'];
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
        this._pendingMessage = message;
        this.setLoading(true);

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 35000);

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message }),
                signal: controller.signal,
            });
            clearTimeout(timeoutId);
            const data = await response.json();
            this.addMessage(data.message || 'Error al obtener respuesta.', 'bot');
            this._pendingMessage = null;
        } catch (err) {
            clearTimeout(timeoutId);
            const isTimeout = err.name === 'AbortError';
            this.addRetryMessage(isTimeout
                ? 'El asistente tardó demasiado en responder. ¿Reintentamos?'
                : 'Error de conexión. ¿Reintentamos?'
            );
        } finally {
            this.setLoading(false);
        }
    }

    retry(event) {
        const message = this._pendingMessage;
        if (!message) return;
        this._pendingMessage = null;
        event.target.closest('.chat')?.remove();
        this.inputTarget.value = message;
        this.send(new Event('submit'));
    }

    addRetryMessage(text) {
        const div = document.createElement('div');
        div.className = 'chat chat-start mb-2';
        div.innerHTML = `<div class="chat-bubble chat-bubble-ghost text-sm">
            ${text}
            <button data-action="click->chatbot#retry" class="btn btn-xs btn-outline ml-2 mt-1">Reintentar</button>
        </div>`;
        this.messagesTarget.appendChild(div);
        this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
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
