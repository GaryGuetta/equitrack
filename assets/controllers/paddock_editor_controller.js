import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import { COLORS, createMap, paddockLayer } from '../leaflet_helpers.js';

/*
 * Tracé de l'enclos sur la carte : chaque clic ajoute un sommet (F4).
 * Le polygone est stocké en JSON dans un champ caché du formulaire.
 */
export default class extends Controller {
    static targets = ['map', 'input', 'count'];

    connect() {
        this.points = this.readPoints();
        this.map = createMap(this.mapTarget, { scrollWheelZoom: true });
        this.layer = L.layerGroup().addTo(this.map);
        this.map.on('click', (event) => this.add(event.latlng));

        if (this.points.length > 0) {
            this.map.fitBounds(L.latLngBounds(this.points), { padding: [32, 32], maxZoom: 18 });
        } else {
            this.map.setView([46.6, 2.4], 6);
            this.locate();
        }
        this.render();
    }

    disconnect() {
        this.map?.remove();
    }

    add(latlng) {
        this.points.push([Number(latlng.lat.toFixed(6)), Number(latlng.lng.toFixed(6))]);
        this.render();
    }

    undo() {
        this.points.pop();
        this.render();
    }

    clear() {
        this.points = [];
        this.render();
    }

    locate() {
        navigator.geolocation?.getCurrentPosition(
            (position) => this.map.setView([position.coords.latitude, position.coords.longitude], 17),
            () => {},
            { timeout: 5000 },
        );
    }

    render() {
        this.layer.clearLayers();
        if (this.points.length >= 3) {
            paddockLayer(this.points).addTo(this.layer);
        } else if (this.points.length === 2) {
            L.polyline(this.points, { color: COLORS.sage, weight: 2 }).addTo(this.layer);
        }
        this.points.forEach((point) => {
            L.circleMarker(point, { radius: 5, color: COLORS.forest, weight: 2, fillColor: '#fff', fillOpacity: 1 }).addTo(this.layer);
        });

        this.inputTarget.value = JSON.stringify(this.points);
        if (this.hasCountTarget) {
            const n = this.points.length;
            this.countTarget.textContent = n === 0
                ? 'Cliquez sur la carte pour placer les coins de l\'enclos.'
                : `${n} point${n > 1 ? 's' : ''}${n < 3 ? ' — il en faut au moins 3' : ''}`;
        }
    }

    readPoints() {
        try {
            const points = JSON.parse(this.inputTarget.value || '[]');
            return Array.isArray(points) ? points : [];
        } catch {
            return [];
        }
    }
}
