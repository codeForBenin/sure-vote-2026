import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'contentArea', 'placeholder', 'loading', 'circoName', 'circoSieges', 'tableBody', 'totalSieges', 'quotientValue', 'statsBlancsNuls', 'statsBlancsNulsValue', 'partiSelect', 'eligibilityResult'];

    async connect() {
        try {
            // Charger les circonscriptions
            const response = await fetch('/api/public/circonscriptions');
            const data = await response.json();

            // Récupérer le paramètre 'code' de l'URL
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedCode = urlParams.get('code');

            this.populateSelect(data, preselectedCode);

            // Charger les partis pour la section éligibilité (si présente)
            this.loadPartis();

        } catch (error) {
            console.error('Erreur chargement:', error);
        }
    }

    async loadPartis() {
        if (!this.hasPartiSelectTarget) return;

        try {
            const response = await fetch('/api/public/partis');
            const partis = await response.json();

            const select = this.partiSelectTarget;
            select.innerHTML = '<option value="">Choisir un parti...</option>';
            partis.forEach(p => {
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = `${p.sigle} - ${p.nom}`;
                select.appendChild(option);
            });
            select.disabled = false;
        } catch (e) {
            console.error("Erreur chargement partis", e);
        }
    }

    async onPartiChange(event) {
        const partiId = event.target.value;
        if (!partiId) {
            this.eligibilityResultTarget.classList.add('hidden');
            return;
        }

        const container = this.eligibilityResultTarget;
        container.classList.remove('hidden');
        container.innerHTML = `<div class="p-8 text-center text-slate-500"><i class="fas fa-circle-notch fa-spin mr-2"></i>Analyse en cours...</div>`;

        try {
            const response = await fetch(`/api/public/parti/${partiId}/performance`);
            const data = await response.json();
            this.renderEligibilityReport(data, container);
        } catch (e) {
            container.innerHTML = `<div class="p-8 text-center text-red-500">Erreur lors de l'analyse.</div>`;
        }
    }

    renderEligibilityReport(data, container) {
        console.log(data);
        const global = data.global;
        const details = data.details;
        const parti = data.parti;
        const isEligible = global.is_eligible_everywhere; // condition stricte

        let html = `
            <div class="p-8 bg-slate-50 border-b border-slate-100">
                <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
                <div class="flex items-center gap-2">
                <img src="${parti.logo}" alt="Logo ${parti.nom}" class="w-12 h-12 rounded-full shadow-sm">
                
                <div class="text-slate-500 font-medium flex flex-col">
                <h3 class="text-2xl font-black text-slate-900">${parti.sigle}</h3>
               <span>${parti.nom}</span>
                </div>
                </div>
                <span class="w-12 h-12 hidden sm:block rounded-full shadow-sm" style="background-color: ${parti.couleur}"></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <div class="text-xs font-bold text-slate-400 uppercase mb-1">Score National</div>
                        <div class="flex items-baseline gap-2">
                             <div class="text-3xl font-black text-slate-900">${global.pourcentage_national}%</div>
                             <div class="text-xs font-black text-benin-green bg-green-50 px-2 py-1 rounded-md border border-green-100"> Rang #${global.rank} </div>
                        </div>
                        <div class="text-sm font-medium text-slate-500">${global.votes.toLocaleString()} voix</div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                        <div class="text-xs font-bold text-slate-400 uppercase mb-1">Circos Validées (>20%)</div>
                        <div class="text-3xl font-black ${global.circos_validees === global.total_circos ? 'text-green-600' : 'text-amber-600'}">
                            ${global.circos_validees} <span class="text-lg text-slate-400 font-medium">/ ${global.total_circos}</span>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col justify-center">
                         <div class="flex justify-between items-center mb-2">
                             <span class="text-[10px] font-bold text-slate-400 uppercase">Sièges (Brut)</span>
                             <span class="text-lg font-black text-slate-700">${global.total_sieges_theoretical}</span>
                         </div>
                         <div class="flex justify-between items-center mb-3 pb-3 border-b border-slate-100">
                             <span class="text-[10px] font-bold text-slate-400 uppercase">Seuil 20%</span>
                             <span class="text-[10px] font-bold ${isEligible ? 'text-green-600' : 'text-red-500 uppercase'}">
                                ${isEligible ? 'Validé' : 'Non Atteint'}
                             </span>
                         </div>
                        <div class="text-center">
                            ${isEligible
                ? `<div class="inline-flex items-center gap-2 px-3 py-2 bg-green-100 text-green-700 rounded-full font-black text-xs uppercase tracking-wide"><i class="fas fa-check-circle"></i> ${global.total_sieges_theoretical} Sièges</div>`
                : `<div class="inline-flex items-center gap-2 px-3 py-2 bg-red-100 text-red-700 rounded-full font-black text-xs uppercase tracking-wide"><i class="fas fa-times-circle"></i> Non Éligible</div>
                   <p class="text-[10px] text-red-500 mt-2 font-medium leading-tight">À défaut d'un accord de coalition avec un autre parti, ce parti perd tous les sièges bruts selon le critère seuil.</p>`
            }
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-100 text-xs uppercase text-slate-500 font-bold tracking-wider">
                        <tr>
                            <th class="px-6 py-4 border-b border-slate-200 sticky left-0 bg-white border-r border-slate-100">Circonscription</th>
                            <th class="px-6 py-4 border-b border-slate-200 text-right">Voix Parti</th>
                            <th class="px-6 py-4 border-b border-slate-200 text-right">Total Valides</th>
                            <th class="px-6 py-4 border-b border-slate-200 text-center">Pourcentage</th>
                            <th class="px-6 py-4 border-b border-slate-200 text-center">Seuil 20%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
        `;

        details.forEach(d => {
            html += `
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-bold text-slate-700 sticky left-0 bg-white border-r border-slate-100">${d.circonscription}</td>
                    <td class="px-6 py-4 text-right font-medium text-slate-600">${d.votes_parti.toLocaleString('fr-FR')}</td>
                    <td class="px-6 py-4 text-right text-sm text-slate-400">${d.votes_valides_total.toLocaleString('fr-FR')}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="font-bold ${d.pourcentage >= 20 ? 'text-green-600' : 'text-red-500'}">${d.pourcentage}%</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        ${d.seuil_atteint
                    ? `<i class="fas fa-check text-green-500"></i>`
                    : `<i class="fas fa-times text-slate-300"></i>`
                }
                    </td>
                </tr>
            `;
        });

        html += `   </tbody>
                </table>
            </div>
            <div class="p-6 bg-slate-50 text-xs text-slate-400 italic text-center">
                Conformément au code électoral, un parti doit atteindre un seuil représentatif (simulé ici à 20% par circonscription) pour être éligible à la répartition des sièges.
            </div>
        `;

        container.innerHTML = html;
    }

    populateSelect(circonscriptions, preselectedCode = null) {
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

        // Auto-sélection si code présent
        if (preselectedCode) {
            select.value = preselectedCode;
            if (select.value === preselectedCode) { // Vérifier que le code existe bien dans les options
                this.onCircoChange(preselectedCode);
            }
        }
    }

    async onCircoChange(event) {
        const code = event.target ? event.target.value : event;
        if (!code) {
            this.showPlaceholder();
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(`/api/public/resultats/${code}`);
            if (!response.ok) throw new Error('Erreur réseau');
            const data = await response.json();
            this.renderProjections(data);
        } catch (error) {
            console.error('Erreur:', error);
            this.contentAreaTarget.innerHTML = `<div class="text-red-500 p-4">Erreur de chargement.</div>`;
        }
    }

    renderProjections(data) {
        this.loadingTarget.classList.add('hidden');
        this.placeholderTarget.classList.add('hidden');
        this.contentAreaTarget.classList.remove('hidden');

        this.circoNameTarget.textContent = data.circonscription.nom;
        this.circoSiegesTarget.textContent = `${data.circonscription.sieges} Sièges au total`;

        // Affichage du quotient électoral si disponible
        if (this.hasQuotientValueTarget) {
            const quotient = data.circonscription.quotient_electoral;
            if (quotient > 0) {
                this.quotientValueTarget.textContent = parseInt(quotient).toLocaleString();
                this.quotientValueTarget.parentElement.classList.remove('hidden');
            } else {
                this.quotientValueTarget.parentElement.classList.add('hidden');
            }
        }

        // Affichage des Blancs et Nuls
        if (this.hasStatsBlancsNulsTarget && data.statistiques) {
            const bnv = data.statistiques.blancs_nuls;
            this.statsBlancsNulsValueTarget.textContent = bnv.toLocaleString();
            this.statsBlancsNulsTarget.classList.remove('hidden');
        } else if (this.hasStatsBlancsNulsTarget) {
            this.statsBlancsNulsTarget.classList.add('hidden');
        }

        const tbody = this.tableBodyTarget;
        tbody.innerHTML = '';

        let grandTotalSieges = 0;

        // Trier par total de sièges décroissant, puis par voix
        const sortedResults = data.resultats.sort((a, b) => {
            if (b.sieges.total !== a.sieges.total) {
                return b.sieges.total - a.sieges.total;
            }
            return b.voix - a.voix;
        });

        sortedResults.forEach(r => {
            grandTotalSieges += r.sieges.total;

            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0";
            tr.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap sticky left-0 bg-white border-r border-slate-100">
                    <div class="flex items-center ">
                        <span class="w-3 h-3 rounded-full mr-3" style="background-color: ${r.parti.couleur || '#ccc'}"></span>
                        <div>
                            <div class="text-sm font-bold text-slate-900">${r.parti.sigle}</div>
                            <div class="text-xs text-slate-500 hidden md:block">${r.parti.nom}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-600 font-medium">
                    ${r.voix.toLocaleString()}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    ${r.sieges.femme > 0
                    ? `<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-pink-100 text-pink-600 font-bold border-2 border-pink-200 shadow-sm" title="Siège réservé femme">1</span>`
                    : `<span class="text-slate-300">-</span>`}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-slate-700">
                    ${r.sieges.ordinaire > 0 ? r.sieges.ordinaire : '<span class="text-slate-300">-</span>'}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-lg ${r.sieges.total > 0 ? 'bg-benin-green text-white shadow-lg shadow-benin-green/30' : 'bg-slate-100 text-slate-400'} font-black text-lg">
                        ${r.sieges.total}
                    </span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    showLoading() {
        this.placeholderTarget.classList.add('hidden');
        this.contentAreaTarget.classList.add('hidden');
        this.loadingTarget.classList.remove('hidden');
    }

    showPlaceholder() {
        this.loadingTarget.classList.add('hidden');
        this.contentAreaTarget.classList.add('hidden');
        this.placeholderTarget.classList.remove('hidden');
    }
}
