/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : map.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Tiny Leaflet wrapper used by the <x-map> Blade component.
 * Looks for `[data-map]` elements carrying JSON config and renders a
 * map with optional markers and an OSRM-derived GeoJSON LineString.
 *
 * Config shape (data-config attr, JSON-encoded):
 *   {
 *     "tiles": { "url": "…", "attribution": "…", "maxZoom": 19 },
 *     "center": [lat, lng] | null,
 *     "zoom": 13,
 *     "markers": [{ "lat": …, "lng": …, "label": "…" }, …],
 *     "route":   GeoJSON LineString | null
 *   }
 */
import "leaflet/dist/leaflet.css";
import "leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.css";
import L from "leaflet";
import "leaflet-defaulticon-compatibility";

const ROOT_SELECTOR = "[data-map]";

function readConfig(el) {
    const raw = el.getAttribute("data-config");
    if (!raw) {
        return {};
    }
    try {
        return JSON.parse(raw);
    } catch (e) {
        console.warn("workDiary map: invalid data-config JSON", e);
        return {};
    }
}

export function initMap(el, overrides = {}) {
    if (!el || el.__wdMap) {
        return el && el.__wdMap;
    }

    const cfg = { ...readConfig(el), ...overrides };
    const tiles = cfg.tiles || {};
    const center = Array.isArray(cfg.center) ? cfg.center : [51.1657, 10.4515]; // DE Mitte
    const zoom = Number.isFinite(cfg.zoom) ? cfg.zoom : 6;

    const map = L.map(el, { scrollWheelZoom: false }).setView(center, zoom);

    L.tileLayer(tiles.url || "https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution:
            tiles.attribution ||
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: tiles.maxZoom || 19,
    }).addTo(map);

    const layers = { markers: [], route: null };

    if (Array.isArray(cfg.markers)) {
        cfg.markers.forEach((m) => {
            if (typeof m.lat === "number" && typeof m.lng === "number") {
                const marker = L.marker([m.lat, m.lng]).addTo(map);
                if (m.label) {
                    marker.bindTooltip(String(m.label));
                }
                layers.markers.push(marker);
            }
        });
    }

    if (cfg.route && typeof cfg.route === "object") {
        layers.route = L.geoJSON(cfg.route, {
            style: { color: "#2563eb", weight: 5, opacity: 0.85 },
        }).addTo(map);
    }

    fitToContent(map, layers);

    el.__wdMap = { map, layers };
    return el.__wdMap;
}

export function addMarker(handle, lat, lng, label) {
    if (!handle || !handle.map) return null;
    const marker = L.marker([lat, lng]).addTo(handle.map);
    if (label) marker.bindTooltip(String(label));
    handle.layers.markers.push(marker);
    fitToContent(handle.map, handle.layers);
    return marker;
}

export function drawRoute(handle, geometry) {
    if (!handle || !handle.map) return null;
    if (handle.layers.route) {
        handle.map.removeLayer(handle.layers.route);
        handle.layers.route = null;
    }
    if (!geometry) return null;
    handle.layers.route = L.geoJSON(geometry, {
        style: { color: "#2563eb", weight: 5, opacity: 0.85 },
    }).addTo(handle.map);
    fitToContent(handle.map, handle.layers);
    return handle.layers.route;
}

function fitToContent(map, layers) {
    const bounds = L.latLngBounds([]);
    layers.markers.forEach((m) => bounds.extend(m.getLatLng()));
    if (layers.route) {
        bounds.extend(layers.route.getBounds());
    }
    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [24, 24], maxZoom: 16 });
    }
}

function autoInit() {
    document.querySelectorAll(ROOT_SELECTOR).forEach((el) => initMap(el));
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", autoInit);
} else {
    autoInit();
}

window.workDiaryMap = { initMap, addMarker, drawRoute };
