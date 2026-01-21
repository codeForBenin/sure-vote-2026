import { Controller } from '@hotwired/stimulus';
import { toPng } from 'html-to-image';

export default class extends Controller {
    static targets = ['select', 'resultsArea', 'placeholder', 'loading', 'circoName', 'circoVilles', 'circoSieges', 'projectionsLink'];

    async connect() {
        console.log('PublicResultsController connected');
        // Au chargement, on peuple le select avec les circonscriptions via l'API
        try {
            console.log('Fetching circonscriptions...');
            const response = await fetch('/api/public/circonscriptions');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Circonscriptions loaded:', data.length);

            this.populateSelect(data);
        } catch (error) {
            console.error('Erreur chargement circonscriptions:', error);
            // Optionnel : afficher un message d'erreur dans le select
            const select = this.selectTarget;
            select.innerHTML = '<option>Erreur de chargement</option>';
        }
    }

    populateSelect(circonscriptions) {
        // Trier par code
        circonscriptions.sort((a, b) => a.code.localeCompare(b.code));

        const select = this.selectTarget;
        select.innerHTML = '<option value="">Choisir une circonscription...</option>';

        circonscriptions.forEach(circo => {
            const option = document.createElement('option');
            option.value = circo.code;
            option.textContent = `${circo.nom} (${circo.code})`;
            select.appendChild(option);
        });

        select.disabled = false;
    }

    async onCircoChange(event) {
        const code = event.target ? event.target.value : event; // Support call with direct value

        if (!code) {
            this.showPlaceholder();
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(`/api/public/resultats/${code}`);

            if (!response.ok) throw new Error('Erreur réseau');

            const data = await response.json();
            this.renderResults(data);

        } catch (error) {
            console.error('Erreur chargement résultats:', error);
            this.resultsAreaTarget.innerHTML = `<div class="text-red-500 text-center p-4">Impossible de charger les résultats.</div>`;
        }
    }

    async share(event) {
        // Animation feedback
        const btn = event.currentTarget.querySelector('i');
        const originalIcon = btn.className;
        btn.className = 'fas fa-spinner fa-spin text-lg';

        try {
            // Utilisation de html-to-image qui supporte mieux le CSS moderne
            const dataUrl = await toPng(this.element, {
                backgroundColor: '#ffffff',
                cacheBust: true,
            });

            // Conversion dataURL -> Blob pour le partage natif
            const blob = await (await fetch(dataUrl)).blob();
            const file = new File([blob], 'resultats-sure-vote.png', { type: 'image/png' });

            // Essayer l'API de partage native (Mobile)
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        files: [file],
                        title: 'Résultats Sure Vote',
                        text: `Résultats pour ${this.circoNameTarget.textContent}`
                    });
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        this.downloadDataUrl(dataUrl);
                    }
                }
            } else {
                // Fallback desktop : téléchargement direct
                this.downloadDataUrl(dataUrl);
            }

            // Reset icon
            btn.className = originalIcon;

        } catch (error) {
            console.error('Erreur génération image:', error);
            btn.className = originalIcon;
            alert('Impossible de générer l\'image : ' + error.message);
        }
    }

    downloadDataUrl(dataUrl) {
        const link = document.createElement('a');
        link.download = `sure-vote-${this.selectTarget.value || 'resultats'}.png`;
        link.href = dataUrl;
        document.body.appendChild(link); // Fix for Firefox: element must be in DOM
        link.click();
        document.body.removeChild(link);
    }

    refresh(event) {
        const code = this.selectTarget.value;
        if (code) {
            // Animation du bouton refresh si présent
            const btn = event.currentTarget.querySelector('i');
            if (btn) btn.classList.add('animate-spin');

            this.onCircoChange(code).then(() => {
                if (btn) btn.classList.remove('animate-spin');
            });
        }
    }

    renderResults(data) {
        // Masquer loader et placeholder
        this.loadingTarget.classList.add('hidden');
        this.placeholderTarget.classList.add('hidden');
        this.resultsAreaTarget.classList.remove('hidden');

        // Mettre à jour les infos de la circo
        this.circoNameTarget.textContent = data.circonscription.nom;
        this.circoSiegesTarget.textContent = `${data.circonscription.sieges} Sièges aux législatives`;

        // Mise à jour du lien vers les projections
        if (this.hasProjectionsLinkTarget) {
            this.projectionsLinkTarget.href = `/projections?code=${data.circonscription.code}`;
            this.projectionsLinkTarget.classList.remove('invisible', 'pointer-events-none');
        }

        // Afficher les villes
        if (data.circonscription.villes && data.circonscription.villes.length > 0) {
            this.circoVillesTarget.textContent = "Communes : " + data.circonscription.villes.join(', ');
        } else {
            this.circoVillesTarget.textContent = '';
        }

        // Générer le HTML des barres de résultat
        const container = this.resultsAreaTarget.querySelector('#results-list');
        container.innerHTML = '';

        if (data.resultats.length === 0) {
            container.innerHTML = '<div class="text-center  text-slate-400 italic py-8">Aucun résultat remonté pour le moment.</div>';
            return;
        }

        // Calcul du total des voix pour les pourcentages
        const totalVoix = data.resultats.reduce((sum, r) => sum + r.voix, 0);

        data.resultats.forEach((result) => {
            const percent = totalVoix > 0 ? ((result.voix / totalVoix) * 100).toFixed(1) : 0;
            const color = result.parti.couleur || this.getRandomColor();

            const html = `
                <div class="mb-6 last:mb-0 animate-slide-in-right">
                    <div class="flex justify-between items-end mb-1">
                        <div>
                            <span class="font-black text-slate-800 text-lg">${result.parti.sigle}</span>
                            <span class="text-xs font-bold text-slate-400 uppercase hidden md:block">${result.parti.nom}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-black text-2xl text-slate-900">${percent}%</span>
                            <span class="text-xs font-bold text-slate-400 block">${result.voix.toLocaleString()} voix</span>
                        </div>
                    </div>
                    <div class="h-4 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-1000 ease-out"
                             style="width: 0%; background-color: ${color}"
                             data-width="${percent}%"></div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        });

        // Trigger animation (petit hack pour que la transition CSS se lance après l'insertion)
        requestAnimationFrame(() => {
            container.querySelectorAll('[data-width]').forEach(el => {
                el.style.width = el.dataset.width;
            });
        });
    }

    showLoading() {
        this.placeholderTarget.classList.add('hidden');
        this.resultsAreaTarget.classList.add('hidden');
        this.loadingTarget.classList.remove('hidden');
    }

    showPlaceholder() {
        this.loadingTarget.classList.add('hidden');
        this.resultsAreaTarget.classList.add('hidden');
        this.placeholderTarget.classList.remove('hidden');
        if (this.hasProjectionsLinkTarget) {
            this.projectionsLinkTarget.classList.add('invisible', 'pointer-events-none');
        }
    }
    getRandomColor() {
        const letters = '0123456789ABCDEF';
        let color = '#';
        for (let i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    }
}
