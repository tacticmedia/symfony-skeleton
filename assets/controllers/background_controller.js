/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

// CSP does not govern CSSOM writes, so the colour needs no `style` attribute or nonce.
export default class extends Controller {
    static values = { color: String };

    connect() {
        this.element.style.backgroundColor = this.colorValue;
    }
}
