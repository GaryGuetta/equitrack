import L from 'leaflet';

export const COLORS = {
    forest: '#1E3A2F',
    sage: '#8DB89F',
    critical: '#A63D2B',
};

/** Fond de carte OpenStreetMap ; vide le conteneur si Turbo l'a restauré depuis son cache. */
export function createMap(element, options = {}) {
    element.innerHTML = '';
    const map = L.map(element, { scrollWheelZoom: false, ...options });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; contributeurs <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    return map;
}

export function paddockLayer(points) {
    return L.polygon(points, {
        color: COLORS.sage,
        weight: 2,
        fillColor: COLORS.sage,
        fillOpacity: 0.25,
    });
}
