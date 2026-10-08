#!/usr/bin/env node
/**
 * Typecheck-Gate über alle Teilprojekte aus tsconfig.json — analog zu
 * phpstan-baseline.neon mit Baseline.
 *
 * Die Teilprojekte (Browser, Service Worker, Node) laufen unter `strict`. Die
 * Altbefunde aus der Umstellung stehen in typecheck-baseline.json; das Gate
 * schlägt an, sobald ein NEUER Befund entsteht — insbesondere ein Verstoß gegen
 * die SafeHtml-Grenze aus resources/js/lib/html.js.
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

/** @type {{ references: { path: string }[] }} */
const solution = JSON.parse(readFileSync(join(ROOT, "tsconfig.json"), "utf8"));

/**
 * tsc endet bei Befunden mit einem Exit-Code ungleich 0 — das ist hier der
 * Normalfall, daher zählt die Ausgabe. Ohne Ausgabe ist tsc selbst gescheitert.
 *
 * @param {string} project
 * @returns {string}
 */
function runTsc(project) {
    try {
        return execFileSync("npx", ["tsc", "-p", project, "--pretty", "false"], {
            cwd: ROOT,
            encoding: "utf8",
            stdio: ["ignore", "pipe", "pipe"],
        });
    } catch (error) {
        const stdout = /** @type {{ stdout?: string }} */ (error).stdout;
        if (stdout) return stdout;
        throw error;
    }
}

const LOCATED = /^(.+?)\((\d+),(\d+)\): error (TS\d+): (.*)$/;
const GLOBAL = /^error (TS\d+): (.*)$/;

/**
 * Schlüssel bewusst OHNE Zeile/Spalte: sonst gilt jede Verschiebung durch eine
 * unbeteiligte Änderung als neuer Befund. Bezeichner in der Meldung bleiben
 * erhalten — sie unterscheiden die Fälle innerhalb einer Datei.
 *
 * Eine Datei kann in mehreren Teilprojekten landen (tests/frontend importiert
 * resources/js/lib); derselbe Befund an derselben Stelle zählt einmal.
 *
 * @param {string[]} outputs
 * @returns {Map<string, number>}
 */
function parse(outputs) {
    /** @type {Set<string>} */
    const seen = new Set();
    /** @type {Map<string, number>} */
    const counts = new Map();
    for (const output of outputs) {
        for (const raw of output.split(/\r?\n/)) {
            const line = raw.trim();
            const located = LOCATED.exec(line);
            const global = located ? null : GLOBAL.exec(line);
            if (!located && !global) continue;
            const [file, at, code, message] = located
                ? [located[1].replace(/\\/g, "/"), `${located[2]},${located[3]}`, located[4], located[5]]
                : ["<global>", "", global?.[1] ?? "", global?.[2] ?? ""];
            const site = `${file}|${at}|${code}|${message}`;
            if (seen.has(site)) continue;
            seen.add(site);
            const key = `${file}|${code}|${message}`;
            counts.set(key, (counts.get(key) ?? 0) + 1);
        }
    }
    return counts;
}

const current = parse(solution.references.map((ref) => runTsc(ref.path)));
const total = [...current.values()].reduce((a, b) => a + b, 0);

if (UPDATE) {
    const sorted = Object.fromEntries([...current.entries()].sort(([a], [b]) => a.localeCompare(b)));
    writeFileSync(BASELINE, `${JSON.stringify(sorted, null, 2)}\n`, "utf8");
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
    console.log(`Typecheck grün (${total} Baseline-Befunde unverändert).`);
}
