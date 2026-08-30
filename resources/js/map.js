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
const DEFAULT_ROUTE_COLOR = "#2563eb";

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

function normalizeCenter(center) {
    if (Array.isArray(center)) {
        return center;
    }
    if (
        center &&
        typeof center === "object" &&
        "lat" in center &&
        "lng" in center
    ) {
        return [Number(center.lat), Number(center.lng)];
    }
    return null;
}

function makeMarker(m) {
    const latlng = [m.lat, m.lng];
    let marker;
    if (typeof m.color === "string" && m.color !== "") {
        marker = L.circleMarker(latlng, {
            radius: 7,
            color: m.color,
            weight: 2,
            fillColor: m.color,
            fillOpacity: 0.85,
        });
    } else {
        marker = L.marker(latlng);
    }
    if (m.popup) {
        marker.bindPopup(String(m.popup));
    }
    if (m.label) {
        marker.bindTooltip(textNode(m.label));
    }
    return marker;
}

/**
 * Beschriftung als Textknoten statt als String.
 *
 * Leaflet rendert String-Inhalte in `DivOverlay._updateContent()` über
 * `node.innerHTML` — ungefiltert. Die Beschriftungen kommen aus Kunden-,
 * Standort-, Auftrags- und Tournamen, also aus Feldern, die jedes Mitglied
 * mit Schreibrecht setzt (Sicherheitsscan 2026-08-23, S-18). Ein
 * HTMLElement nimmt Leaflet unverändert entgegen; `textContent` kann per
 * Definition kein Markup erzeugen. Die Popups waren serverseitig escaped,
 * die Tooltips nicht.
 */
function textNode(value) {
    const span = document.createElement("span");
    span.textContent = String(value);

    return span;
}

export function initMap(el, overrides = {}) {
    if (!el || el.__wdMap) {
        return el && el.__wdMap;
    }

    const cfg = { ...readConfig(el), ...overrides };
    const tiles = cfg.tiles || {};
    const center = normalizeCenter(cfg.center) || [51.1657, 10.4515]; // DE Mitte
    const zoom = Number.isFinite(cfg.zoom) ? cfg.zoom : 6;

    const map = L.map(el, { scrollWheelZoom: false }).setView(center, zoom);

    L.tileLayer(tiles.url || "https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution:
            tiles.attribution ||
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: tiles.maxZoom || 19,
    }).addTo(map);

    // Overlay groups keyed by layer name; items without a layer go to `_default`.
    const groups = {};
    const layerDefs = Array.isArray(cfg.layers) ? cfg.layers : [];
    layerDefs.forEach((def) => {
        if (def && def.key) {
            groups[def.key] = L.layerGroup().addTo(map);
        }
    });
    const ensureGroup = (key) => {
        const k = key || "_default";
        if (!groups[k]) {
            groups[k] = L.layerGroup().addTo(map);
        }
        return groups[k];
    };

    const layers = { markers: [], route: null, routes: [], groups };

    if (Array.isArray(cfg.markers)) {
        cfg.markers.forEach((m) => {
            if (typeof m.lat === "number" && typeof m.lng === "number") {
                const marker = makeMarker(m);
                marker.addTo(ensureGroup(m.layer));
                layers.markers.push(marker);
            }
        });
    }

    // New multi-route support.
    if (Array.isArray(cfg.routes)) {
        cfg.routes.forEach((r) => {
            if (!r || typeof r.geometry !== "object") {
                return;
            }
            const line = L.geoJSON(r.geometry, {
                style: {
                    color: r.color || DEFAULT_ROUTE_COLOR,
                    weight: 5,
                    opacity: 0.8,
                },
            });
            if (r.label) {
                line.bindTooltip(textNode(r.label), { sticky: true });
            }
            line.addTo(ensureGroup(r.layer || "tours"));
            layers.routes.push(line);
        });
    }

    // Legacy single route.
    if (cfg.route && typeof cfg.route === "object") {
        layers.route = L.geoJSON(cfg.route, {
            style: { color: DEFAULT_ROUTE_COLOR, weight: 5, opacity: 0.85 },
        }).addTo(map);
    }

    // Layer toggle control + legend when overlay definitions are provided.
    if (layerDefs.length > 0) {
        const overlays = {};
        layerDefs.forEach((def) => {
            if (!def || !def.key || !groups[def.key]) {
                return;
            }
            const swatch = def.color
                ? `<span style="display:inline-block;width:10px;height:10px;border-radius:9999px;background:${def.color};margin-right:4px;"></span>`
                : "";
            overlays[`${swatch}${def.label || def.key}`] = groups[def.key];
        });
        L.control
            .layers(null, overlays, { collapsed: false, position: "topright" })
            .addTo(map);
    }

    fitToContent(map, layers);

    // Leaflet measures the container on init. When the map lives inside a
    // Tailwind/Alpine layout (x-cloak, tabs, late flex layout) the element can
    // still have a height of 0 at that point, which renders a fully grey box.
    // Re-measuring after layout and on every resize keeps the tiles painted.
    const refresh = () => map.invalidateSize(false);
    requestAnimationFrame(refresh);
    setTimeout(refresh, 200);
    if (typeof ResizeObserver !== "undefined") {
        const ro = new ResizeObserver(() => refresh());
        ro.observe(el);
        layers._resizeObserver = ro;
    }
    window.addEventListener("resize", refresh);

    el.__wdMap = { map, layers };
    return el.__wdMap;
}

export function addMarker(handle, lat, lng, label) {
    if (!handle || !handle.map) return null;
    const marker = L.marker([lat, lng]).addTo(handle.map);
    if (label) marker.bindTooltip(textNode(label));
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
        style: { color: DEFAULT_ROUTE_COLOR, weight: 5, opacity: 0.85 },
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
    (layers.routes || []).forEach((r) => {
        try {
            bounds.extend(r.getBounds());
        } catch (e) {
            /* empty geometry */
        }
    });
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
