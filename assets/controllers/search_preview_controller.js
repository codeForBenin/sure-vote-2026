import { Controller } from '@hotwired/stimulus';

/*
 * This is an example Stimulus controller!
 *
 * Any element with a data-controller="search-preview" attribute will cause
 * this controller to be executed. The name "search-preview" comes from the filename:
 * search_preview_controller.js -> "search-preview"
 *
 * Delete this file or adapt it for your use!
 */
export default class extends Controller {
    static targets = ['input', 'results'];
    static values = { url: String };

    connect() {
        this.debouncedSearch = this.debounce(this.search.bind(this), 300);
    }

    onInput(event) {
        this.debouncedSearch(event.target.value);
    }

    search(query) {
        if (!query || query.length < 2) {
            this.resultsTarget.innerHTML = '';
            return;
        }

        const url = `${this.urlValue}?q=${encodeURIComponent(query)}`;

        fetch(url)
            .then(response => response.text())
            .then(html => {
                this.resultsTarget.innerHTML = html;
            })
            .catch(error => {
                console.error('Error fetching suggestions:', error);
            });
    }

    // Fonction de debounce
    debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    }
    
    // Fermer les suggestions si on clique en dehors
    close(event) {
         if (!this.element.contains(event.target)) {
            this.resultsTarget.innerHTML = '';
        }
    }
}
