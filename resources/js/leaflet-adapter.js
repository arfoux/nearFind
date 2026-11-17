import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export { L };

export function createLeafletMap(container, { center, zoom, minZoom, maxZoom }) {
    return L.map(container, {
        center,
        zoom,
        minZoom,
        maxZoom,
        attributionControl: false,
        zoomControl: false,
        zoomSnap: 0.25,
        worldCopyJump: false,
        keyboard: true,
        scrollWheelZoom: true,
        doubleClickZoom: true,
        boxZoom: true,
    });
}

export function createTileLayer(url) {
    return L.tileLayer(url, {
        maxZoom: 19,
        maxNativeZoom: 19,
        attribution: '© OpenStreetMap contributors',
    });
}

export function setViewWithPadding(map, latlng, zoom, padding = {}) {
    const targetZoom = Number.isFinite(zoom) ? zoom : map.getZoom();
    const { left = 0, right = 0, top = 0, bottom = 0 } = padding;

    map.setView(latlng, targetZoom, { animate: false });

    const dx = (Number(left) - Number(right)) / 2;
    const dy = (Number(top) - Number(bottom)) / 2;

    if (dx || dy) {
        map.panBy([dx, dy], { animate: false });
    }
}

export function fitBoundsWithPadding(map, bounds, padding = {}, maxZoom, duration = 0) {
    const { left = 0, right = 0, top = 0, bottom = 0 } = padding;

    map.fitBounds(bounds, {
        paddingTopLeft: [Number(left), Number(top)],
        paddingBottomRight: [Number(right), Number(bottom)],
        ...(Number.isFinite(maxZoom) ? { maxZoom } : {}),
        animate: duration > 0,
        duration: duration > 0 ? duration : 0,
    });
}

export function panTo(map, latlng, duration = 0) {
    map.panTo(latlng, { animate: duration > 0, duration: duration > 0 ? duration : 0 });
}

export function zoomTo(map, zoom, duration = 0) {
    map.setZoom(zoom, { animate: duration > 0, duration: duration > 0 ? duration : 0 });
}

export function resizeMap(map) {
    map.invalidateSize({ animate: false, pan: false });
}
