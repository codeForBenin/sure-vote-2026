import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["circoSelect", "seatsInput", "partyInput", "resultTable", "quotientDisplay", "resultSection", "placeholder"];

    connect() {
        console.log("Simulation controller connected");
    }

    updateCirco() {
        const select = this.circoSelectTarget;
        const option = select.options[select.selectedIndex];

        // Reset if placeholder selected
        if (!option.value) {
            this.seatsInputTarget.value = '';
            return;
        }

        const sieges = option.dataset.sieges;
        if (sieges) {
            this.seatsInputTarget.value = sieges;
            this.calculate();
        }
    }

    async calculate() {
        const sieges = parseInt(this.seatsInputTarget.value);
        if (!sieges || sieges <= 0) return;

        const votes = {};
        let hasVotes = false;

        this.partyInputTargets.forEach(input => {
            const partiId = input.dataset.partiId;
            const val = parseInt(input.value) || 0;
            if (val > 0) {
                votes[partiId] = val;
                hasVotes = true;
            }
        });

        if (!hasVotes) {
            // Optional: clear results or show empty state
            this.resultSectionTarget.classList.add('hidden');
            if (this.hasPlaceholderTarget) this.placeholderTarget.classList.remove('hidden');
            return;
        }

        try {
            const response = await fetch('/api/public/simulate-seats', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ sieges, votes })
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();
            this.renderResults(data);

        } catch (error) {
            console.error('Error:', error);
        }
    }

    renderResults(data) {
        this.resultSectionTarget.classList.remove('hidden');
        if (this.hasPlaceholderTarget) this.placeholderTarget.classList.add('hidden');

        if (this.hasQuotientDisplayTarget) {
            this.quotientDisplayTarget.textContent = new Intl.NumberFormat().format(data.quotient_electoral);
        }

        const tbody = this.resultTableTarget.querySelector('tbody');
        tbody.innerHTML = '';

        data.repartition.forEach(row => {
            // Only show parties with votes or seats? Or all? 
            // The API returns everything that was passed in input (implied) or just the repartition.
            // The service returns repartition for input parties.

            if (row.voix === 0 && row.total_sieges === 0) return;

            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0";
            tr.innerHTML = `
                <td class="flex items-center gap-3 px-6 py-4">
                    <div class="w-10 h-10 rounded-full flex-shrink-0 bg-white border border-slate-200 flex items-center justify-center overflow-hidden p-1 shadow-sm">
                        ${row.parti.logo ? `<img src="${row.parti.logo}" class="w-full h-full object-contain" alt="${row.parti.sigle}">` : `<span class="text-xs font-bold text-slate-400">${row.parti.sigle.substring(0, 2)}</span>`}
                    </div>
                    <div>
                        <div class="font-bold text-slate-900">${row.parti.sigle}</div>
                        <div class="text-xs text-slate-500 hidden sm:block">${row.parti.nom}</div>
                    </div>
                </td>
                <td class="px-6 py-4 text-center font-mono font-medium text-slate-600">
                    ${new Intl.NumberFormat().format(row.voix)}
                </td>
                <td class="px-6 py-4 text-center font-mono font-medium text-slate-600">
                    ${row.pourcentage}%
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full ${row.total_sieges > 0 ? 'bg-benin-green text-white shadow-md shadow-benin-green/20' : 'bg-slate-100 text-slate-400'} font-bold transition-all duration-300">
                        ${row.total_sieges}
                    </span>
                    ${row.sieges_femme > 0 ? `<div class="text-[10px] text-benin-green font-bold mt-1">+1 Femme</div>` : ''}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }
}
