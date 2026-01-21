import { Controller } from '@hotwired/stimulus';

/*
 * This is the Stimulus controller for flash messages.
 *
 * Any element with data-controller="flash" will be dismissible.
 * Usage:
 * <div data-controller="flash">
 *   ... content ...
 *   <button data-action="flash#close">Close</button>
 * </div>
 */
export default class extends Controller {
    connect() {
        // Optional: Auto - dismiss after 5 seconds if needed
        setTimeout(() => {
            this.close();
        }, 5000);
    }

    close() {
        this.element.style.transition = 'opacity 0.3s ease-out';
        this.element.style.opacity = '0';

        setTimeout(() => {
            this.element.remove();
        }, 300);
    }
}
