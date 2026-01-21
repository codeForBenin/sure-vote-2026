import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

/*
 * This is the Stimulus controller for the stats chart.
 * Usage:
 * <canvas data-controller="stats-chart"
 *         data-stats-chart-labels-value='["8H", "10H"]'
 *         data-stats-chart-data-value='[10, 25]'>
 * </canvas>
 */
export default class extends Controller {
    static values = {
        labels: Array,
        data: Array
    }

    connect() {
        this.renderChart();
    }

    renderChart() {
        const ctx = this.element.getContext('2d');

        // Gradient configuration (adapted from original script)
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(0, 135, 81, 0.2)'); // Benin Green
        gradient.addColorStop(1, 'rgba(0, 135, 81, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    label: 'Taux de Participation par Heure (%)',
                    data: this.dataValue,
                    borderColor: '#008751', // Benin Green
                    backgroundColor: gradient,
                    borderWidth: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#008751',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        titleFont: { family: 'Inter', size: 14 },
                        bodyFont: { family: 'Inter', size: 14, weight: 'bold' },
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return context.parsed.y + '% de participation à cette heure';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: '#f1f5f9',
                            borderDash: [5, 5]
                        },
                        ticks: {
                            font: { family: 'Inter', weight: '500' },
                            color: '#64748b',
                            callback: function (value) { return value + '%' }
                        },
                        border: { display: true }
                    },
                    x: {
                        grid: { display: true },
                        ticks: {
                            font: { family: 'Inter', weight: 'bold' },
                            color: '#64748b'
                        },
                        border: { display: true }
                    }
                },
                animation: {
                    y: {
                        duration: 2000,
                        easing: 'easeOutQuart'
                    }
                }
            }
        });
    }
}
