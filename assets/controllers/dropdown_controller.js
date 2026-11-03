import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this._closeOnOutsideClick = this._closeOnOutsideClick.bind(this);
    }

    toggle() {
        this.menuTarget.classList.toggle('hidden');
        if (!this.menuTarget.classList.contains('hidden')) {
            document.addEventListener('click', this._closeOnOutsideClick);
        }
    }

    _closeOnOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.add('hidden');
            document.removeEventListener('click', this._closeOnOutsideClick);
        }
    }

    disconnect() {
        document.removeEventListener('click', this._closeOnOutsideClick);
    }
}
