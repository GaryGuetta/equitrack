import { Controller } from '@hotwired/stimulus';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
Chart.defaults.font.family = "'Outfit', system-ui, sans-serif";
Chart.defaults.color = '#5E6B64';

const DATASETS = {
    climate: [
        { key: 'temperature', label: 'Sous la couverture (°C)', color: '#1E3A2F', axis: 'y' },
        { key: 'outside', label: 'Extérieur (°C)', color: '#8DB89F', axis: 'y', dash: [5, 4] },
        { key: 'humidity', label: 'Humidité (%)', color: '#31617F', axis: 'y1' },
    ],
    activity: [
        { key: 'activity', label: 'Activité (0–100)', color: '#1E3A2F', axis: 'y', fill: true },
    ],
};

/*
 * Courbes de la fiche cheval : climat sous la couverture et activité (F6).
 */
export default class extends Controller {
    static values = { series: Object, kind: String, multiDay: Boolean };

    connect() {
        const formatter = new Intl.DateTimeFormat('fr-FR', this.multiDayValue
            ? { weekday: 'short', day: 'numeric', hour: '2-digit' }
            : { hour: '2-digit', minute: '2-digit' });
        const definitions = DATASETS[this.kindValue];

        this.chart = new Chart(this.element, {
            type: 'line',
            data: {
                labels: this.seriesValue.labels.map((label) => formatter.format(new Date(label))),
                datasets: definitions.map((d) => ({
                    label: d.label,
                    data: this.seriesValue[d.key],
                    yAxisID: d.axis,
                    borderColor: d.color,
                    backgroundColor: d.fill ? 'rgba(141, 184, 159, 0.25)' : d.color,
                    borderDash: d.dash ?? [],
                    fill: Boolean(d.fill),
                    borderWidth: 2,
                    pointRadius: 0,
                    tension: 0.3,
                    spanGaps: true,
                })),
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 2, usePointStyle: false } },
                    tooltip: { backgroundColor: '#1E3A2F', padding: 10, cornerRadius: 8 },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: { grid: { color: '#EFEAE0' }, ...(this.kindValue === 'activity' ? { min: 0, max: 100 } : {}) },
                    ...(definitions.some((d) => d.axis === 'y1')
                        ? { y1: { position: 'right', min: 0, max: 100, grid: { display: false } } }
                        : {}),
                },
            },
        });
    }

    disconnect() {
        this.chart?.destroy();
    }
}
