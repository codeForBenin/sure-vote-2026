import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["menu", "iconOpen", "iconClose"]

    connect() {
        // Ensure menu is closed on connect, unless we want to persist state (usually not for nav)
        // But we rely on CSS classes for initial state usually.
    }

    toggle() {
        this.menuTarget.classList.toggle('hidden');
        
        if (this.hasIconOpenTarget && this.hasIconCloseTarget) {
            this.iconOpenTarget.classList.toggle('hidden');
            this.iconCloseTarget.classList.toggle('hidden');
        }
    }
}
