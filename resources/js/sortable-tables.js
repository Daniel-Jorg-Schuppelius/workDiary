/**
 * Lightweight client-side table sorting.
 *
 * Markup contract:
 *   <table data-sortable> ... <th data-sort [data-sort-type="..."]>Header</th>
 *
 *   data-sort-type:
 *     - "string" (default): localeCompare (de)
 *     - "number": parseFloat after stripping non-digits/decimal
 *     - "date": Date.parse on full text or `data-sort-value`
 *     - any: prefer cell `data-sort-value` if present
 *
 *   <th data-sort-default="asc|desc"> – initial sort & icon shown on load
 *
 * Sort state is kept per table in memory (no URL changes). Headers append a
 * span.sort-icon (↕/↑/↓). Only direct child <tbody> rows are sorted.
 *
 * For paginated tables this sorts only the current page – which is the
 * accepted trade-off given that we did not want to refactor every controller.
 */

const collator = new Intl.Collator("de", {
    numeric: true,
    sensitivity: "base",
});

function getCellValue(row, columnIndex) {
    const cell = row.children[columnIndex];
    if (!cell) return "";
    if (cell.dataset.sortValue !== undefined) return cell.dataset.sortValue;
    return (cell.textContent || "").trim();
}

function compareFactory(type) {
    if (type === "number") {
        return (a, b) => {
            const na = parseFloat(
                String(a)
                    .replace(/[^0-9.,-]/g, "")
                    .replace(",", "."),
            );
            const nb = parseFloat(
                String(b)
                    .replace(/[^0-9.,-]/g, "")
                    .replace(",", "."),
            );
            const va = Number.isFinite(na) ? na : -Infinity;
            const vb = Number.isFinite(nb) ? nb : -Infinity;
            return va - vb;
        };
    }
    if (type === "date") {
        return (a, b) => {
            const ta = Date.parse(a);
            const tb = Date.parse(b);
            const va = Number.isFinite(ta) ? ta : -Infinity;
            const vb = Number.isFinite(tb) ? tb : -Infinity;
            return va - vb;
        };
    }
    return (a, b) => collator.compare(String(a), String(b));
}

function applySort(table, columnIndex, dir) {
    const tbody = table.tBodies[0];
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll(":scope > tr")).filter(
        (row) => !row.dataset.sortIgnore,
    );
    if (rows.length < 2) return;

    const th = table.tHead?.rows[0]?.cells[columnIndex];
    const type = th?.dataset.sortType || "string";
    const cmp = compareFactory(type);
    const factor = dir === "desc" ? -1 : 1;

    rows.sort((a, b) => {
        const av = getCellValue(a, columnIndex);
        const bv = getCellValue(b, columnIndex);
        const r = cmp(av, bv);
        return r === 0 ? 0 : factor * (r > 0 ? 1 : -1);
    });

    const frag = document.createDocumentFragment();
    rows.forEach((r) => frag.appendChild(r));
    tbody.appendChild(frag);
}

function setIcons(table, activeIndex, dir) {
    const ths = table.tHead?.rows[0]?.cells;
    if (!ths) return;
    Array.from(ths).forEach((th, idx) => {
        if (!Object.prototype.hasOwnProperty.call(th.dataset, "sort")) return;
        let icon = th.querySelector(".sort-icon");
        if (!icon) {
            icon = document.createElement("span");
            icon.className = "sort-icon ml-1 text-base-content/50";
            th.appendChild(icon);
        }
        if (idx === activeIndex) {
            icon.textContent = dir === "asc" ? "↑" : "↓";
            icon.classList.remove("text-base-content/50");
            icon.classList.add("text-base-content");
        } else {
            icon.textContent = "↕";
            icon.classList.add("text-base-content/50");
            icon.classList.remove("text-base-content");
        }
    });
}

function initTable(table) {
    if (table.dataset.sortableInit === "1") return;
    table.dataset.sortableInit = "1";
    const headRow = table.tHead?.rows[0];
    if (!headRow) return;

    let state = { index: -1, dir: "asc" };

    Array.from(headRow.cells).forEach((th, idx) => {
        if (!Object.prototype.hasOwnProperty.call(th.dataset, "sort")) return;
        th.classList.add("cursor-pointer", "select-none");
        th.addEventListener("click", () => {
            const dir =
                state.index === idx && state.dir === "asc" ? "desc" : "asc";
            state = { index: idx, dir };
            applySort(table, idx, dir);
            setIcons(table, idx, dir);
        });

        if (th.dataset.sortDefault && state.index === -1) {
            const dir = th.dataset.sortDefault === "desc" ? "desc" : "asc";
            state = { index: idx, dir };
            applySort(table, idx, dir);
        }
    });

    setIcons(table, state.index, state.dir);
}

function initAll(root = document) {
    root.querySelectorAll("table[data-sortable]").forEach(initTable);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initAll());
} else {
    initAll();
}

// Re-init on dynamic DOM swaps (e.g. modal/htmx).
document.addEventListener("sortable-tables:refresh", (e) => {
    initAll(e.target instanceof Element ? e.target : document);
});

export { initAll as initSortableTables };
