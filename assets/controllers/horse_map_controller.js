import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import { COLORS, createMap, paddockLayer } from '../leaflet_helpers.js';
import { reduceMotion } from '../motion.js';

/*
 * Carte de la fiche cheval : enclos, trajet du jour (qui se dessine) et position actuelle (F2, F4).
 */
export default class extends Controller {
    static values = {
        track: Array,
        current: Array,
        paddock: Array,
        outside: Boolean,
        name: String,
    };

    connect() {
        this.map = createMap(this.element);
        const bounds = L.latLngBounds([]);

        if (this.paddockValue.length >= 3) {
            const zone = paddockLayer(this.paddockValue).addTo(this.map);
            bounds.extend(zone.getBounds());
        }

        if (this.trackValue.length > 1) {
            bounds.extend(L.latLngBounds(this.trackValue));
            this.drawTrack();
        }

        if (this.currentValue.length === 2) {
            const icon = L.divIcon({
                className: `pulse-marker${this.outsideValue ? ' is-out' : ''}`,
                html: '<span></span>',
                iconSize: [20, 20],
            });
            L.marker(this.currentValue, { icon, keyboard: false })
                .bindTooltip(this.nameValue || 'Position actuelle', { direction: 'top', offset: [0, -12] })
                .addTo(this.map);
            bounds.extend(this.currentValue);
        }

        if (bounds.isValid()) {
            this.map.fitBounds(bounds, { padding: [24, 24], maxZoom: 18 });
        } else {
            this.map.setView([46.6, 2.4], 5);
        }
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        this.map?.remove();
    }

    /** Le trajet se trace point par point en 2,5 s, comme une rediffusion de la journée. */
    drawTrack() {
        const line = L.polyline([], {
            color: COLORS.forest,
            weight: 2,
            opacity: 0.6,
            lineJoin: 'round',
        }).addTo(this.map);

        if (reduceMotion()) {
            line.setLatLngs(this.trackValue);
            return;
        }

        const points = this.trackValue;
        const duration = 2500;
        let start = null;
        const step = (time) => {
            start ??= time;
            const progress = Math.min(1, (time - start) / duration);
            const eased = 1 - (1 - progress) ** 3;
            line.setLatLngs(points.slice(0, Math.max(2, Math.round(eased * points.length))));
            if (progress < 1) {
                this.frame = requestAnimationFrame(step);
            }
        };
        this.frame = requestAnimationFrame(step);
    }
}
