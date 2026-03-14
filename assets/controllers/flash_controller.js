import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { type: String, message: String };

    connect() {
        const alertClass = this.typeValue === 'success' ? 'alert-success'
            : this.typeValue === 'error' ? 'alert-error'
            : this.typeValue === 'warning' ? 'alert-warning'
            : 'alert-info';

        this.element.classList.add('alert', alertClass);

        const timeout = this.typeValue === 'error' ? 0 : 4000;
        if (timeout > 0) {
            setTimeout(() => this.element.remove(), timeout);
        }
    }

    dismiss() {
        this.element.remove();
    }
}
