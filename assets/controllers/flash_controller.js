/* stimulusFetch: 'eager' */
import {Controller} from "@hotwired/stimulus";
import {enter, leave} from '../libraries/transition.js'

export default class extends Controller {

    static targets = [ "message" ]

    open = 0;

    messageTargetConnected(element) {
        this.open++;
        setTimeout(async () => {
            await enter(element, 'transition-flash');
            setTimeout(() => this.close(element), 1000);
        }, this.open * 200);
    }

    async close(element) {
        await leave(element, 'transition-flash');
        element.remove();
    }

    messageTargetDisconnected(element) {
        this.open--;
    }
}
