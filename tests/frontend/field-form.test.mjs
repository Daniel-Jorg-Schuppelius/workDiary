/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : field-form.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für lange Formulare (resources/js/lib/field-form.js,
 * Feature 203): Sichtbarkeit und „beantwortet“ wie auf dem Server, Zählung je
 * Abschnitt, Eingabenamen und die Warteschlange fürs Speichern je Feld —
 * zwei schnell geänderte Felder werden beide gespeichert, jede Anfrage trägt
 * die Version der vorigen Antwort, ein Konflikt hält an statt zu überschreiben.
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { SaveQueue, isAnswered, isVisible, parseFieldName, progressText, sectionProgress } from "../../resources/js/lib/field-form.js";

/** @typedef {import("../../resources/js/lib/field-form.js").SaveJob} SaveJob */

const now = () => Promise.resolve();

test("Sichtbarkeit wie FieldSchema::isVisible", () => {
    const vals = { schaden: "ja", liste: "a,b", leer: "", haken: "0" };
    assert.equal(isVisible(undefined, vals), true);
    assert.equal(isVisible({ field: "schaden", op: "eq", value: "ja" }, vals), true);
    assert.equal(isVisible({ field: "schaden", op: "ne", value: "ja" }, vals), false);
    assert.equal(isVisible({ field: "schaden", op: "in", value: "nein, ja" }, vals), true);
    assert.equal(isVisible({ field: "leer", op: "filled" }, vals), false);
    assert.equal(isVisible({ field: "haken", op: "filled" }, vals), false);
    assert.equal(isVisible({ field: "fehlt", op: "eq", value: "" }, vals), true);
});

test("Beantwortet: leer offen, nicht angehakte Checkbox offen, Zahl 0 beantwortet", () => {
    assert.equal(isAnswered(""), false);
    assert.equal(isAnswered("  "), false);
    assert.equal(isAnswered(undefined), false);
    assert.equal(isAnswered("0"), true);
    assert.equal(isAnswered("0", true), false);
    assert.equal(isAnswered("1", true), true);
    assert.equal(isAnswered("a,b"), true);
});

test("Eingabenamen: Wert, Liste, Ergänzung; Dateien und Kopffelder gehören nicht dazu", () => {
    assert.deepEqual(parseFieldName("values[name]", "values"), { key: "name", kind: "value" });
    assert.deepEqual(parseFieldName("values[farben][]", "values"), { key: "farben", kind: "list" });
    assert.deepEqual(parseFieldName("notes[farben]", "values"), { key: "farben", kind: "note" });
    assert.deepEqual(parseFieldName("catalog[menge]", "catalog"), { key: "menge", kind: "value" });
    assert.equal(parseFieldName("files[foto]", "values"), null);
    assert.equal(parseFieldName("signatures[unterschrift]", "values"), null);
    assert.equal(parseFieldName("form_template_id", "values"), null);
    assert.equal(parseFieldName("notes[x][]", "values"), null);
});

test("Zählung je Abschnitt ohne unsichtbare Felder", () => {
    const answered = new Set(["a", "c"]);
    const hidden = new Set(["d"]);
    const progress = sectionProgress(
        { start: ["a", "b"], s2: ["c", "d"], leer: [] },
        (k) => answered.has(k),
        (k) => !hidden.has(k),
    );
    assert.deepEqual(progress, { start: { answered: 1, total: 2 }, s2: { answered: 1, total: 1 }, leer: { answered: 0, total: 0 } });
    assert.equal(progressText(":answered von :total beantwortet", progress.start), "1 von 2 beantwortet");
    assert.equal(progressText(":answered/:total", undefined), "0/0");
});

test("Zwei schnell geänderte Felder werden beide gespeichert, nacheinander mit fortlaufender Version", async () => {
    /** @type {Array<{key: string, value: unknown, version: number}>} */
    const sent = [];
    let server = 4;
    const queue = new SaveQueue({
        version: 4,
        wait: now,
        send: async (job, version) => {
            sent.push({ key: job.key, value: job.value, version });
            assert.equal(version, server, "Anfrage trägt die Version der vorigen Antwort");
            server++;
            return { ok: true, version: server };
        },
    });
    queue.enqueue({ key: "a", value: "1" });
    queue.enqueue({ key: "b", value: "2" });
    await queue.whenIdle();
    assert.deepEqual(sent.map((s) => [s.key, s.version]), [["a", 4], ["b", 5]]);
    assert.equal(queue.version, 6);
    assert.equal(queue.state, "idle");
    assert.equal(queue.busy, false);
});

test("Ein noch nicht gesendeter Wert wird durch den neueren ersetzt; ein unterwegs geänderter folgt", async () => {
    /** @type {Array<[string, unknown]>} */
    const sent = [];
    /** @type {(() => void) | null} */
    let release = null;
    let version = 1;
    const queue = new SaveQueue({
        version: 1,
        wait: now,
        send: async (job) => {
            sent.push([job.key, job.value]);
            if (sent.length === 1) await new Promise((resolve) => (release = () => resolve(undefined)));
            return { ok: true, version: ++version };
        },
    });
    queue.enqueue({ key: "a", value: "erst" });
    await Promise.resolve();
    queue.enqueue({ key: "a", value: "zweit" }); // ersetzt den laufenden → folgt
    queue.enqueue({ key: "b", value: "x" });
    queue.enqueue({ key: "b", value: "y" }); // ersetzt den wartenden
    /** @type {() => void} */ (/** @type {unknown} */ (release))();
    await queue.whenIdle();
    assert.deepEqual(sent, [["a", "erst"], ["a", "zweit"], ["b", "y"]]);
});

test("Ergänzung und Wert desselben Felds werden zusammengeführt", async () => {
    /** @type {SaveJob[]} */
    const sent = [];
    const queue = new SaveQueue({ version: 1, wait: now, send: async (job) => (sent.push(job), { ok: true, version: 2 }) });
    queue.running = true; // blockiert den Lauf, bis beide stehen
    queue.enqueue({ key: "wahl", value: "a" });
    queue.enqueue({ key: "wahl", note: "Hinweis" });
    queue.running = false;
    await queue.run();
    assert.deepEqual(sent, [{ key: "wahl", value: "a", note: "Hinweis" }]);
});

test("Versionskonflikt hält an statt zu überschreiben; Fortsetzen mit der Serverversion", async () => {
    /** @type {Array<[string, number]>} */
    const sent = [];
    let conflict = true;
    /** @type {string[]} */
    const states = [];
    const queue = new SaveQueue({
        version: 3,
        wait: now,
        onState: (s) => states.push(s),
        send: async (job, version) => {
            sent.push([job.key, version]);
            if (conflict) {
                conflict = false;
                return { ok: false, kind: "conflict", version: 7 };
            }
            return { ok: true, version: version + 1 };
        },
    });
    queue.enqueue({ key: "a", value: "mein" });
    await queue.whenIdle();
    assert.equal(queue.state, "conflict");
    assert.equal(queue.conflictVersion, 7);
    assert.equal(queue.pending.length, 1, "Wert bleibt erhalten");
    queue.enqueue({ key: "b", value: "auch" }); // gesperrt: nichts geht raus
    assert.deepEqual(sent, [["a", 3]]);

    queue.resumeWith(7);
    await queue.whenIdle();
    assert.deepEqual(sent, [["a", 3], ["a", 7], ["b", 8]]);
    assert.equal(queue.state, "idle");
    assert.ok(states.includes("conflict"));
});

test("Netzfehler: wiederholen, dann aufgeben; eine neue Eingabe stößt wieder an", async () => {
    let failures = 3;
    /** @type {number[]} */
    const waits = [];
    const queue = new SaveQueue({
        version: 1,
        retryDelays: [10, 20],
        wait: async (ms) => {
            waits.push(ms);
        },
        send: async () => (failures-- > 0 ? { ok: false, kind: "offline" } : { ok: true, version: 2 }),
    });
    queue.enqueue({ key: "a", value: "1" });
    await queue.whenIdle();
    assert.deepEqual(waits, [10, 20]);
    assert.equal(queue.state, "stalled");
    assert.equal(queue.pending.length, 1);

    queue.enqueue({ key: "b", value: "2" });
    await queue.whenIdle();
    assert.equal(queue.state, "idle");
    assert.equal(queue.pending.length, 0);
    assert.equal(queue.version, 2);
});

test("Ungültiger Wert fällt aus der Schlange, die anderen laufen weiter", async () => {
    /** @type {string[]} */
    const invalid = [];
    const queue = new SaveQueue({
        version: 1,
        wait: now,
        onInvalid: (job) => invalid.push(job.key),
        send: async (job, version) => (job.key === "zahl" ? { ok: false, kind: "invalid", message: "Keine Zahl" } : { ok: true, version: version + 1 }),
    });
    queue.enqueue({ key: "zahl", value: "x" });
    queue.enqueue({ key: "text", value: "y" });
    await queue.whenIdle();
    assert.deepEqual(invalid, ["zahl"]);
    assert.equal(queue.version, 2);
});

test("Abgeschlossener Träger: Schlange schließt, weitere Eingaben gehen nicht raus", async () => {
    let calls = 0;
    const queue = new SaveQueue({ version: 1, wait: now, send: async () => (calls++, { ok: false, kind: "closed" }) });
    queue.enqueue({ key: "a", value: "1" });
    await queue.whenIdle();
    queue.enqueue({ key: "b", value: "2" });
    await queue.whenIdle();
    assert.equal(calls, 1);
    assert.equal(queue.state, "closed");
});

test("Ohne Träger: erst anlegen (prepare), dann mit dessen Version speichern — einmal", async () => {
    let prepared = 0;
    /** @type {number[]} */
    const versions = [];
    const queue = new SaveQueue({
        version: null,
        wait: now,
        prepare: async () => (prepared++, 1),
        send: async (_job, version) => (versions.push(version), { ok: true, version: version + 1 }),
    });
    queue.enqueue({ key: "a", value: "1" });
    queue.enqueue({ key: "b", value: "2" });
    await queue.whenIdle();
    assert.equal(prepared, 1);
    assert.deepEqual(versions, [1, 2]);
});
