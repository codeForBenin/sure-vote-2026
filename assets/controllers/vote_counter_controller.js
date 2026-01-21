import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["input", "total", "submitButton", "warning", "displayTotal"];
    static values = {
        inscrits: Number
    }

    connect() {
        // Initial Calculation
        this.calculate();
        
        // Add event listeners to all inputs
        this.inputTargets.forEach(input => {
            input.addEventListener('input', () => this.calculate());
        });
    }

    calculate() {
        let total = 0;
        this.inputTargets.forEach(input => {
            const val = parseInt(input.value) || 0;
            total += val;
        });

        if (this.hasDisplayTotalTarget) {
            this.displayTotalTarget.textContent = total.toLocaleString('fr-FR');
        }

        const max = this.inscritsValue;
        
        if (total > max) {
            if (this.hasWarningTarget) this.warningTarget.classList.remove('hidden');
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = true;
                this.submitButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            }
            if (this.hasDisplayTotalTarget) this.displayTotalTarget.classList.add('text-red-600');
        } else {
            if (this.hasWarningTarget) this.warningTarget.classList.add('hidden');
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = false;
                this.submitButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            if (this.hasDisplayTotalTarget) this.displayTotalTarget.classList.remove('text-red-600');
        }
    }
}
