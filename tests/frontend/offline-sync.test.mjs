/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline-sync.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für die pure Logik der Offline-Sync-Outbox
 * (resources/js/offline-sync.js): Payload-Ableitung, Outbox-Verwaltung,
 * Flush-Routing (applied/duplicate/conflict/rejected), Batching und
 * Foto-Nachreichung. IndexedDB/DOM/fetch werden minimal gefakt — genau
 * die API-Oberfläche, die das Modul nutzt.
 *
 * Lauf: npm run test:frontend  (bzw. node --test tests/frontend/)
 */

import { test, beforeEach } from "node:test";
import assert from "node:assert/strict";

/* ------------------------------------------------------------------ */
/* Fakes: IndexedDB (nur die genutzte Oberfläche)                      */
/* ------------------------------------------------------------------ */

class FakeRequest {
    constructor() {
        this.onsuccess = null;
        this.onerror = null;
        this.result = undefined;
    }

    /** Ergebnis synchron setzen, Callback asynchron feuern (wie IDB). */
    _resolve(result) {
        this.result = result;
        queueMicrotask(() => this.onsuccess && this.onsuccess());
        return this;
    }
}

class FakeObjectStore {
    constructor(table, def) {
        this.table = table; // Map key → value
        this.def = def; // { keyPath, autoIncrement, seq }
    }

    put(value) {
        let key = value[this.def.keyPath];
        if (key === undefined && this.def.autoIncrement) {
            key = ++this.def.seq;
            value = { ...value, [this.def.keyPath]: key };
        }
        this.table.set(key, value);
        return new FakeRequest()._resolve(key);
    }

    get(key) {
        return new FakeRequest()._resolve(this.table.get(key));
    }

    delete(key) {
        this.table.delete(key);
        return new FakeRequest()._resolve(undefined);
    }

    clear() {
        this.table.clear();
        return new FakeRequest()._resolve(undefined);
    }

    getAll() {
        return new FakeRequest()._resolve([...this.table.values()]);
    }

    count() {
        return new FakeRequest()._resolve(this.table.size);
    }

    createIndex() {}

    index(name) {
        return {
            getAll: (value) =>
                new FakeRequest()._resolve(
                    [...this.table.values()].filter((v) => v[name] === value),
                ),
        };
    }
}

class FakeTransaction {
    constructor(db, name) {
        this._db = db;
        this._name = name;
        this.oncomplete = null;
        this.onerror = null;
        this.error = null;
        queueMicrotask(() => this.oncomplete && this.oncomplete());
    }

    objectStore(name) {
        return this._db._store(name);
    }
}

class FakeDB {
    constructor() {
        this.tables = new Map();
        this.defs = new Map();
        this.objectStoreNames = { contains: (n) => this.tables.has(n) };
    }

    createObjectStore(name, opts = {}) {
        this.tables.set(name, new Map());
        this.defs.set(name, { ...opts, seq: 0 });
        return this._store(name);
    }

    _store(name) {
        return new FakeObjectStore(this.tables.get(name), this.defs.get(name));
    }

    transaction(name) {
        return new FakeTransaction(this, name);
    }

    /** Testhelfer: Inhalt eines Stores als Array. */
    rows(name) {
        return [...(this.tables.get(name)?.values() ?? [])];
    }
}

function installIndexedDB(db) {
    Object.defineProperty(globalThis, "indexedDB", {
        configurable: true,
        writable: true,
        value: {
            open() {
                const request = new FakeRequest();
                request.result = db;
                queueMicrotask(() => {
                    if (db.tables.size === 0 && request.onupgradeneeded) {
                        request.onupgradeneeded();
                    }
                    if (request.onsuccess) request.onsuccess();
                });
                return request;
            },
        },
    });
}

/* ------------------------------------------------------------------ */
/* Fakes: DOM / navigator / fetch / FormData                           */
/* ------------------------------------------------------------------ */

function makeBadge() {
    const countEl = { textContent: "" };
    return {
        hidden: true,
        dataset: {},
        countEl,
        querySelector: (sel) =>
            sel === "[data-sync-pending-count]" ? countEl : null,
    };
}

function installDocument({
    syncEndpoint = "https://app.test/sync",
    attachmentEndpoint = "https://app.test/sync-att",
    badge = null,
} = {}) {
    Object.defineProperty(globalThis, "document", {
        configurable: true,
        writable: true,
        value: {
            querySelector(sel) {
                if (sel === 'meta[name="sync-endpoint"]') {
                    return syncEndpoint
                        ? { getAttribute: () => syncEndpoint }
                        : null;
                }
                if (sel === 'meta[name="sync-attachment-endpoint"]') {
                    return attachmentEndpoint
                        ? { getAttribute: () => attachmentEndpoint }
                        : null;
                }
                if (sel === "[data-sync-status]") return badge;
                return null; // csrf-token-Meta u. a.
            },
            addEventListener() {},
        },
    });
}

const fakeNavigator = { onLine: true };
Object.defineProperty(globalThis, "navigator", {
    configurable: true,
    writable: true,
    value: fakeNavigator,
});

globalThis.window = { addEventListener() {}, location: { reload() {} } };

/** FormData-Ersatz: liest Einträge aus dem Fake-Formular (_entries). */
class FakeFormData {
    constructor(form) {
        this._entries = form && form._entries ? [...form._entries] : [];
    }

    append(name, value) {
        this._entries.push([name, value]);
    }

    entries() {
        return this._entries[Symbol.iterator]();
    }
}
globalThis.FormData = FakeFormData;

/** fetch-Stub: Antworten aus einer Warteschlange, Aufrufe protokolliert. */
function installFetch(responses) {
    const calls = [];
    globalThis.fetch = async (url, init) => {
        calls.push({ url, init });
        const next = responses.shift();
        if (next instanceof Error) throw next;
        return next ?? jsonResponse(200, {});
    };
    return calls;
}

function jsonResponse(status, body) {
    return new Response(JSON.stringify(body), {
        status,
        headers: { "Content-Type": "application/json" },
    });
}

/* ------------------------------------------------------------------ */
/* Modul laden (nach den Globals; das Modul hat keine Import-Effekte)  */
/* ------------------------------------------------------------------ */

const { __testables } = await import(
    new URL("../../resources/js/offline-sync.js", import.meta.url).href
);
const {
    buildPayload, flush, updateBadge, uploadPhotos,
    outboxAll, outboxPut, outboxDelete, storeAll, storePut, clearAll,
    courseStore, courseGet, courseDelete,
} = __testables;

/** Frische Umgebung: leere DB, Standard-Dokument, online. */
let db;
beforeEach(() => {
    db = new FakeDB();
    installIndexedDB(db);
    installDocument();
    fakeNavigator.onLine = true;
    globalThis.fetch = async () => jsonResponse(200, {});
});

const command = (uuid, extra = {}) => ({
    client_uuid: uuid,
    type: "attendance.clock-in",
    payload: { started_at: "2026-08-24T10:00:00.000Z" },
    captured_at: "2026-08-24T10:00:00.000Z",
    ...extra,
});

/* ------------------------------------------------------------------ */
/* buildPayload                                                        */
/* ------------------------------------------------------------------ */

test("buildPayload: attendance.clock-in liefert started_at (ISO)", () => {
    const payload = buildPayload("attendance.clock-in", {});
    assert.ok(payload);
    assert.ok(!Number.isNaN(Date.parse(payload.started_at)));
    assert.deepEqual(Object.keys(payload), ["started_at"]);
});

test("buildPayload: attendance.clock-out übernimmt break_minutes als Zahl", () => {
    const form = {
        querySelector: (sel) =>
            sel === '[name="break_minutes"]' ? { value: "15" } : null,
    };
    const payload = buildPayload("attendance.clock-out", form);
    assert.ok(!Number.isNaN(Date.parse(payload.ended_at)));
    assert.equal(payload.break_minutes, 15);
});

test("buildPayload: attendance.clock-out ohne Pausenwert lässt break_minutes weg", () => {
    const form = {
        querySelector: (sel) =>
            sel === '[name="break_minutes"]' ? { value: "" } : null,
    };
    const payload = buildPayload("attendance.clock-out", form);
    assert.equal("break_minutes" in payload, false);
});

test("buildPayload: learning.unit-complete nimmt Einschreibung + Einheit mit", () => {
    const form = {
        dataset: { syncPayloadEnrollment: "enr1", syncPayloadUnit: "unit1" },
        querySelector: () => null,
    };
    const payload = buildPayload("learning.unit-complete", form);
    assert.deepEqual(payload, { enrollment: "enr1", unit: "unit1" });
});

test("buildPayload: comment.diary nimmt Diary-Sqid + Body mit", () => {
    const form = {
        dataset: { syncPayloadDiary: "sq1d" },
        querySelector: (sel) =>
            sel === '[name="body"]' ? { value: "Hallo Welt" } : null,
    };
    assert.deepEqual(buildPayload("comment.diary", form), {
        diary: "sq1d",
        body: "Hallo Welt",
    });
});

test("buildPayload: unbekannter Typ liefert null (kein Abfangen)", () => {
    assert.equal(buildPayload("unbekannt.typ", {}), null);
});

test("buildPayload: form.submission serialisiert values[…] inkl. Mehrfachwerten", () => {
    const fields = {
        form_template_id: "tpl-1",
        subject_kind: "customer",
        subject_id: "42",
    };
    const form = {
        dataset: {},
        querySelector(sel) {
            const m = sel.match(/^\[name="(.+)"\]$/);
            return m && fields[m[1]] !== undefined
                ? { value: fields[m[1]] }
                : null;
        },
        querySelectorAll: () => [],
        _entries: [
            ["values[frei]", "Text"],
            ["values[mehrfach][]", "a"],
            ["values[mehrfach][]", "b"],
            ["fremdfeld", "wird ignoriert"],
            ["values[datei]", { keineZeichenkette: true }], // Files überspringen
        ],
    };
    const payload = buildPayload("form.submission", form);
    assert.equal(payload.template, "tpl-1");
    assert.equal(payload.subject_kind, "customer");
    assert.equal(payload.subject_id, "42");
    assert.deepEqual(payload.values, {
        frei: "Text",
        mehrfach: ["a", "b"],
    });
    assert.equal("pending_files" in payload, false);
});

test("buildPayload: form.submission ohne vollständiges Subjekt lässt es weg", () => {
    const fields = { form_template_id: "tpl-1", subject_kind: "customer" };
    const form = {
        dataset: {},
        querySelector(sel) {
            const m = sel.match(/^\[name="(.+)"\]$/);
            return m && fields[m[1]] !== undefined
                ? { value: fields[m[1]] }
                : null;
        },
        querySelectorAll: () => [],
        _entries: [],
    };
    const payload = buildPayload("form.submission", form);
    assert.equal("subject_kind" in payload, false);
    assert.equal("subject_id" in payload, false);
});

test("buildPayload: form.submission kündigt gefüllte Dateifelder als pending_files an", () => {
    const form = {
        dataset: {},
        querySelector: () => null,
        querySelectorAll: () => [
            { name: "files[foto]", files: [{}] },
            { name: "files[leer]", files: [] }, // leer → nicht ankündigen
            { name: "anderes_feld", files: [{}] }, // falsches Namensschema
        ],
        _entries: [],
    };
    const payload = buildPayload("form.submission", form);
    assert.deepEqual(payload.pending_files, ["foto"]);
});

/* ------------------------------------------------------------------ */
/* Outbox-Verwaltung                                                   */
/* ------------------------------------------------------------------ */

test("Outbox: put/getAll/delete-Roundtrip über die IndexedDB-Naht", async () => {
    await outboxPut(command("a"));
    await outboxPut(command("b"));
    assert.deepEqual(
        (await outboxAll()).map((c) => c.client_uuid),
        ["a", "b"],
    );

    await outboxDelete("a");
    assert.deepEqual(
        (await outboxAll()).map((c) => c.client_uuid),
        ["b"],
    );
});

test("Outbox: put mit gleicher client_uuid überschreibt (idempotent)", async () => {
    await outboxPut(command("a"));
    await outboxPut(command("a", { type: "attendance.clock-out" }));
    const all = await outboxAll();
    assert.equal(all.length, 1);
    assert.equal(all[0].type, "attendance.clock-out");
});

test("clearAll leert Outbox, rejected, conflicts und photos (§3.4 Logout)", async () => {
    await outboxPut(command("a"));
    await storePut("rejected", command("r"));
    await storePut("conflicts", command("k"));
    await storePut("photos", { client_uuid: "a", field: "foto", blob: {} });

    await clearAll();

    assert.equal((await outboxAll()).length, 0);
    assert.equal((await storeAll("rejected")).length, 0);
    assert.equal((await storeAll("conflicts")).length, 0);
    assert.equal(db.rows("photos").length, 0);
});

/* ------------------------------------------------------------------ */
/* flush: Routing der Server-Ergebnisse                                */
/* ------------------------------------------------------------------ */

test("flush: applied/duplicate räumen die Outbox", async () => {
    await outboxPut(command("a"));
    await outboxPut(command("b"));
    const calls = installFetch([
        jsonResponse(200, {
            results: [
                { client_uuid: "a", status: "applied" },
                { client_uuid: "b", status: "duplicate" },
            ],
        }),
    ]);

    await flush();

    assert.equal(calls.length, 1);
    assert.equal(calls[0].url, "https://app.test/sync");
    const body = JSON.parse(calls[0].init.body);
    assert.deepEqual(
        body.commands.map((c) => c.client_uuid),
        ["a", "b"],
    );
    assert.equal((await outboxAll()).length, 0);
});

test("flush: rejected wandert mit Fehlern in den rejected-Store", async () => {
    await outboxPut(command("a"));
    installFetch([
        jsonResponse(200, {
            results: [
                {
                    client_uuid: "a",
                    status: "rejected",
                    errors: { started_at: ["ungültig"] },
                },
            ],
        }),
    ]);

    await flush();

    assert.equal((await outboxAll()).length, 0);
    const rejected = await storeAll("rejected");
    assert.equal(rejected.length, 1);
    assert.equal(rejected[0].client_uuid, "a");
    assert.equal(rejected[0].type, "attendance.clock-in");
    assert.deepEqual(rejected[0].payload, {
        started_at: "2026-08-24T10:00:00.000Z",
    });
    assert.deepEqual(rejected[0].errors, { started_at: ["ungültig"] });
    assert.ok(rejected[0].rejected_at);
});

test("flush: conflict wandert mit Server-Stand in den conflicts-Store (nicht rejected)", async () => {
    await outboxPut(command("a"));
    installFetch([
        jsonResponse(200, {
            results: [
                {
                    client_uuid: "a",
                    status: "conflict",
                    conflict: {
                        server: { started_at: "2026-08-24T09:00:00Z" },
                        current_version: 7,
                    },
                },
            ],
        }),
    ]);

    await flush();

    assert.equal((await outboxAll()).length, 0);
    assert.equal((await storeAll("rejected")).length, 0);
    const conflicts = await storeAll("conflicts");
    assert.equal(conflicts.length, 1);
    assert.deepEqual(conflicts[0].server, {
        started_at: "2026-08-24T09:00:00Z",
    });
    assert.equal(conflicts[0].current_version, 7);
    assert.ok(conflicts[0].detected_at);
});

test("flush: nicht-ok-Antwort (z. B. 419) lässt die Outbox unangetastet", async () => {
    await outboxPut(command("a"));
    const calls = installFetch([jsonResponse(419, {})]);

    await flush();

    assert.equal(calls.length, 1);
    assert.equal((await outboxAll()).length, 1);
});

test("flush: Netzfehler mitten im Flush lässt den Rest in der Outbox", async () => {
    await outboxPut(command("a"));
    installFetch([new TypeError("Failed to fetch")]);

    await flush();

    assert.equal((await outboxAll()).length, 1);
});

test("flush: offline wird gar nicht erst gesendet", async () => {
    fakeNavigator.onLine = false;
    await outboxPut(command("a"));
    const calls = installFetch([]);

    await flush();

    assert.equal(calls.length, 0);
    assert.equal((await outboxAll()).length, 1);
});

test("flush: ohne sync-endpoint-Meta bleibt die Outbox liegen", async () => {
    installDocument({ syncEndpoint: null });
    await outboxPut(command("a"));
    const calls = installFetch([]);

    await flush();

    assert.equal(calls.length, 0);
    assert.equal((await outboxAll()).length, 1);
});

test("flush: sendet Batches von 50, sequentiell", async () => {
    for (let i = 0; i < 60; i++) {
        await outboxPut(command(`cmd-${String(i).padStart(2, "0")}`));
    }
    const results = (from, to) =>
        Array.from({ length: to - from }, (_, i) => ({
            client_uuid: `cmd-${String(from + i).padStart(2, "0")}`,
            status: "applied",
        }));
    const calls = installFetch([
        jsonResponse(200, { results: results(0, 50) }),
        jsonResponse(200, { results: results(50, 60) }),
    ]);

    await flush();

    assert.equal(calls.length, 2);
    assert.equal(JSON.parse(calls[0].init.body).commands.length, 50);
    assert.equal(JSON.parse(calls[1].init.body).commands.length, 10);
    assert.equal((await outboxAll()).length, 0);
});

test("flush: bricht nach fehlgeschlagenem Batch ab (kein zweiter Request)", async () => {
    for (let i = 0; i < 60; i++) {
        await outboxPut(command(`cmd-${String(i).padStart(2, "0")}`));
    }
    const calls = installFetch([jsonResponse(500, {})]);

    await flush();

    assert.equal(calls.length, 1);
    assert.equal((await outboxAll()).length, 60);
});

/* ------------------------------------------------------------------ */
/* uploadPhotos: Nachreich-Semantik (W4.1)                             */
/* ------------------------------------------------------------------ */

test("uploadPhotos: ok und 410 räumen das Foto, 409 lässt es liegen", async () => {
    await storePut("photos", { client_uuid: "a", field: "f1", blob: {} });
    await storePut("photos", { client_uuid: "a", field: "f2", blob: {} });
    await storePut("photos", { client_uuid: "a", field: "f3", blob: {} });
    installFetch([
        jsonResponse(200, {}), // f1 → angewendet
        jsonResponse(409, {}), // f2 → Befehl noch nicht da: behalten
        jsonResponse(410, {}), // f3 → Abgabe weg: verwerfen
    ]);

    await uploadPhotos("a");

    assert.deepEqual(
        db.rows("photos").map((p) => p.field),
        ["f2"],
    );
});

test("uploadPhotos: dauerhafte Ablehnung (413) verwirft statt endlos zu wiederholen", async () => {
    await storePut("photos", { client_uuid: "a", field: "f1", blob: {} });
    installFetch([jsonResponse(413, {})]);

    await uploadPhotos("a");

    assert.equal(db.rows("photos").length, 0);
});

test("uploadPhotos: Netzfehler lässt die restliche Queue stehen", async () => {
    await storePut("photos", { client_uuid: "a", field: "f1", blob: {} });
    await storePut("photos", { client_uuid: "a", field: "f2", blob: {} });
    installFetch([new TypeError("Failed to fetch")]);

    await uploadPhotos("a");

    assert.equal(db.rows("photos").length, 2);
});

/* ------------------------------------------------------------------ */
/* updateBadge                                                         */
/* ------------------------------------------------------------------ */

test("updateBadge: zeigt offene Befehle mit Zähler", async () => {
    const badge = makeBadge();
    installDocument({ badge });
    await outboxPut(command("a"));

    await updateBadge();

    assert.equal(badge.hidden, false);
    assert.equal(badge.countEl.textContent, "1");
    assert.equal(badge.dataset.syncOffline, "0");
    assert.equal(badge.dataset.syncRejected, "0");
    assert.equal(badge.dataset.syncConflicts, "0");
});

test("updateBadge: offline sichtbar auch ohne offene Befehle", async () => {
    const badge = makeBadge();
    installDocument({ badge });
    fakeNavigator.onLine = false;

    await updateBadge();

    assert.equal(badge.hidden, false);
    assert.equal(badge.dataset.syncOffline, "1");
});

test("updateBadge: leer und online → versteckt", async () => {
    const badge = makeBadge();
    installDocument({ badge });

    await updateBadge();

    assert.equal(badge.hidden, true);
});

/* ------------------------------------------------------------------ */
/* Kurse offline lesen (Feature 149, MVP-748)                          */
/* ------------------------------------------------------------------ */

test("courseStore legt das Bündel ab und courseGet liest es zurück", async () => {
    globalThis.fetch = async () =>
        jsonResponse(200, { course: { title: "Brandschutz" }, units: [] });

    await courseStore("enr1", "/meine-schulungen/enr1/offline");
    const stored = await courseGet("enr1");

    assert.equal(stored.enrollment, "enr1");
    assert.equal(stored.course.title, "Brandschutz");
});

test("courseDelete entfernt den Kurs wieder", async () => {
    globalThis.fetch = async () => jsonResponse(200, { units: [] });

    await courseStore("enr1", "/x");
    await courseDelete("enr1");

    assert.equal(await courseGet("enr1"), undefined);
});

test("Abmelden löscht auch die offline gespeicherten Kurse", async () => {
    globalThis.fetch = async () => jsonResponse(200, { units: [] });

    await courseStore("enr1", "/x");
    await clearAll();

    // Der Stoff darf auf einem geteilten Gerät nicht zurückbleiben.
    assert.equal(await courseGet("enr1"), undefined);
});

test("courseStore wirft bei Fehlerantwort und legt nichts ab", async () => {
    globalThis.fetch = async () => jsonResponse(500, {});

    await assert.rejects(() => courseStore("enr1", "/x"));
    assert.equal(await courseGet("enr1"), undefined);
});

