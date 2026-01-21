import { Controller } from '@hotwired/stimulus';
// Import lodash debounce si disponible, sinon on fait notre propre debounce simple

export default class extends Controller {
    static targets = [
        "bureauxContainer",
        "jsonInput",
        "totalInput",
        "centreIdInput",
        "searchInput",
        "searchResults"
    ];

    connect() {
        console.log("Signalement controller V2 connected");
        this.debounceTimer = null;

        // Écouter les changements sur le total pour revalider
        if (this.hasTotalInputTarget) {
            this.totalInputTarget.addEventListener('input', () => this.updateJson());
        }

        // Auto-load si l'ID est déjà présent (Cas Assesseur)
        // Auto-load si l'ID est déjà présent (Cas Assesseur)
        if (this.hasCentreIdInputTarget && this.centreIdInputTarget.value) {
            this.loadBureaux(this.centreIdInputTarget.value);
            // On cache la recherche si elle existe
            if (this.hasSearchInputTarget) {
                const container = this.searchInputTarget.closest('.relative');
                if (container) container.style.display = 'none';
            }
        }
    }

    onSearchInput(event) {
        const query = event.target.value;

        if (this.debounceTimer) clearTimeout(this.debounceTimer);

        if (query.length < 2) {
            this.searchResultsTarget.classList.add('hidden');
            this.searchResultsTarget.innerHTML = '';
            return;
        }

        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, 300); // 300ms debounce
    }

    async performSearch(query) {
        this.searchResultsTarget.innerHTML = '<div class="p-3 text-slate-500 text-sm"><i class="fas fa-spinner fa-spin"></i> Recherche...</div>';
        this.searchResultsTarget.classList.remove('hidden');

        try {
            const response = await fetch(`/api/search/centres?q=${encodeURIComponent(query)}`);
            const results = await response.json();

            this.renderSearchResults(results);
        } catch (error) {
            console.error(error);
            this.searchResultsTarget.innerHTML = '<div class="p-3 text-red-500 text-sm">Erreur réseau</div>';
        }
    }

    renderSearchResults(results) {
        this.searchResultsTarget.innerHTML = '';

        if (results.length === 0) {
            this.searchResultsTarget.innerHTML = '<div class="p-3 text-slate-500 text-sm">Aucun centre trouvé.</div>';
            return;
        }

        const ul = document.createElement('ul');
        ul.className = 'divide-y divide-slate-100';

        results.forEach(centre => {
            const li = document.createElement('li');
            li.className = 'p-3 hover:bg-slate-50 cursor-pointer transition-colors';
            li.innerHTML = `
                <div class="font-bold text-slate-800">${centre.nom}</div>
                <div class="text-xs text-slate-500">${centre.location}</div>
            `;
            li.addEventListener('click', () => this.selectCentre(centre));
            ul.appendChild(li);
        });

        this.searchResultsTarget.appendChild(ul);
    }

    selectCentre(centre) {
        // 1. Remplir l'input ID caché
        this.centreIdInputTarget.value = centre.id;

        // 2. Mettre le nom dans la barre de recherche et cacher les résultats
        this.searchInputTarget.value = centre.nom;
        this.searchResultsTarget.classList.add('hidden');

        this.loadBureaux(centre.id);
    }

    async loadBureaux(centreId) {
        this.bureauxContainerTarget.innerHTML = '<div class="text-center py-4 text-gray-500"><i class="fas fa-spinner fa-spin"></i> Chargement des postes de vote...</div>';

        try {
            const response = await fetch(`/api/centre/${centreId}/bureaux`);
            const bureaux = await response.json();
            this.renderBureauxFields(bureaux);
        } catch (error) {
            console.error(error);
            this.bureauxContainerTarget.innerHTML = '<div class="text-red-500">Erreur lors du chargement des bureaux.</div>';
        }
    }

    renderBureauxFields(bureaux) {
        this.bureauxContainerTarget.innerHTML = '';

        if (bureaux.length === 0) {
            this.bureauxContainerTarget.innerHTML = '<div class="p-4 bg-yellow-50 text-yellow-700 rounded-lg">Aucun bureau de vote trouvé pour ce centre.</div>';
            return;
        }

        const title = document.createElement('h3');
        title.className = "text-lg font-bold text-slate-800 mb-4 mt-6";
        title.innerText = `Détail par Poste de Vote (${bureaux.length} postes)`;
        this.bureauxContainerTarget.appendChild(title);

        bureaux.forEach(bureau => {
            const row = document.createElement('div');
            row.className = "flex items-center gap-4 mb-3 p-3 bg-white border border-slate-200 rounded-lg shadow-sm";

            row.innerHTML = `
                <div class="flex-1">
                    <label class="block text-sm font-bold text-slate-700">${bureau.nom}</label>
                    <span class="text-xs text-slate-500">Code: ${bureau.code}</span>
                </div>
                <div class="w-32">
                    <input type="number" 
                           class="w-full px-4 p-2 rounded-xl border border-slate-200 focus:ring-4 focus:ring-benin-green/20 focus:border-benin-green transition-all outline-hidden" 
                           placeholder="Inscrits"
                           min="0"
                           data-bureau-id="${bureau.id}"
                           data-action="input->signalement#updateJson"
                    >
                </div>
            `;
            this.bureauxContainerTarget.appendChild(row);
        });

        // Pre-remplir les champs si JSON existe (mode édition)
        if (this.hasJsonInputTarget && this.jsonInputTarget.value) {
            try {
                const data = JSON.parse(this.jsonInputTarget.value);
                if (Array.isArray(data)) {
                    data.forEach(item => {
                        const input = this.bureauxContainerTarget.querySelector(`input[data-bureau-id="${item.bureau_id}"]`);
                        if (input) {
                            input.value = item.inscrits;
                        }
                    });
                }
            } catch (e) {
                console.error("Error parsing existing JSON", e);
            }
        }

        this.updateJson();
    }

    updateJson() {
        const inputs = this.bureauxContainerTarget.querySelectorAll('input[type="number"]');
        const data = [];
        let totalCalculated = 0;

        inputs.forEach(input => {
            const val = parseInt(input.value) || 0;
            if (val > 0) {
                totalCalculated += val;
                data.push({
                    bureau_id: input.dataset.bureauId,
                    inscrits: val
                });
            }
        });

        this.jsonInputTarget.value = JSON.stringify(data);

        // Validation en temps réel
        this.validateTotal(totalCalculated);
    }

    validateTotal(sum) {
        if (!this.hasTotalInputTarget) return;

        const totalSaisi = parseInt(this.totalInputTarget.value) || 0;

        // Créer ou récupérer le conteneur de message
        let msgEl = this.totalInputTarget.parentNode.querySelector('.validation-message');
        if (!msgEl) {
            msgEl = document.createElement('div');
            msgEl.className = 'validation-message mt-2 text-sm font-bold';
            this.totalInputTarget.parentNode.appendChild(msgEl);
        }

        // Reset
        msgEl.innerHTML = '';
        msgEl.className = 'validation-message mt-2 text-sm font-bold'; // Reset classes

        if (totalSaisi > 0) {
            if (sum > totalSaisi) {
                // Erreur critique
                msgEl.classList.add('text-red-600');
                msgEl.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i> La somme des postes (${sum}) dépasse le total indiqué (${totalSaisi}) de ${sum - totalSaisi} !`;
            } else if (sum < totalSaisi && sum > 0) {
                // Warning
                msgEl.classList.add('text-orange-600');
                msgEl.innerHTML = `<i class="fas fa-info-circle mr-1"></i> La somme des postes (${sum}) est inférieure au total (${totalSaisi}). Il manque ${totalSaisi - sum} inscrits.`;
            } else if (sum === totalSaisi) {
                // Succès
                msgEl.classList.add('text-green-600');
                msgEl.innerHTML = `<i class="fas fa-check-circle mr-1"></i> Le compte est bon !`;
            }
        } else if (sum > 0) {
            // Suggestion si total vide
            msgEl.classList.add('text-blue-600');
            msgEl.innerHTML = `<span class="cursor-pointer hover:underline">Utiliser la somme calculée (${sum}) comme total ?</span>`;

            msgEl.querySelector('span')?.addEventListener('click', () => {
                this.totalInputTarget.value = sum;
                this.validateTotal(sum);
            });
        }
    }
}
