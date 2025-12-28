import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'text', 'spinner'];

    connect() {
        // Le contrôleur est connecté
        console.log('ImportController connected');

    }

    submit(event) {
        // Feedback visuel immédiat
        this.buttonTarget.classList.add('opacity-75', 'cursor-not-allowed');
        this.buttonTarget.style.pointerEvents = 'none'; // Désactive les clics

        if (this.hasTextTarget) {
            this.textTarget.innerText = 'Importation en cours...';
        }

        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.remove('hidden');
        }
    }
}
