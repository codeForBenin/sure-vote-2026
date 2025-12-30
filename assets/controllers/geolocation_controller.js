import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['lat', 'lon', 'status', 'submit'];
    static values = {
        allowOnError: { type: Boolean, default: false },
        devMode: { type: Boolean, default: false }
    }

    connect() {
        this.options = {
            enableHighAccuracy: true,
            timeout: 5000,
            maximumAge: 0
        };

        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(
                (pos) => this.success(pos.coords.latitude, pos.coords.longitude),
                (err) => this.error(err.message),
                this.options
            );
        } else {
            this.error("Géolocalisation non supportée");
        }
    }

    success(lat, lon) {
        if (this.hasLatTarget) this.latTarget.value = lat;
        if (this.hasLonTarget) this.lonTarget.value = lon;

        this.statusTarget.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-benin-green" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
            </svg>
            Position récupérée avec succès
        `;
        this.statusTarget.classList.remove('bg-slate-50', 'bg-red-50', 'text-slate-500', 'text-red-600');
        this.statusTarget.classList.add('bg-benin-green/5', 'text-benin-green');

        this.enableSubmit();
    }

    error(msg) {
        let content = `
            <div class="flex flex-col items-center gap-2">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <span>Erreur: ${msg}</span>
                </div>
        `;

        // Add simulation button only if in dev mode (passed as value or detected somehow, here simple check)
        if (this.devModeValue) {
             content += `<button type="button" data-action="click->geolocation#simulate" class="text-[10px] underline uppercase font-bold text-slate-400 hover:text-benin-green">Simuler ma position (Dev uniquement)</button>`;
        }
        
        content += `</div>`;
        
        this.statusTarget.innerHTML = content;
        
        this.statusTarget.classList.remove('bg-slate-50', 'bg-benin-green/5', 'text-slate-500', 'text-benin-green');
        this.statusTarget.classList.add('bg-red-50', 'text-red-600');

        if (this.allowOnErrorValue) {
            this.enableSubmit();
        }
    }

    enableSubmit() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = false;
            this.submitTarget.classList.remove('bg-slate-200', 'text-slate-500', 'cursor-not-allowed');
            this.submitTarget.classList.add('bg-benin-green', 'text-white', 'hover:bg-benin-green/90', 'hover:scale-[1.02]');
        }
    }

    simulate() {
        this.success(6.3667, 2.4167); // Cotonou default
    }
}
