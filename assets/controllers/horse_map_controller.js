import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import { COLORS, createMap, paddockLayer } from '../leaflet_helpers.js';

/*
 * Carte de la fiche cheval : enclos, trajet du jour et position actuelle (F2, F4).
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
            const track = L.polyline(this.trackValue, {
                color: COLORS.forest,
                weight: 2,
                opacity: 0.6,
                lineJoin: 'round',
            }).addTo(this.map);
            bounds.extend(track.getBounds());
        }

        if (this.currentValue.length === 2) {
            const color = this.outsideValue ? COLORS.critical : COLORS.forest;
            L.circleMarker(this.currentValue, {
                radius: 9,
                color,
                weight: 4,
                fillColor: '#fff',
                fillOpacity: 1,
            })
                .bindTooltip(this.nameValue || 'Position actuelle', { direction: 'top', offset: [0, -8] })
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
        this.map?.remove();
    }
}
