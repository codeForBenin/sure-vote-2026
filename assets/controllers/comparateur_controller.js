import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["hemicycle", "checkboxes", "limitWarning", "col1Header", "col2Header", "tableBody"]
    static values = {
        parlement: Array,
        data: Array
    }

    connect() {
        this.selectedParties = [];
        this.renderHemicycle();
    }

    renderHemicycle() {
        const targetTotalSeats = 109;

        // 1. Flatten seats into a single array of "dots"
        let dots = [];
        this.parlementValue.forEach(p => {
            for (let i = 0; i < p.seats; i++) {
                dots.push({ color: p.color, party: p.nom, sigle: p.sigle });
            }
        });

        while (dots.length < targetTotalSeats) {
            dots.push({
                color: '#e2e8f0',
                party: 'En attente de résultats',
                sigle: '-'
            });
        }

        // Safety Trim
        if (dots.length > targetTotalSeats) {
            dots = dots.slice(0, targetTotalSeats);
        }

        // 3. Setup SVG
        const width = 600;
        const height = 320;
        const centerX = width / 2;
        const centerY = height - 20;

        let svgContent = `<svg viewBox="0 0 ${width} ${height}" class="w-full h-full">`;

        // 4. Exact Layout for 109 Seats (8 Rows)
        // Inner to Outer.
        const rowCounts = [9, 11, 12, 13, 14, 15, 17, 18];
        // Sum = 109.

        const r_min = 120;
        const r_max = 290;
        const r_step = (r_max - r_min) / (rowCounts.length - 1);
        const dotRadius = 9;

        let seatsCoords = [];

        rowCounts.forEach((count, rowIndex) => {
            const r = r_min + (rowIndex * r_step);
            // Angle span: We use PI but with margins.
            // angles: PI - (step/2 + i*step). (Centers them in sectors)
            const angleStep = Math.PI / count;

            for (let i = 0; i < count; i++) {
                const angle = Math.PI - (angleStep / 2 + i * angleStep);

                seatsCoords.push({
                    x: centerX + r * Math.cos(angle),
                    y: centerY - r * Math.sin(angle),
                    angle: angle
                });
            }
        });

        // 5. Sort "seats" by angle (Left to Right) to fill sectors properly
        // This ensures that Party 1 fills its wedge, then Party 2, etc.
        seatsCoords.sort((a, b) => b.angle - a.angle);

        // 6. Map dots to seats 1:1
        for (let i = 0; i < targetTotalSeats; i++) {
            const seat = seatsCoords[i];
            const dot = dots[i];

            if (seat && dot) {
                svgContent += `<circle cx="${seat.x}" cy="${seat.y}" r="${dotRadius}" fill="${dot.color}" stroke="white" stroke-width="2">
                    <title>${dot.party} (${dot.sigle})</title>
                </circle>`;
            }
        }

        svgContent += '</svg>';
        this.hemicycleTarget.innerHTML = svgContent;
    }

    updateComparison(event) {
        const checkbox = event.target;
        const id = checkbox.value;
        const sigle = checkbox.dataset.sigle;

        if (checkbox.checked) {
            if (this.selectedParties.length >= 2) {
                // Prevent check
                checkbox.checked = false;
                this.limitWarningTarget.classList.remove('hidden');
                setTimeout(() => this.limitWarningTarget.classList.add('hidden'), 3000);
                return;
            }
            this.selectedParties.push({ id, sigle, color: checkbox.dataset.color });
        } else {
            this.selectedParties = this.selectedParties.filter(p => p.id !== id);
        }

        this.renderTable();
    }

    renderTable() {
        const tbody = this.tableBodyTarget;
        tbody.innerHTML = '';

        if (this.selectedParties.length === 0) {
            tbody.innerHTML = `
                <tr class="bg-white">
                    <td colspan="5" class="p-8 text-center text-slate-400">
                        <i class="fas fa-mouse-pointer mb-2 text-2xl"></i><br>
                        Sélectionnez des partis ci-dessus pour voir la comparaison.
                    </td>
                </tr>`;
            // Reset headers
            this.col1HeaderTarget.textContent = 'Parti 1';
            this.col2HeaderTarget.textContent = 'Parti 2';
            return;
        }

        // Update Headers
        const p1 = this.selectedParties[0];
        const p2 = this.selectedParties[1] || null;

        this.col1HeaderTarget.innerHTML = `<span style="color:${p1.color}">${p1.sigle}</span>`;
        if (p2) {
            this.col2HeaderTarget.innerHTML = `<span style="color:${p2.color}">${p2.sigle}</span>`;
        } else {
            this.col2HeaderTarget.textContent = '---';
        }

        // Render Data
        this.dataValue.forEach(row => {
            const v1 = row.votes[p1.id] || 0;
            const v2 = p2 ? (row.votes[p2.id] || 0) : 0;

            let gap = 0;
            let winner = '-';
            let winnerColor = '';

            if (p2) {
                gap = Math.abs(v1 - v2);
                if (v1 > v2) {
                    winner = p1.sigle;
                    winnerColor = p1.color;
                } else if (v2 > v1) {
                    winner = p2.sigle;
                    winnerColor = p2.color;
                } else {
                    if (v1 > 0) winner = 'Égalité';
                }
            } else {
                // Single mode
                winner = '-';
            }

            const tr = document.createElement('tr');
            tr.className = 'bg-white hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0';

            tr.innerHTML = `
                <td class="p-4 font-bold text-slate-700 sticky left-0 z-10 bg-white border-r border-slate-200 shadow-sm">${row.circo_nom}</td>
                <td class="p-4 text-right font-mono font-bold text-slate-600">
                    <span class="${v1 > v2 && p2 ? 'text-benin-green' : ''}">${this.formatNumber(v1)}</span>
                </td>
                <td class="p-4 text-right font-mono font-bold text-slate-600 border-l border-slate-100">
                    ${p2 ? `<span class="${v2 > v1 ? 'text-benin-green' : ''}">${this.formatNumber(v2)}</span>` : '<span class="text-slate-300">-</span>'}
                </td>
                <td class="p-4 text-center font-mono text-sm text-slate-500 border-l border-slate-100">
                    ${p2 ? this.formatNumber(gap) : '-'}
                </td>
                <td class="p-4 text-center font-bold text-sm border-l border-slate-100">
                    <span style="color: ${winnerColor}">${winner}</span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    formatNumber(num) {
        return new Intl.NumberFormat('fr-FR').format(num);
    }
}
