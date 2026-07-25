#!/usr/bin/env node
/**
 * Typecheck-Gate für resources/js mit Baseline — analog zu phpstan-baseline.neon.
 *
 * Hintergrund: `checkJs` über die gewachsenen Module meldet ~300 Befunde, fast
 * ausschließlich DOM-Typisierungsrauschen (`querySelector` liefert `Element`,
 * der Code nutzt `.value`/`.dataset`/`.style`). Das sind keine Bugs, aber sie
 * würden ein Null-Fehler-Gate unerreichbar machen.
 *
 * Die Baseline friert den Ist-Stand ein. Das Gate schlägt an, sobald ein NEUER
 * Befund entsteht — insbesondere ein Verstoß gegen die SafeHtml-Grenze aus
 * resources/js/lib/html.js.
 *
 *   node scripts/typecheck.mjs            prüft gegen die Baseline
 *   node scripts/typecheck.mjs --update   schreibt die Baseline neu
 */

import { execFileSync } from "node:child_process";
import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");
const BASELINE = join(ROOT, "typecheck-baseline.json");
const UPDATE = process.argv.includes("--update");

// tsc endet bei gefundenen Fehlern mit Exit-Code 2 — das ist hier der Normalfall
// und kein Ausführungsfehler, daher wird der Status ignoriert.
function runTsc() {
    try {
        return execFileSync("npx", ["tsc", "--noEmit", "--pretty", "false"], {
            cwd: ROOT,
            encoding: "utf8",
            stdio: ["ignore", "pipe", "pipe"],
        });
    } catch (e) {
        if (e.stdout != null) return e.stdout;
        throw e;
    }
}

const LINE = /^(.+?)\((\d+),(\d+)\): error (TS\d+): (.*)$/;

/**
 * Schlüssel bewusst OHNE Zeile/Spalte: sonst gilt jede Verschiebung durch eine
 * unbeteiligte Änderung als neuer Befund. Bezeichner in der Meldung bleiben
 * erhalten — sie unterscheiden die Fälle innerhalb einer Datei.
 */
function parse(output) {
    const counts = new Map();
    for (const raw of output.split(/\r?\n/)) {
        const m = LINE.exec(raw.trim());
        if (!m) continue;
        const [, file, , , code, message] = m;
        const key = `${file.replace(/\\/g, "/")}|${code}|${message}`;
        counts.set(key, (counts.get(key) ?? 0) + 1);
    }
    return counts;
}

const current = parse(runTsc());

if (UPDATE) {
    const sorted = Object.fromEntries([...current.entries()].sort(([a], [b]) => a.localeCompare(b)));
    writeFileSync(BASELINE, `${JSON.stringify(sorted, null, 2)}\n`, "utf8");
    const total = [...current.values()].reduce((a, b) => a + b, 0);
    console.log(`Baseline geschrieben: ${current.size} Einträge, ${total} Befunde.`);
    process.exit(0);
}

if (!existsSync(BASELINE)) {
    console.error("typecheck-baseline.json fehlt — einmalig `npm run typecheck:baseline` ausführen.");
    process.exit(1);
}

/** @type {Record<string, number>} */
const baseline = JSON.parse(readFileSync(BASELINE, "utf8"));

const added = [];
for (const [key, count] of current) {
    const allowed = baseline[key] ?? 0;
    if (count > allowed) added.push({ key, count, allowed });
}

if (added.length > 0) {
    console.error("Neue Typfehler gegenüber der Baseline:\n");
    for (const { key, count, allowed } of added) {
        const [file, code, message] = key.split("|");
        const times = allowed > 0 ? ` (${allowed} → ${count}×)` : "";
        console.error(`  ${file}\n    ${code}: ${message}${times}\n`);
    }
    console.error(
        "Beheben — oder, falls beabsichtigt, die Baseline mit `npm run typecheck:baseline` neu schreiben.",
    );
    process.exit(1);
}

// Behobene Befunde melden, damit die Baseline nicht unbemerkt veraltet.
const fixed = Object.entries(baseline).filter(([key, allowed]) => (current.get(key) ?? 0) < allowed);
if (fixed.length > 0) {
    console.log(`Typecheck grün. ${fixed.length} Baseline-Einträge sind behoben —`);
    console.log("`npm run typecheck:baseline` hält die Datei aktuell.");
} else {
    console.log(`Typecheck grün (${[...current.values()].reduce((a, b) => a + b, 0)} Baseline-Befunde unverändert).`);
}
