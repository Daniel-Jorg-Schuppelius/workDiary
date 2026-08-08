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
 * @property {'percent'} [axis]
 */
/**
 * @typedef {Object} ChartSpec
 * @property {ChartKind|'boxplot'|'scatter'} type
 * @property {boolean} [stacked]
 * @property {boolean} [horizontal]
 * @property {boolean} [waterfall]
 * @property {boolean} [bullet]
 * @property {string} title
 * @property {string} [unit]
 * @property {string} [xLabel]
 * @property {string} [yLabel]
 * @property {number|null} [median]
 * @property {string[]} labels
 * @property {Array<string|null>} [urls]
 * @property {ChartDataset[]} [datasets]
 * @property {Array<{min:number,q1:number,median:number,q3:number,max:number}>} [boxes]
 * @property {Array<[number, number]>} [ranges]
 * @property {Array<'total'|'up'|'down'>} [kinds]
 * @property {number[]} [values]
 * @property {Array<number|null>} [targets]
 * @property {number[][]} [bands]
 * @property {string} [targetLabel]
 * @property {Array<{x:number,y:number,label?:string}>} [points]
 * @property {Array<{label:string,value:number}>} [percentiles]
 */

/** @type {Promise<any>|null} */
let chartLibPromise = null;

/** Lädt Chart.js + Zoom-/Boxplot-Plugin einmalig und registriert die Bausteine. */
function loadChartLib() {
    if (chartLibPromise === null) {
        chartLibPromise = Promise.all([
            import("chart.js"),
            import("chartjs-plugin-zoom"),
            import("@sgratzl/chartjs-chart-boxplot"),
        ]).then(([core, zoom, box]) => {
            core.Chart.register(
                ...core.registerables,
                zoom.default,
                box.BoxPlotController,
                box.BoxAndWiskers,
            );
            return core.Chart;
        });
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
    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();
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
        success: cssVar("--color-success", "#16a34a"),
        error: cssVar("--color-error", "#dc2626"),
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
    if (spec.type === "boxplot") {
        return buildBoxplotConfig(spec, theme, reduceMotion);
    }
    if (spec.type === "scatter") {
        return buildScatterConfig(spec, theme, reduceMotion);
    }
    if (spec.waterfall === true) {
        return buildWaterfallConfig(spec, theme, reduceMotion);
    }
    if (spec.bullet === true) {
        return buildBulletConfig(spec, theme, reduceMotion);
    }

    const datasetsIn = spec.datasets ?? [];
    const stacked = spec.stacked === true;
    const horizontal = spec.horizontal === true;
    const hasPercent = datasetsIn.some((ds) => ds.axis === "percent");
    const manyPoints = spec.labels.length > 12;

    const datasets = datasetsIn.map((ds, index) => {
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
            dataset.backgroundColor =
                ds.hatch === true
                    ? hatchPattern(base)
                    : gradientFor(base, horizontal);
        }
        if (ds.axis === "percent") {
            dataset.yAxisID = "y1";
            dataset.fill = false;
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
        legend: {
            display: (spec.datasets ?? []).length > 1,
            labels: { color: theme.text },
        },
        tooltip: { mode: "index", intersect: false },
    };
    if (manyPoints) {
        plugins.zoom = {
            pan: { enabled: true, mode: horizontal ? "y" : "x" },
            zoom: {
                wheel: { enabled: true },
                drag: { enabled: false },
                pinch: { enabled: true },
                mode: horizontal ? "y" : "x",
            },
        };
    }

    const catTitle = typeof spec.xLabel === "string" ? spec.xLabel : "";
    const valTitle = typeof spec.yLabel === "string" ? spec.yLabel : "";
    /** @type {Record<string, any>} */
    const scales = {
        x: {
            stacked,
            beginAtZero: horizontal,
            grid: { color: theme.grid },
            ticks: {
                color: theme.text,
                maxRotation: horizontal ? 0 : spec.labels.length > 8 ? 40 : 0,
                autoSkip: true,
            },
            title: {
                display: (horizontal ? valTitle : catTitle) !== "",
                text: horizontal ? valTitle : catTitle,
                color: theme.text,
            },
        },
        y: {
            stacked,
            beginAtZero: !horizontal,
            grid: { color: theme.grid },
            ticks: { color: theme.text },
            title: {
                display: (horizontal ? catTitle : valTitle) !== "",
                text: horizontal ? catTitle : valTitle,
                color: theme.text,
            },
        },
    };
    if (hasPercent) {
        scales.y1 = {
            position: "right",
            beginAtZero: true,
            min: 0,
            max: 100,
            grid: { drawOnChartArea: false },
            ticks: {
                color: theme.text,
                callback: (/** @type {number} */ value) => `${value}%`,
            },
        };
    }

    return {
        type: spec.type,
        data: { labels: spec.labels, datasets },
        options: {
            indexAxis: horizontal ? "y" : "x",
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 500 },
            interaction: { mode: "index", intersect: false },
            scales,
            plugins,
        },
    };
}

/**
 * Punktdiagramm mit horizontalen Perzentil-Linien. Die X-Position ist ordinal
 * (Reihenfolge der Punkte = fachliche Sortierung, z. B. Inaktivität); die
 * X-Ticks werden ausgeblendet, die Bedeutung steckt in Tooltip/Tabelle.
 * @param {ChartSpec} spec
 * @param {ReturnType<typeof readTheme>} theme
 * @param {boolean} reduceMotion
 * @returns {Record<string, any>}
 */
function buildScatterConfig(spec, theme, reduceMotion) {
    const points = spec.points ?? [];
    const percentiles = spec.percentiles ?? [];
    const lastX = Math.max(0, points.length - 1);
    const dashes = [
        [4, 3],
        [7, 3],
        [2, 3],
    ];
    /** @type {Array<Record<string, any>>} */
    const datasets = [
        {
            type: "scatter",
            label: spec.unit ?? "",
            data: points,
            backgroundColor: withAlpha(theme.primary, 0.8),
            borderColor: theme.surface,
            borderWidth: 1,
            pointRadius: 4,
            pointHoverRadius: 6,
            order: 1,
        },
    ];
    percentiles.forEach((p, i) => {
        datasets.push({
            type: "line",
            label: p.label,
            data: [
                { x: 0, y: p.value },
                { x: lastX, y: p.value },
            ],
            borderColor: theme.muted,
            borderWidth: 1,
            borderDash: dashes[i % dashes.length],
            pointRadius: 0,
            fill: false,
            order: 2,
        });
    });

    return {
        type: "scatter",
        data: { datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 500 },
            plugins: {
                legend: {
                    display: percentiles.length > 0,
                    labels: {
                        color: theme.text,
                        filter: (/** @type {any} */ item) => item.datasetIndex !== 0,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (/** @type {any} */ ctx) => {
                            const raw = ctx.raw ?? {};
                            const name = typeof raw.label === "string" ? raw.label : "";
                            return `${name}: ${ctx.parsed.y} ${spec.unit ?? ""}`.trim();
                        },
                    },
                },
            },
            scales: {
                x: {
                    type: "linear",
                    min: -0.5,
                    max: lastX + 0.5,
                    grid: { color: theme.grid },
                    ticks: { display: false },
                    title: {
                        display: typeof spec.xLabel === "string" && spec.xLabel !== "",
                        text: spec.xLabel,
                        color: theme.text,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    ticks: { color: theme.text },
                    title: {
                        display: typeof spec.yLabel === "string" && spec.yLabel !== "",
                        text: spec.yLabel,
                        color: theme.text,
                    },
                },
            },
        },
    };
}

/**
 * Boxplot-Konfiguration (horizontal): die fünf Kennwerte kommen vorberechnet
 * aus dem Builder; das Boxplot-Plugin rendert Box/Whisker/Median.
 * @param {ChartSpec} spec
 * @param {ReturnType<typeof readTheme>} theme
 * @param {boolean} reduceMotion
 * @returns {Record<string, any>}
 */
function buildBoxplotConfig(spec, theme, reduceMotion) {
    return {
        type: "boxplot",
        data: {
            labels: spec.labels,
            datasets: [
                {
                    label: spec.unit ?? "",
                    data: spec.boxes ?? [],
                    backgroundColor: withAlpha(theme.primary, 0.3),
                    borderColor: theme.primary,
                    borderWidth: 1.5,
                    medianColor: theme.primary,
                    itemRadius: 0,
                    outlierRadius: 2,
                    outlierBackgroundColor: theme.muted,
                },
            ],
        },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 500 },
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    grid: { color: theme.grid },
                    ticks: { color: theme.text },
                },
                y: {
                    grid: { color: theme.grid },
                    ticks: { color: theme.text },
                },
            },
        },
    };
}

/**
 * Waterfall/Brücke: Start-/Endbestand + schwebende Δ-Balken (`[from,to]`),
 * Zunahmen grün, Abnahmen rot, Bestandssäulen neutral.
 * @param {ChartSpec} spec
 * @param {ReturnType<typeof readTheme>} theme
 * @param {boolean} reduceMotion
 * @returns {Record<string, any>}
 */
function buildWaterfallConfig(spec, theme, reduceMotion) {
    const ranges = spec.ranges ?? [];
    const kinds = spec.kinds ?? [];
    const colorFor = (/** @type {string} */ kind) =>
        kind === "up"
            ? theme.success
            : kind === "down"
              ? theme.error
              : theme.muted;
    return {
        type: "bar",
        data: {
            labels: spec.labels,
            datasets: [
                {
                    label: spec.unit ?? "",
                    data: ranges,
                    backgroundColor: ranges.map((_, i) =>
                        withAlpha(colorFor(kinds[i]), 0.75),
                    ),
                    borderColor: ranges.map((_, i) => colorFor(kinds[i])),
                    borderWidth: 1,
                    borderSkipped: false,
                    borderRadius: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 500 },
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    grid: { color: theme.grid },
                    ticks: {
                        color: theme.text,
                        maxRotation: spec.labels.length > 8 ? 40 : 0,
                        autoSkip: true,
                    },
                },
                y: {
                    grid: { color: theme.grid },
                    ticks: { color: theme.text },
                },
            },
        },
    };
}

/**
 * Bullet: horizontale Ist-Balken je Kennzahl mit Ziel-Marker (Raute).
 * Qualitative Bänder sind sekundär und bleiben der SVG-Variante vorbehalten.
 * @param {ChartSpec} spec
 * @param {ReturnType<typeof readTheme>} theme
 * @param {boolean} reduceMotion
 * @returns {Record<string, any>}
 */
function buildBulletConfig(spec, theme, reduceMotion) {
    const targets = spec.targets ?? [];
    /** @type {Array<Record<string, any>>} */
    const datasets = [
        {
            type: "bar",
            label: spec.yLabel ?? spec.unit ?? "",
            data: spec.values ?? [],
            backgroundColor: gradientFor(theme.primary, true),
            borderColor: theme.primary,
            borderWidth: 0,
            borderRadius: 3,
            maxBarThickness: 18,
            order: 2,
        },
    ];
    const targetPoints = targets.map((t, i) =>
        t === null ? null : { x: t, y: spec.labels[i] },
    );
    const hasTarget = targetPoints.some((p) => p !== null);
    if (hasTarget) {
        datasets.push({
            type: "scatter",
            label: spec.targetLabel ?? "",
            data: targetPoints,
            pointStyle: "rectRot",
            radius: 6,
            hoverRadius: 7,
            borderColor: theme.accent,
            backgroundColor: theme.accent,
            order: 1,
        });
    }
    return {
        type: "bar",
        data: { labels: spec.labels, datasets },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            animation: reduceMotion ? false : { duration: 500 },
            plugins: {
                legend: { display: hasTarget, labels: { color: theme.text } },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: theme.grid },
                    ticks: { color: theme.text },
                    title: {
                        display:
                            typeof spec.yLabel === "string" &&
                            spec.yLabel !== "",
                        text: spec.yLabel,
                        color: theme.text,
                    },
                },
                y: {
                    type: "category",
                    grid: { color: theme.grid },
                    ticks: { color: theme.text },
                },
            },
        },
    };
}

/**
 * Weicher Verlauf für Säulen; als scriptable Funktion, weil die Chart-Fläche
 * erst zur Zeichenzeit bekannt ist. Richtung folgt der Balkenausrichtung.
 * @param {string} color
 * @param {boolean} [horizontal]
 * @returns {(context: any) => CanvasGradient|string}
 */
function gradientFor(color, horizontal) {
    return (context) => {
        const chart = context.chart;
        const { ctx, chartArea } = chart;
        if (!chartArea) {
            return color;
        }
        const gradient =
            horizontal === true
                ? ctx.createLinearGradient(
                      chartArea.left,
                      0,
                      chartArea.right,
                      0,
                  )
                : ctx.createLinearGradient(
                      0,
                      chartArea.bottom,
                      0,
                      chartArea.top,
                  );
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
        const value = Math.round(alpha * 255)
            .toString(16)
            .padStart(2, "0");
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
    if (
        !(holder instanceof HTMLElement) ||
        holder.dataset.wdChartInit === "1"
    ) {
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
    if (spec === null || typeof spec.type !== "string") {
        return;
    }
    // bar/line brauchen datasets; boxplot/scatter/waterfall/bullet tragen ihre Werte separat.
    const specialType =
        spec.type === "boxplot" ||
        spec.type === "scatter" ||
        spec.waterfall === true ||
        spec.bullet === true;
    if (
        !specialType &&
        (!Array.isArray(spec.datasets) || spec.datasets.length === 0)
    ) {
        return;
    }

    try {
        const Chart = await loadChartLib();
        const canvas = document.createElement("canvas");
        canvas.setAttribute("role", "img");
        canvas.setAttribute("aria-label", spec.title ?? "");
        holder.appendChild(canvas);
        holder.hidden = false;

        const reduceMotion = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        ).matches;
        const config = buildConfig(spec, readTheme(), reduceMotion);

        const urls = Array.isArray(spec.urls) ? spec.urls : [];
        config.options.onClick = (
            /** @type {any} */ _event,
            /** @type {any[]} */ elements,
        ) => {
            if (elements.length === 0 || elements[0].datasetIndex !== 0) {
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
        if (
            figure instanceof HTMLElement &&
            figure.querySelector("[data-wd-chart]") !== null
        ) {
            void enhance(figure);
        }
    });
}
