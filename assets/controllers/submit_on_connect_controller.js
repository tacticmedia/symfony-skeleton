import { Controller } from '@hotwired/stimulus';

/*
 * Submits the form it is attached to as soon as it connects. Used by the dev
 * login-confirmation page to complete the one-click sign-in without a click.
 */
export default class extends Controller {
    connect() {
        this.element.requestSubmit();
    }
}
