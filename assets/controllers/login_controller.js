import { Controller } from '@hotwired/stimulus';

/*
 * Enhances the magic-link login form (LoginFormType).
 *
 * On connect it:
 *  - fills the hidden timezone field with the browser's IANA timezone,
 *  - restores the saved email and ticks "remember me" when one was stored,
 *  - keeps the saved email in sync while the user types / toggles "remember me",
 *  - reveals the "working" message once the form is submitted.
 */
export default class extends Controller {
    static targets = ['timezone', 'remember', 'email', 'working'];

    connect() {
        if (this.hasTimezoneTarget) {
            this.timezoneTarget.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
        }

        this.onRemember = this.remember.bind(this);
        this.onSubmit = this.showWorking.bind(this);

        if (this.hasEmailTarget) {
            this.emailTarget.addEventListener('keyup', this.onRemember);
        }
        if (this.hasRememberTarget) {
            this.rememberTarget.addEventListener('change', this.onRemember);
        }
        this.element.addEventListener('submit', this.onRemember);
        this.element.addEventListener('submit', this.onSubmit);

        this.restore();
    }

    disconnect() {
        if (this.hasEmailTarget) {
            this.emailTarget.removeEventListener('keyup', this.onRemember);
        }
        if (this.hasRememberTarget) {
            this.rememberTarget.removeEventListener('change', this.onRemember);
        }
        this.element.removeEventListener('submit', this.onRemember);
        this.element.removeEventListener('submit', this.onSubmit);
    }

    remember() {
        if (!this.hasEmailTarget || !this.hasRememberTarget) {
            return;
        }

        if (this.rememberTarget.checked) {
            window.localStorage.setItem('email', this.emailTarget.value);
        } else {
            window.localStorage.removeItem('email');
        }
    }

    showWorking() {
        if (this.hasWorkingTarget) {
            this.workingTarget.classList.remove('hidden');
        }
    }

    restore() {
        if (!this.hasEmailTarget || !this.hasRememberTarget) {
            return;
        }

        const email = window.localStorage.getItem('email');
        if (email) {
            this.emailTarget.value = email;
            this.rememberTarget.checked = true;
        }
    }
}
