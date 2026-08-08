// @ts-check
/*
 * Created on   : Fri Aug 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : charts.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Progressive Enhancement der serverseitig gerenderten SVG-Diagramme:
 * Am Bildschirm wird jedes `figure.wd-chart` mit einem `[data-wd-chart]`-
 * Spezifikationsblock durch ein interaktives Chart.js-Canvas ersetzt
 * (Tooltips, umschaltbare Legende, weiche Verläufe, Zoom/Pan bei langen
 * Zeitreihen). Schlägt irgendetwas fehl, bleibt das SVG als Fallback sichtbar —
 * die Basis funktioniert immer, auch ohne JS. PDFs nutzen ausschließlich die
 * SVG-Variante (dompdf kennt kein JavaScript) und bleiben unberührt.
 *
 * Chart.js wird erst geladen, wenn wirklich ein Diagramm auf der Seite ist
 * (dynamischer Import → eigener Vite-Chunk).
 */

/** @typedef {'bar'|'line'} ChartKind */
/** @typedef {'primary'|'second'|'compare'|'ideal'|'band'} DatasetRole */
/**
 * @typedef {Object} ChartDataset
 * @property {string} label
 * @property {Array<number|null>} data
 * @property {ChartKind} kind
 * @property {DatasetRole} role
 * @property {boolean} [hatch]
 * @property {boolean} [dashed]
 */
/**
 * @typedef {Object} ChartSpec
 * @property {ChartKind} type
 * @property {boolean} [stacked]
 * @property {string} title
 * @property {string} [unit]
 * @property {string} [xLabel]
 * @property {string} [yLabel]
 * @property {number|null} [median]
 * @property {string[]} labels
 * @property {Array<string|null>} [urls]
 * @property {ChartDataset[]} datasets
 */

/** @type {Promise<any>|null} */
let chartLibPromise = null;

/** Lädt Chart.js + Zoom-Plugin einmalig und registriert die Bausteine. */
function loadChartLib() {
    if (chartLibPromise === null) {
        chartLibPromise = Promise.all([import("chart.js"), import("chartjs-plugin-zoom")]).then(
            ([core, zoom]) => {
                core.Chart.register(...core.registerables, zoom.default);
                return core.Chart;
            },
        );
    }
    return chartLibPromise;
}

/**
 * Liest einen CSS-Custom-Property-Wert vom Wurzelelement (DaisyUI-Theme-Token).
 * @param {string} name
 * @param {string} fallback
 * @returns {string}
 */
function cssVar(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return value !== "" ? value : fallback;
}

/** Aktuelle Theme-Farben aus den DaisyUI-Tokens (reagiert auf Light/Dark). */
function readTheme() {
    return {
        primary: cssVar("--color-primary", "#2563eb"),
        secondary: cssVar("--color-secondary", "#7c3aed"),
        accent: cssVar("--color-accent", "#0891b2"),
        muted: cssVar("--color-base-content", "#6b7280"),
        grid: cssVar("--color-base-300", "#e5e7eb"),
        text: cssVar("--color-base-content", "#374151"),
        surface: cssVar("--color-base-100", "#ffffff"),
        palette: [
            cssVar("--color-primary", "#2563eb"),
            cssVar("--color-secondary", "#7c3aed"),
            cssVar("--color-accent", "#0891b2"),
            cssVar("--color-info", "#0ea5e9"),
            cssVar("--color-warning", "#d97706"),
            cssVar("--color-success", "#16a34a"),
            cssVar("--color-error", "#dc2626"),
            cssVar("--color-neutral", "#374151"),
        ],
    };
}

/**
 * Farbe je Dataset-Rolle.
 * @param {ReturnType<typeof readTheme>} theme
 * @param {DatasetRole} role
 * @param {number} index
 * @returns {string}
 */
function colorForRole(theme, role, index) {
    switch (role) {
        case "second":
            return theme.secondary;
        case "compare":
            return theme.accent;
        case "ideal":
            return theme.muted;
        case "band":
            return theme.palette[index % theme.palette.length];
        default:
            return theme.primary;
    }
}

/**
 * Diagonale Schraffur als CanvasPattern (Kontrastband — Farbe nie alleiniger
 * Träger). Fällt bei fehlendem 2D-Kontext auf die Vollfarbe zurück.
 * @param {string} color
 * @returns {CanvasPattern|string}
 */
function hatchPattern(color) {
    const tile = document.createElement("canvas");
    tile.width = 6;
    tile.height = 6;
    const ctx = tile.getContext("2d");
    if (ctx === null) {
        return color;
    }
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, 6);
    ctx.lineTo(6, 0);
    ctx.stroke();
    const pattern = ctx.createPattern(tile, "repeat");
    return pattern !== null ? pattern : color;
}

/**
 * Baut die Chart.js-Konfiguration aus der Spezifikation.
 * @param {ChartSpec} spec
 * @param {ReturnType<typeof readTheme>} theme
 * @param {boolean} reduceMotion
 * @returns {Record<string, any>}
 */
function buildConfig(spec, theme, reduceMotion) {
    const stacked = spec.stacked === true;
    const manyPoints = spec.labels.length > 12;

    const datasets = spec.datasets.map((ds, index) => {
        const base = colorForRole(theme, ds.role, index);
        const isLine = ds.kind === "line";
        /** @type {Record<string, any>} */
        const dataset = {
            type: ds.kind,
            label: ds.label,
            data: ds.data,
            borderColor: base,
            borderWidth: isLine ? 2 : ds.hatch === true ? 1.5 : 0,
            spanGaps: true,
            stack: stacked ? "stack" : undefined,
        };
        if (isLine) {
            dataset.backgroundColor = base;
            dataset.pointRadius = ds.role === "compare" ? 2.5 : 3;
            dataset.pointHoverRadius = 5;
            dataset.tension = 0.25;
            dataset.fill = false;
            if (ds.dashed === true) {
                dataset.borderDash = [5, 4];
            }
        } else {
            dataset.borderRadius = 3;
            dataset.maxBarThickness = 42;
            dataset.backgroundColor = ds.hatch === true ? hatchPattern(base) : gradientFor(base);
        }
        return dataset;
    });

    if (typeof spec.median === "number") {
        datasets.push({
            type: "line",
            label: "Median",
            data: spec.labels.map(() => spec.median),
            borderColor: theme.muted,
            borderWidth: 1,
            borderDash: [2, 3],
            pointRadius: 0,
            fill: false,
            stack: undefined,
        });
    }

    /** @type {Record<string, any>} */
    const plugins = {
        legend: { display: spec.datasets.length > 1, labels: { color: theme.text } },
        tooltip: { mode: "index", intersect: false },
    };
    if (manyPoints) {
        plugins.zoom = {
            pan: { enabled: true, mode: "x" },
            zoom: { wheel: { enabled: true }, drag: { enabled: false }, pinch: { enabled: true }, mode: "x" },
        };
    }

    return {
        type: spec.type,
        data: { labels: spec.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 500 },
            interaction: { mode: "index", intersect: false },
            scales: {
                x: {
                    stacked,
                    grid: { color: theme.grid },
                    ticks: { color: theme.text, maxRotation: spec.labels.length > 8 ? 40 : 0, autoSkip: true },
                    title: { display: typeof spec.xLabel === "string" && spec.xLabel !== "", text: spec.xLabel, color: theme.text },
                },
                y: {
                    stacked,
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    ticks: { color: theme.text },
                    title: { display: typeof spec.yLabel === "string" && spec.yLabel !== "", text: spec.yLabel, color: theme.text },
                },
            },
            plugins,
        },
    };
}

/**
 * Weicher Vertikal-Verlauf für Säulen; als scriptable Funktion, weil die
 * Chart-Fläche erst zur Zeichenzeit bekannt ist.
 * @param {string} color
 * @returns {(context: any) => CanvasGradient|string}
 */
function gradientFor(color) {
    return (context) => {
        const chart = context.chart;
        const { ctx, chartArea } = chart;
        if (!chartArea) {
            return color;
        }
        const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
        gradient.addColorStop(0, withAlpha(color, 0.55));
        gradient.addColorStop(1, color);
        return gradient;
    };
}

/**
 * Hängt eine Alpha-Stufe an eine Themefarbe. Unterstützt hex und moderne
 * Farbfunktionen (oklch/rgb) über `color-mix` mit Transparenz-Fallback.
 * @param {string} color
 * @param {number} alpha
 * @returns {string}
 */
function withAlpha(color, alpha) {
    if (/^#([0-9a-f]{6})$/i.test(color)) {
        const value = Math.round(alpha * 255).toString(16).padStart(2, "0");
        return `${color}${value}`;
    }
    return `color-mix(in oklab, ${color} ${Math.round(alpha * 100)}%, transparent)`;
}

/**
 * Wandelt ein einzelnes `figure.wd-chart` in ein Chart.js-Canvas um.
 * @param {HTMLElement} figure
 */
async function enhance(figure) {
    const holder = figure.querySelector("[data-wd-chart]");
    if (!(holder instanceof HTMLElement) || holder.dataset.wdChartInit === "1") {
        return;
    }
    holder.dataset.wdChartInit = "1";

    /** @type {ChartSpec|null} */
    let spec = null;
    try {
        spec = JSON.parse(holder.dataset.wdChart ?? "null");
    } catch {
        return;
    }
    if (spec === null || !Array.isArray(spec.datasets) || spec.datasets.length === 0) {
        return;
    }

    try {
        const Chart = await loadChartLib();
        const canvas = document.createElement("canvas");
        canvas.setAttribute("role", "img");
        canvas.setAttribute("aria-label", spec.title ?? "");
        holder.appendChild(canvas);
        holder.hidden = false;

        const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        const config = buildConfig(spec, readTheme(), reduceMotion);

        const urls = Array.isArray(spec.urls) ? spec.urls : [];
        config.options.onClick = (/** @type {any} */ _event, /** @type {any[]} */ elements) => {
            if (elements.length === 0) {
                return;
            }
            const target = urls[elements[0].index];
            if (typeof target === "string" && target !== "") {
                window.location.assign(target);
            }
        };

        new Chart(canvas, config);

        const svg = figure.querySelector(".wd-chart-svg");
        if (svg instanceof SVGElement) {
            svg.setAttribute("hidden", "hidden");
        }
    } catch {
        // Enhancement gescheitert → SVG-Fallback sichtbar lassen.
        holder.hidden = true;
    }
}

/**
 * Findet und verbessert alle Diagramme unterhalb von `root`. No-op, wenn keine
 * enhancebaren Diagramme vorhanden sind (Chart.js wird dann nie geladen).
 * @param {ParentNode} [root]
 */
export function initCharts(root) {
    const scope = root ?? document;
    const figures = scope.querySelectorAll("figure.wd-chart");
    figures.forEach((figure) => {
        if (figure instanceof HTMLElement && figure.querySelector("[data-wd-chart]") !== null) {
            void enhance(figure);
        }
    });
}
