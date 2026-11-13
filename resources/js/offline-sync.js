/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline-sync.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { getJson, postForm, postJson } from "./lib/http.js";

/**
 * Offline-Sync-Outbox (Feature 035, Phase 2 — offline-sync-architektur.md
 * §3.1/§3.5). Formulare mit `data-offline-sync="<typ>"` werden NUR im
 * Offline-Fall abgefangen und als Befehl in einer IndexedDB-Outbox
 * gespeichert; online laufen sie unverändert als normale POSTs. Der
 * Sync-Manager flusht die Outbox beim Online-Event, bei Sichtbarwechsel
 * und beim Laden gegen den idempotenten Batch-Endpunkt
 * `api.internal.sync.commands` (applied | duplicate | conflict | rejected).
 *
 * Bewusst KEIN Service-Worker-Request-Replay (sw.js bleibt ohne
 * fetch-Handler) — die Outbox ist explizit und testbar.
 *
 * HTTP läuft über lib/http.js — mit on419 "ignore": eine abgelaufene
 * Session darf hier NICHT die Seite neu laden, die Outbox bleibt liegen
 * und der nächste Flush versucht es erneut.
 */

/** @typedef {HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement} FormField */

/**
 * Nutzlast eines Befehls, wie buildPayload() sie aus dem Formular ableitet.
 * @typedef {{pending_files?: string[]} & Record<string, unknown>} SyncPayload
 */

/**
 * Eintrag der Stores outbox, rejected und conflicts (IndexedDB). Alt- und
 * Fehlereinträge können Felder auslassen.
 * @typedef {Object} SyncEntry
 * @property {string} client_uuid
 * @property {string} [type]
 * @property {SyncPayload | null} [payload]
 * @property {string} [owner]
 * @property {string | null} [captured_at]
 * @property {Record<string, string[]> | null} [errors]
 * @property {Record<string, unknown> | null} [server]
 * @property {string | null} [current_version]
 * @property {string} [detected_at]
 * @property {string} [rejected_at]
 * @property {boolean} [remote] offener Konflikt vom Server, nicht in diesem Gerät (MVP-1595)
 */

/**
 * Ergebnis je Befehl vom Batch-Endpunkt (SyncCommandService::handle).
 * @typedef {Object} SyncResult
 * @property {string} client_uuid
 * @property {string} status applied | duplicate | conflict | rejected
 * @property {Record<string, string[]> | null} [errors]
 * @property {{server?: Record<string, unknown>, current_version?: string}} [conflict]
 */

/**
 * Foto der Warteschlange (Store photos, `id` vergibt IndexedDB).
 * @typedef {Object} SyncPhoto
 * @property {number} id
 * @property {string} client_uuid
 * @property {string} field
 * @property {string} name
 * @property {Blob} blob
 */

const DB_NAME = "workdiary-sync";
// v2 (Audit 2026-08, W4.1): eigener Konflikt-Store + Foto-Warteschlange.
const DB_VERSION = 4;
const OUTBOX = "outbox";
const REJECTED = "rejected";
const CONFLICTS = "conflicts";
const PHOTOS = "photos";
/* Kursinhalte zum Offline-Lesen (Feature 149, MVP-748). Bewusst ein eigener
   Store: er wird beim Abmelden mitgeleert, und der Seiten-Cache bleibt
   unberuehrt (der Service Worker cacht keine angemeldeten Seiten). */
const COURSES = "courses";
/* Krisenmappe fuer den Offline-Leser (MVP-914): ein Eintrag je Geraet. */
const CRISIS = "crisis";

/** @returns {Promise<IDBDatabase>} */
function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(OUTBOX)) {
                db.createObjectStore(OUTBOX, { keyPath: "client_uuid" });
            }
            if (!db.objectStoreNames.contains(REJECTED)) {
                db.createObjectStore(REJECTED, { keyPath: "client_uuid" });
            }
            // Konflikte liegen bewusst NICHT im rejected-Store: dort bietet die
            // Oberflaeche „Erneut anwenden" an — genau das darf ein Konflikt
            // nicht anbieten, ohne dass der Nutzer den fremden Stand gesehen hat.
            if (!db.objectStoreNames.contains(CONFLICTS)) {
                db.createObjectStore(CONFLICTS, { keyPath: "client_uuid" });
            }
            if (!db.objectStoreNames.contains(COURSES)) {
                db.createObjectStore(COURSES, { keyPath: "enrollment" });
            }
            if (!db.objectStoreNames.contains(CRISIS)) {
                db.createObjectStore(CRISIS, { keyPath: "key" });
            }
            if (!db.objectStoreNames.contains(PHOTOS)) {
                const store = db.createObjectStore(PHOTOS, {
                    keyPath: "id",
                    autoIncrement: true,
                });
                store.createIndex("client_uuid", "client_uuid", {
                    unique: false,
                });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

/**
 * @param {IDBDatabase} db
 * @param {string} store
 * @param {IDBTransactionMode} mode
 * @param {(store: IDBObjectStore) => IDBRequest} fn
 * @returns {Promise<unknown>}
 */
function tx(db, store, mode, fn) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(store, mode);
        const result = fn(transaction.objectStore(store));
        transaction.oncomplete = () => resolve(result?.result ?? result);
        transaction.onerror = () => reject(transaction.error);
    });
}

/** @returns {Promise<SyncEntry[]>} */
async function outboxAll() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const request = db
            .transaction(OUTBOX, "readonly")
            .objectStore(OUTBOX)
            .getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

/** @param {SyncEntry} command */
async function outboxPut(command) {
    const db = await openDb();
    await tx(db, OUTBOX, "readwrite", (store) => store.put(command));
}

/** @param {string} clientUuid */
async function outboxDelete(clientUuid) {
    const db = await openDb();
    await tx(db, OUTBOX, "readwrite", (store) => store.delete(clientUuid));
}

/**
 * @param {string} name
 * @param {object} entry
 */
async function storePut(name, entry) {
    const db = await openDb();
    await tx(db, name, "readwrite", (store) => store.put(entry));
}

/**
 * @param {string} name
 * @returns {Promise<unknown[]>} Inhalt beliebiger Stores, Form je Store.
 */
async function storeAll(name) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const request = db
            .transaction(name, "readonly")
            .objectStore(name)
            .getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

/**
 * @param {string} name
 * @returns {Promise<number>}
 */
async function storeCount(name) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const request = db
            .transaction(name, "readonly")
            .objectStore(name)
            .count();
        request.onsuccess = () => resolve(request.result || 0);
        request.onerror = () => reject(request.error);
    });
}

/**
 * @param {string} name
 * @param {IDBValidKey} key
 */
async function storeDelete(name, key) {
    const db = await openDb();
    await tx(db, name, "readwrite", (store) => store.delete(key));
}

/** @param {SyncEntry} entry */
const rejectedPut = (entry) => storePut(REJECTED, entry);
const rejectedCount = () => storeCount(REJECTED);
/** @param {SyncEntry} entry */
const conflictPut = (entry) => storePut(CONFLICTS, entry);
const conflictCount = () => storeCount(CONFLICTS);
/** @param {string} clientUuid */
const conflictDelete = (clientUuid) => storeDelete(CONFLICTS, clientUuid);

/**
 * Fotos der Warteschlange eines Befehls (Foto-Queue, W4.1).
 * @param {string} clientUuid
 * @returns {Promise<SyncPhoto[]>}
 */
async function photosFor(clientUuid) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const request = db
            .transaction(PHOTOS, "readonly")
            .objectStore(PHOTOS)
            .index("client_uuid")
            .getAll(clientUuid);
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

/** §3.4: Logout leert die Outbox (Gerätewechsel/Abmeldung). */
async function clearAll() {
    const db = await openDb();
    for (const name of [OUTBOX, REJECTED, CONFLICTS, PHOTOS, COURSES, CRISIS]) {
        await tx(db, name, "readwrite", (store) => store.clear());
    }
}

async function updateBadge() {
    const badge = /** @type {HTMLElement} */ (
        document.querySelector("[data-sync-status]")
    );
    if (!badge) return;

    try {
        const pending = (await outboxAll()).length;
        const rejected = await rejectedCount();
        const conflicts = await conflictCount();
        const offline = !navigator.onLine;

        badge.hidden =
            pending === 0 && rejected === 0 && conflicts === 0 && !offline;
        const countEl = badge.querySelector("[data-sync-pending-count]");
        if (countEl) countEl.textContent = String(pending);
        badge.dataset.syncOffline = offline ? "1" : "0";
        badge.dataset.syncRejected = rejected > 0 ? "1" : "0";
        badge.dataset.syncConflicts = conflicts > 0 ? "1" : "0";
    } catch (_) {
        /* IndexedDB nicht verfügbar (privater Modus) — Badge bleibt leer. */
    }
}

/**
 * Foto-Warteschlange nachreichen (Audit 2026-08, W4.1). Bilder passen nicht
 * in den JSON-Batch — sie gehen einzeln als Multipart an
 * `api.internal.sync.attachments`, zugeordnet ueber die client_uuid des
 * bereits angewendeten Befehls.
 *
 * Bei HTTP 409 („noch nicht angewendet") bleibt das Foto in der Queue: der
 * naechste Flush versucht es erneut. Bei 410 („Abgabe weg") wird es verworfen,
 * sonst laege es fuer immer im Speicher des Geraets.
 *
 * @param {string} clientUuid
 */
async function uploadPhotos(clientUuid) {
    let photos;
    try {
        photos = await photosFor(clientUuid);
    } catch (_) {
        return;
    }
    if (photos.length === 0) return;

    const endpoint = document
        .querySelector('meta[name="sync-attachment-endpoint"]')
        ?.getAttribute("content");
    if (!endpoint) return;

    for (const photo of photos) {
        const body = new FormData();
        body.append("client_uuid", clientUuid);
        body.append("field", photo.field);
        body.append("file", photo.blob, photo.name || "foto.jpg");

        let response;
        try {
            response = await postForm(endpoint, body, { on419: "ignore" });
        } catch (_) {
            return; // Netz weg — Rest bleibt in der Queue
        }

        if (response.ok || response.status === 410) {
            await storeDelete(PHOTOS, photo.id);
        } else if (response.status !== 409) {
            // Dauerhafte Ablehnung (zu gross, falscher Typ): nicht endlos
            // wiederholen — der Nutzer sieht die Abgabe ohne Foto.
            await storeDelete(PHOTOS, photo.id);
        }
    }
}

/**
 * Sqid der angemeldeten Person (Sicherheitsaudit 2026-09-17, storage-1). Die
 * Outbox liegt pro Browserprofil, nicht pro Konto — auf einem geteilten Gerät
 * hätten sonst Stempelungen und Kommentare der einen Person unter der nächsten
 * gestanden.
 */
function currentOwner() {
    return (
        document
            .querySelector('meta[name="sync-owner"]')
            ?.getAttribute("content") || ""
    );
}

const OWNER_KEY = "workdiary-sync-owner";

function storedOwner() {
    try {
        return window.localStorage.getItem(OWNER_KEY) || "";
    } catch (_) {
        return "";
    }
}

/** @param {string} owner */
function rememberOwner(owner) {
    try {
        window.localStorage.setItem(OWNER_KEY, owner);
    } catch (_) {
        /* privater Modus: dann greift die Filterung im Flush */
    }
}

/** Kontowechsel auf dem Gerät: alles Lokale verwerfen (wie beim Abmelden, §3.4). */
async function enforceOwner() {
    const owner = currentOwner();
    if (owner === "") return;

    const previous = storedOwner();
    if (previous !== "" && previous !== owner) {
        await clearAll().catch(() => {});
    }
    if (previous !== owner) {
        rememberOwner(owner);
    }
}

let flushing = false;

async function flush() {
    if (flushing || !navigator.onLine) return;

    let commands;
    try {
        commands = await outboxAll();
    } catch (_) {
        return;
    }

    // Befehle eines anderen Kontos werden nie gesendet (storage-1). Ohne
    // Besitzervermerk (Altbestand) gelten sie als eigene — der Kontowechsel
    // leert die Ablage ohnehin.
    const owner = currentOwner();
    commands = commands.filter((c) => !c.owner || !owner || c.owner === owner);

    if (commands.length === 0) {
        updateBadge();
        return;
    }

    flushing = true;
    try {
        const endpoint = document
            .querySelector('meta[name="sync-endpoint"]')
            ?.getAttribute("content");
        if (!endpoint) return;

        // Batches von 50 (Server-Limit), sequentiell.
        for (let i = 0; i < commands.length; i += 50) {
            const batch = commands.slice(i, i + 50);
            const response = await postJson(
                endpoint,
                { commands: batch },
                { on419: "ignore" },
            );

            // Session abgelaufen o. ä.: Outbox behalten, später erneut.
            if (!response.ok) return;

            /** @type {{results?: SyncResult[]}} */
            const data = response.data ?? {};
            for (const result of data.results || []) {
                const original = batch.find(
                    (c) => c.client_uuid === result.client_uuid,
                );

                if (
                    result.status === "applied" ||
                    result.status === "duplicate"
                ) {
                    // Erst die Fotos nachreichen, dann den Befehl raeumen —
                    // andersherum waere die Zuordnung (client_uuid) weg.
                    await uploadPhotos(result.client_uuid);
                    await outboxDelete(result.client_uuid);
                } else if (result.status === "conflict") {
                    // Konflikt: KEIN „Erneut anwenden" ohne Entscheidung —
                    // eigener Store, eigener Abschnitt, zwei Auswege.
                    await conflictPut({
                        client_uuid: result.client_uuid,
                        type: original?.type,
                        payload: original?.payload || null,
                        captured_at: original?.captured_at || null,
                        errors: result.errors || null,
                        server: result.conflict?.server || null,
                        current_version:
                            result.conflict?.current_version || null,
                        detected_at: new Date().toISOString(),
                    });
                    await outboxDelete(result.client_uuid);
                } else {
                    // rejected: aus der Outbox nehmen (kein Endlos-Retry) und
                    // für die Anzeige aufheben (§3.3).
                    await rejectedPut({
                        client_uuid: result.client_uuid,
                        type: original?.type,
                        payload: original?.payload || null,
                        captured_at: original?.captured_at || null,
                        errors: result.errors || null,
                        rejected_at: new Date().toISOString(),
                    });
                    await outboxDelete(result.client_uuid);
                }
            }
        }
    } catch (_) {
        /* Netzfehler mitten im Flush — Rest bleibt in der Outbox. */
    } finally {
        flushing = false;
        updateBadge();
    }
}

/**
 * Benannte Felder wie beim normalen Absenden: FormData lässt gesperrte Felder
 * aus (inaktive Modi der Eingabeleiste), `name[]` wird zur Liste, Leeres fällt weg.
 * @param {HTMLFormElement} form
 * @param {string[]} names
 * @returns {Record<string, string | string[]>}
 */
function formFields(form, names) {
    /** @type {Record<string, string | string[]>} */
    const out = {};
    for (const [name, value] of new FormData(form).entries()) {
        if (typeof value !== "string") continue;
        const list = name.endsWith("[]");
        const key = list ? name.slice(0, -2) : name;
        if (!names.includes(key)) continue;
        if (list) {
            const current = out[key];
            out[key] = Array.isArray(current) ? [...current, value] : [value];
        } else if (value !== "") {
            out[key] = value;
        }
    }
    return out;
}

/**
 * Payload je Befehlstyp aus dem abgefangenen Formular ableiten.
 * @param {string} type
 * @param {HTMLFormElement} form
 */
function buildPayload(type, form) {
    const now = new Date().toISOString();

    if (type === "time.entry") {
        // Eingabeleiste auf „Heute“ (MVP-1593): Dauer oder Von/Bis als
        // Wanduhrzeit, umgerechnet wird beim Abgleich. Der Timer braucht den
        // Server — ohne Zeitangabe wird nicht abgefangen.
        const payload = formFields(form, [
            "project_id", "task_id", "diary_entry_id", "description", "date",
            "minutes", "start_time", "end_time", "break_minutes", "tag_ids", "new_tags",
        ]);
        return payload.minutes || payload.start_time ? payload : null;
    }

    if (type === "material.usage") {
        // Material auf „Heute“ (MVP-1594): Projekt und Tag, der Stundenzettel folgt beim Abgleich;
        // Variante (MVP-1690) und „Negativen Bestand zulassen“ wie online.
        return formFields(form, ["project_id", "date", "material_id", "article_variant_id", "description", "quantity", "unit", "allow_negative"]);
    }

    if (type === "attendance.correct") {
        // Stempelkorrektur (MVP-1595): base_version ist der Stand, den der Dialog gezeigt hat.
        return {
            attendance: form.dataset.syncPayloadAttendance || "",
            base_version: form.dataset.syncPayloadBaseVersion || "",
            ...formFields(form, ["started_at", "ended_at", "break_minutes", "reason"]),
        };
    }

    if (type === "attendance.clock-in") {
        return { started_at: now };
    }

    if (type === "attendance.clock-out") {
        /** @type {{ended_at: string, break_minutes?: number}} */
        const payload = { ended_at: now };
        const breakMinutes = /** @type {FormField | null} */ (form.querySelector('[name="break_minutes"]'));
        if (breakMinutes && breakMinutes.value !== "") {
            payload.break_minutes = Number(breakMinutes.value);
        }
        return payload;
    }

    if (type === "learning.unit-complete") {
        // Prüfungen und Aufgaben tragen dieses Attribut gar nicht erst — und
        // der Server lehnt sie zusätzlich ab. Eine offline erzeugte
        // Prüfungsakte wäre nicht manipulationssicher.
        return {
            enrollment: form.dataset.syncPayloadEnrollment || "",
            unit: form.dataset.syncPayloadUnit || "",
        };
    }

    if (type === "inventory.count") {
        /** @type {{count: string, code: string, qty: string, unit?: string}} */
        const payload = {
            count: form.dataset.syncPayloadCount || "",
            code: /** @type {FormField | null} */ (form.querySelector('[name="code"]'))?.value || "",
            qty: /** @type {FormField | null} */ (form.querySelector('[name="qty"]'))?.value || "1",
        };
        // Erfasste Einheit wie online (MVP-1584); leer = Basiseinheit.
        const unit = /** @type {FormField | null} */ (form.querySelector('[name="unit"]'))?.value;
        if (unit) payload.unit = unit;
        return payload;
    }

    if (type === "comment.diary") {
        return {
            diary: form.dataset.syncPayloadDiary || "",
            body: /** @type {FormField | null} */ (form.querySelector('[name="body"]'))?.value || "",
        };
    }

    if (type === "tour.stop-arrive" || type === "tour.stop-depart") {
        // Fahreransicht (MVP-1183): Stopp-Ist mit dem Zeitpunkt der Erfassung auf dem Gerät.
        /** @type {{stop: string, at: string, outcome?: string}} */
        const payload = { stop: form.dataset.syncPayloadStop || "", at: now };
        const outcome = /** @type {FormField | null} */ (form.querySelector('[name="outcome"]'))?.value;
        if (outcome) payload.outcome = outcome;
        return payload;
    }

    if (type === "patrol.scan") {
        // Kontrollpunkt (MVP-1291): gewertet wird die Scanzeit auf dem Gerät.
        return {
            run: form.dataset.syncPayloadRun || "",
            token: /** @type {FormField | null} */ (form.querySelector('[name="token"]'))?.value || "",
            at: now,
        };
    }

    if (type === "takeoff.line") {
        // Aufmaßzeile (MVP-1059): gerechnet wird beim Abgleich auf dem Server,
        // das Foto wandert als angekündigte Datei in die Warteschlange.
        /** @param {string} name */
        const field = (name) => /** @type {FormField | null} */ (form.querySelector(`[name="${name}"]`))?.value || "";
        /** @type {{takeoff: string, formula: string, values: string[], factor: string, label: string, pending_files?: string[]}} */
        const payload = {
            takeoff: form.dataset.syncPayloadTakeoff || "",
            formula: field("formula"),
            values: [.../** @type {NodeListOf<FormField>} */ (form.querySelectorAll('[name^="values["]'))].map((input) => input.value),
            factor: field("factor"),
            label: field("label"),
        };
        const photo = /** @type {HTMLInputElement | null} */ (form.querySelector('input[type="file"][name="files[photo]"]'));
        if (photo?.files?.length) payload.pending_files = ["photo"];
        return payload;
    }

    if (type === "form.submission") {
        // Werte exakt wie der normale Submit serialisieren (FormData respektiert
        // checked-Zustände); Dateien/Unterschriften bleiben dem Online-Weg
        // vorbehalten (Konzept §5) und werden übersprungen.
        /** @type {{template: string, values: Record<string, string | string[]>, notes?: Record<string, string>, draft?: string, subject_kind?: string, subject_id?: string, pending_files?: string[]}} */
        const payload = {
            template:
                /** @type {FormField | null} */ (form.querySelector('[name="form_template_id"]'))?.value || "",
            values: {},
        };
        // Entwurf aus dem Speichern je Feld (Feature 203): beim Abgleich wird er abgesendet statt verdoppelt.
        const draft = /** @type {FormField | null} */ (form.querySelector('[name="draft"]'))?.value;
        if (draft) payload.draft = draft;
        const kind = /** @type {FormField | null} */ (form.querySelector('[name="subject_kind"]'))?.value;
        const subjectId = /** @type {FormField | null} */ (form.querySelector('[name="subject_id"]'))?.value;
        if (kind && subjectId) {
            payload.subject_kind = kind;
            payload.subject_id = subjectId;
        }
        // Foto-/Dateifelder ankuendigen: die Abgabe entsteht sofort mit
        // Nachreich-Marker, der Inhalt folgt nach dem Reconnect (W4.1).
        /** @type {string[]} */
        const pending = [];
        for (const input of /** @type {NodeListOf<HTMLInputElement>} */ (form.querySelectorAll('input[type="file"]'))) {
            const match = (input.name || "").match(/^files\[([^\]]+)\]$/);
            if (match && input.files && input.files.length > 0) {
                pending.push(match[1]);
            }
        }
        if (pending.length > 0) payload.pending_files = pending;

        for (const [name, value] of new FormData(form).entries()) {
            if (typeof value !== "string") continue;
            const note = name.match(/^notes\[([^\]]+)\]$/);
            if (note) {
                (payload.notes ||= {})[note[1]] = value;
                continue;
            }
            const match = name.match(/^values\[([^\]]+)\](\[\])?$/);
            if (!match) continue;
            if (match[2]) {
                /** @type {string[]} */ (payload.values[match[1]] ||= []).push(value);
            } else {
                payload.values[match[1]] = value;
            }
        }
        return payload;
    }

    return null;
}

/**
 * Dateiinhalte der angekuendigten Felder als Blob in IndexedDB parken
 * (Foto-Queue, W4.1). Ein `File` IST ein Blob — der strukturierte Klon der
 * IndexedDB speichert ihn direkt, ohne Base64-Umweg (das Dreifache an
 * Speicher und ein spuerbarer Kodierschritt auf dem Telefon).
 *
 * @param {string} clientUuid
 * @param {HTMLFormElement} form
 * @param {SyncPayload} payload
 */
async function queuePhotos(clientUuid, form, payload) {
    const keys = payload?.pending_files;
    if (!Array.isArray(keys) || keys.length === 0) return;

    for (const key of keys) {
        const input = /** @type {HTMLInputElement | null} */ (form.querySelector(
            `input[type="file"][name="files[${key}]"]`,
        ));
        const file = input?.files?.[0];
        if (!file) continue;
        await storePut(PHOTOS, {
            client_uuid: clientUuid,
            field: key,
            name: file.name,
            blob: file,
        });
    }
}

function bindForms() {
    document.addEventListener(
        "submit",
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            const type = form.dataset.offlineSync;
            if (!type || navigator.onLine) return;

            const payload = buildPayload(type, form);
            if (payload === null) return;

            event.preventDefault();

            const clientUuid = crypto.randomUUID();
            outboxPut({
                client_uuid: clientUuid,
                type,
                payload,
                owner: currentOwner(),
                captured_at: new Date().toISOString(),
            })
                .then(() => queuePhotos(clientUuid, form, payload))
                .then(() => {
                    form.reset();
                    // Dialogformulare (Korrektur, Material) schließen wie nach dem Speichern.
                    form.closest("dialog")?.close();
                    // Fortlaufende Erfassung (Inventur-Scan): gleich weiter scannen.
                    /** @type {HTMLElement | null} */ (form.querySelector("[autofocus]"))?.focus();
                    updateBadge();
                })
                .catch(() => {
                    // IndexedDB nicht verfügbar: normalen Submit nachholen,
                    // der Browser zeigt dann seine Offline-Fehlerseite.
                    form.submit();
                });
        },
        true,
    );

    // §3.4: Abmelden leert die Gerätedaten.
    document.addEventListener("submit", (event) => {
        const form = event.target;
        if (form instanceof HTMLFormElement && isLogoutForm(form)) {
            clearAll().catch(() => {});
        }
    });
}

// getAttribute statt form.action: ein Feld name="action" überdeckt die
// Property (Inventar-Scan, Datenschutz-Fristen, …) und ließ das werfen.
/** @param {HTMLFormElement} form */
function isLogoutForm(form) {
    return (form.getAttribute("action") || "").includes("/logout");
}

/**
 * Offline-Änderungs-Seite (Phase 3, MVP-367): rendert Outbox, Konflikte und
 * abgelehnte Befehle aus IndexedDB; Texte kommen als data-Attribute/Template
 * aus Blade (übersetzt, CSP-konform). Aktionen: „Erneut anwenden" (neue
 * client_uuid → Outbox) und „Verwerfen"; Konflikte siehe resolveConflict().
 */
const rejectedAll = () => /** @type {Promise<SyncEntry[]>} */ (storeAll(REJECTED));
/** @param {string} clientUuid */
const rejectedDelete = (clientUuid) => storeDelete(REJECTED, clientUuid);
const conflictAll = () => /** @type {Promise<SyncEntry[]>} */ (storeAll(CONFLICTS));

/**
 * Konflikt entscheiden (MVP-1595): erst meldet das Gerät die Entscheidung an
 * den Server (`sync.conflict.resolved` in der Audit-Kette), dann räumt es den
 * Konflikt. 404 heißt, dort gibt es ihn nicht (mehr) — geräumt wird trotzdem.
 * Ohne Verbindung bleibt alles, wie es ist.
 *
 * @param {string} url
 * @param {string} clientUuid
 * @param {"server" | "discarded" | "resent"} resolution
 * @param {{resent_client_uuid?: string, edited?: boolean}} [extra]
 * @returns {Promise<boolean>}
 */
async function resolveConflict(url, clientUuid, resolution, extra = {}) {
    let response;
    try {
        response = await postJson(url, { client_uuid: clientUuid, resolution, ...extra }, { on419: "ignore" });
    } catch (_) {
        return false;
    }
    if (!response.ok && response.status !== 404) return false;

    await conflictDelete(clientUuid);
    updateBadge();
    return true;
}

/**
 * Konflikt mit eigener Fassung erneut senden: zuerst in die Outbox, dann die
 * Entscheidung melden — scheitert die Meldung, fliegt der neue Befehl wieder
 * heraus. So geht weder der Konflikt noch die Erfassung verloren.
 *
 * @param {string} url
 * @param {SyncEntry} item
 * @param {SyncPayload} payload
 * @param {boolean} edited
 * @returns {Promise<boolean>}
 */
async function resendConflict(url, item, payload, edited) {
    const clientUuid = crypto.randomUUID();
    await outboxPut({
        client_uuid: clientUuid,
        type: item.type,
        payload,
        owner: currentOwner(),
        captured_at: item.captured_at || new Date().toISOString(),
    });
    if (!(await resolveConflict(url, item.client_uuid, "resent", { resent_client_uuid: clientUuid, edited }))) {
        await outboxDelete(clientUuid);
        return false;
    }
    await flush();
    return true;
}

/** Eingabetyp je bearbeitbarem Feld; alles andere ist Text. */
/** @type {Record<string, string>} */
const EDIT_TYPES = {
    date: "date",
    start_time: "time",
    end_time: "time",
    started_at: "datetime-local",
    ended_at: "datetime-local",
    minutes: "number",
    break_minutes: "number",
    quantity: "number",
};

/** @param {number} n */
const pad2 = (n) => String(n).padStart(2, "0");

/**
 * Wert für das Bearbeiten-Feld: Zeitstempel mit Zonenangabe (ältere Geräte)
 * werden zur Wanduhrzeit des Geräts, wie sie das Eingabefeld erwartet.
 * @param {string} key
 * @param {unknown} value
 */
function editorValue(key, value) {
    if (value === undefined || value === null) return "";
    const text = String(value);
    if (EDIT_TYPES[key] === "datetime-local" && /(?:[zZ]|[+-]\d{2}:?\d{2})$/.test(text)) {
        const d = new Date(text);
        if (!Number.isNaN(d.getTime())) {
            return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
        }
    }
    return text;
}

/**
 * Server-Stand lesbar machen: ISO-Zeitstempel in Ortszeit.
 * @param {unknown} value
 */
function displayValue(value) {
    if (value === null || value === undefined || value === "") return "—";
    const text = String(value);
    if (/^\d{4}-\d{2}-\d{2}T/.test(text)) {
        const d = new Date(text);
        if (!Number.isNaN(d.getTime())) return d.toLocaleString(document.documentElement?.lang || undefined);
    }
    return text;
}

/** Schlüssel des Serverstands, die nur das Skript liest (Feld des Lagerfehlers, Fehlbestand). */
const SERVER_STATE_INTERNAL = new Set(["field", "shortage"]);

/**
 * Serverstand für den Hinweis: „Feld: Wert, …“ ohne interne Schlüssel.
 * @param {Record<string, unknown>} server
 * @param {Record<string, string>} fieldLabels
 */
function serverStateText(server, fieldLabels) {
    return Object.entries(server)
        .filter(([key]) => !SERVER_STATE_INTERNAL.has(key))
        .map(([key, value]) => `${fieldLabels[key] || key}: ${displayValue(value)}`)
        .join(", ");
}

/**
 * Fehlt einem Materialverbrauch nur Bestand (MVP-1691), löst „Negativen
 * Bestand zulassen“ den Konflikt — angeboten nur mit Recht `inventory.negative`
 * und nur für Konflikte, deren Fassung auf diesem Gerät liegt. Recht und
 * Vier-Augen prüft der Server beim erneuten Senden wie online.
 * @param {SyncEntry} item
 * @param {boolean} mayGoNegative
 */
function offersNegativeStock(item, mayGoNegative) {
    return mayGoNegative && item.type === "material.usage" && !item.remote && !!item.payload && item.server?.shortage === true;
}

/**
 * Haken „Negativen Bestand zulassen“ im Bearbeiten-Formular des Konflikts.
 * @param {string} label
 * @param {string} hint
 */
function negativeStockField(label, hint) {
    const wrap = document.createElement("label");
    wrap.className = "fieldset sm:col-span-2";
    const row = document.createElement("span");
    row.className = "flex items-center gap-2";
    const input = document.createElement("input");
    input.type = "checkbox";
    input.className = "checkbox checkbox-sm";
    input.dataset.editorAllowNegative = "1";
    const caption = document.createElement("span");
    caption.className = "fieldset-label";
    caption.textContent = label;
    row.append(input, caption);
    wrap.append(row);
    if (hint) {
        const note = document.createElement("span");
        note.className = "text-xs text-muted";
        note.textContent = hint;
        wrap.append(note);
    }
    return wrap;
}

/**
 * @param {string} key
 * @param {unknown} value
 * @param {string} label
 */
function editorField(key, value, label) {
    const wrap = document.createElement("label");
    wrap.className = "fieldset";
    const caption = document.createElement("span");
    caption.className = "fieldset-label";
    caption.textContent = label;

    const multi = key === "reason" || key === "description";
    /** @type {HTMLInputElement | HTMLTextAreaElement} */
    let input;
    if (multi) {
        input = document.createElement("textarea");
        input.className = "textarea textarea-bordered textarea-sm w-full";
        input.rows = 2;
    } else {
        input = document.createElement("input");
        input.type = EDIT_TYPES[key] || "text";
        if (input.type === "number") input.step = "any";
        input.className = "input input-bordered input-sm w-full";
    }
    input.dataset.editorKey = key;
    input.value = editorValue(key, value);
    wrap.append(caption, input);
    return wrap;
}

/**
 * JSON aus einem data-Attribut der Seite.
 * @param {HTMLElement} root
 * @param {string} name
 * @returns {unknown}
 */
function dataJson(root, name) {
    try {
        return JSON.parse(root.dataset[name] || "null");
    } catch (_) {
        return null;
    }
}

/**
 * Auf dieser Seite schon entschiedene Konflikte — die Serverliste ist der Stand beim Laden.
 * @type {Set<string>}
 */
const resolvedHere = new Set();

/** @param {HTMLElement} root */
async function renderChangesPage(root) {
    const template = /** @type {HTMLTemplateElement} */ (
        document.querySelector("[data-sync-item-template]")
    );
    if (!template) return;

    const typeLabels = /** @type {Record<string, string>} */ (dataJson(root, "typeLabels") || {});
    const fieldLabels = /** @type {Record<string, string>} */ (dataJson(root, "fieldLabels") || {});
    const editFields = /** @type {Record<string, string[]>} */ (dataJson(root, "editFields") || {});
    const mayGoNegative = root.dataset.allowNegative === "1";
    const resolveUrl = root.dataset.resolveUrl || "";
    const failed = () => {
        if (typeof window.notifyAction === "function") {
            window.notifyAction({ tone: "error", message: root.dataset.labelResolveFailed || "" });
        }
    };

    // Offene Konflikte vom Server, die nicht (mehr) auf diesem Gerät liegen —
    // anderes Gerät oder geleerter Speicher: hier nur noch verwerfbar.
    const local = await conflictAll();
    const onDevice = new Set(local.map((c) => c.client_uuid));
    const remote = /** @type {SyncEntry[]} */ (dataJson(root, "serverConflicts") || [])
        .filter((c) => c && c.client_uuid && !onDevice.has(c.client_uuid) && !resolvedHere.has(c.client_uuid))
        .map((c) => ({ ...c, remote: true }));

    /** @type {Record<string, {items: SyncEntry[], label: string | undefined, retry: boolean, conflict?: boolean}>} */
    const sections = {
        outbox: {
            items: await outboxAll(),
            label: root.dataset.labelPending,
            retry: false,
        },
        // Konflikte stehen VOR den Ablehnungen: sie sind das Einzige auf der
        // Seite, das ohne Entscheidung des Nutzers nicht weitergeht.
        conflicts: {
            items: [...local, ...remote],
            label: root.dataset.labelConflict,
            retry: false,
            conflict: true,
        },
        rejected: {
            items: await rejectedAll(),
            label: root.dataset.labelRejected,
            retry: true,
        },
    };

    let total = 0;
    for (const [name, section] of Object.entries(sections)) {
        const el = /** @type {HTMLElement | null} */ (root.querySelector(`[data-offline-section="${name}"]`));
        if (!el) continue;
        /** @type {Element} */ (el.querySelector("[data-section-heading]")).textContent =
            section.label || name;
        const list = /** @type {Element} */ (el.querySelector("[data-section-list]"));
        list.textContent = "";
        el.hidden = section.items.length === 0;
        total += section.items.length;

        for (const item of section.items) {
            const node = /** @type {HTMLElement} */ (
                /** @type {Element} */ (template.content.firstElementChild).cloneNode(true)
            );
            /** @type {Element} */ (node.querySelector("[data-item-type]")).textContent =
                typeLabels[item.type || ""] || item.type || "";
            /** @type {Element} */ (node.querySelector("[data-item-time]")).textContent =
                displayValue(item.captured_at || item.rejected_at || "");

            const errorsEl = /** @type {HTMLElement} */ (
                node.querySelector("[data-item-errors]")
            );
            if (item.errors) {
                errorsEl.textContent = Object.values(item.errors)
                    .flat()
                    .join(" ");
                errorsEl.hidden = false;
            }

            const retryBtn = /** @type {HTMLElement} */ (
                node.querySelector("[data-item-retry]")
            );
            if (section.retry && item.payload) {
                retryBtn.hidden = false;
                retryBtn.addEventListener("click", async () => {
                    await outboxPut({
                        client_uuid: crypto.randomUUID(),
                        type: item.type,
                        payload: item.payload,
                        owner: currentOwner(),
                        captured_at: new Date().toISOString(),
                    });
                    await rejectedDelete(item.client_uuid);
                    await flush();
                    renderChangesPage(root);
                });
            }

            const discardBtn = /** @type {HTMLElement} */ (
                node.querySelector("[data-item-discard]")
            );

            if (section.conflict) {
                const hint = /** @type {HTMLElement} */ (
                    node.querySelector("[data-item-server]")
                );
                if (hint && item.server) {
                    hint.textContent = (
                        root.dataset.labelConflictHint || ":server"
                    ).replace(":server", serverStateText(item.server, fieldLabels));
                    hint.hidden = false;
                }

                if (item.remote) {
                    const remoteHint = /** @type {HTMLElement | null} */ (node.querySelector("[data-item-remote]"));
                    if (remoteHint) {
                        remoteHint.textContent = root.dataset.labelRemoteHint || "";
                        remoteHint.hidden = false;
                    }
                }

                // „Meine Fassung senden": neu in die Outbox, aber mit dem
                // INZWISCHEN gueltigen Stand als base_version — sonst liefe
                // der Befehl sofort in denselben Konflikt.
                const forceBtn = /** @type {HTMLElement} */ (
                    node.querySelector("[data-item-force]")
                );
                if (forceBtn && item.payload && item.current_version && !item.remote) {
                    forceBtn.hidden = false;
                    forceBtn.addEventListener("click", async () => {
                        const payload = { ...item.payload, base_version: item.current_version };
                        if (!(await resendConflict(resolveUrl, item, payload, false))) failed();
                        renderChangesPage(root);
                    });
                }

                // Bearbeiten und erneut senden: die Felder des Typs, vorbelegt mit der eigenen Fassung.
                const keys = editFields[item.type || ""] || [];
                const editBtn = /** @type {HTMLElement | null} */ (node.querySelector("[data-item-edit]"));
                const editor = /** @type {HTMLFormElement | null} */ (node.querySelector("[data-item-editor]"));
                const fieldsBox = editor?.querySelector("[data-editor-fields]");
                if (editBtn && editor && fieldsBox && item.payload && keys.length > 0 && !item.remote) {
                    const own = item.payload;
                    editBtn.hidden = false;
                    editBtn.addEventListener("click", () => {
                        fieldsBox.textContent = "";
                        for (const key of keys) {
                            fieldsBox.appendChild(editorField(key, own[key], fieldLabels[key] || key));
                        }
                        if (offersNegativeStock(item, mayGoNegative)) {
                            fieldsBox.appendChild(negativeStockField(root.dataset.labelAllowNegative || "", root.dataset.labelAllowNegativeHint || ""));
                        }
                        editor.hidden = false;
                    });
                    editor.querySelector("[data-editor-cancel]")?.addEventListener("click", () => {
                        editor.hidden = true;
                    });
                    editor.addEventListener("submit", async (event) => {
                        event.preventDefault();
                        /** @type {SyncPayload} */
                        const payload = { ...own };
                        for (const input of /** @type {NodeListOf<HTMLInputElement | HTMLTextAreaElement>} */ (editor.querySelectorAll("[data-editor-key]"))) {
                            const key = input.dataset.editorKey || "";
                            const value = input.value.trim();
                            if (value === "") delete payload[key];
                            else payload[key] = value;
                        }
                        const negative = /** @type {HTMLInputElement | null} */ (editor.querySelector("[data-editor-allow-negative]"));
                        if (negative) {
                            if (negative.checked) payload.allow_negative = "1";
                            else delete payload.allow_negative;
                        }
                        if (item.current_version) payload.base_version = item.current_version;
                        if (!(await resendConflict(resolveUrl, item, payload, true))) failed();
                        renderChangesPage(root);
                    });
                }

                // Mit Server-Stand (geänderte Stempelung) heißt Verwerfen: den
                // fremden Stand übernehmen. Der Knopf sagt das auch.
                if (item.current_version && root.dataset.labelTakeServer) {
                    discardBtn.textContent = root.dataset.labelTakeServer;
                    discardBtn.classList.remove("text-error");
                }
            }

            discardBtn.addEventListener("click", async () => {
                if (name === "conflicts") {
                    const resolution = item.current_version ? "server" : "discarded";
                    if (!(await resolveConflict(resolveUrl, item.client_uuid, resolution))) {
                        failed();
                        return;
                    }
                    resolvedHere.add(item.client_uuid);
                } else if (name === "outbox") {
                    await outboxDelete(item.client_uuid);
                } else {
                    await rejectedDelete(item.client_uuid);
                }
                updateBadge();
                renderChangesPage(root);
            });

            list.appendChild(node);
        }
    }

    const empty = /** @type {HTMLElement | null} */ (root.querySelector("[data-offline-empty]"));
    if (empty) empty.hidden = total > 0;
}

/* ── Kurse offline lesen (Feature 149, MVP-748) ───────────────────────── */

/**
 * Kursinhalt fuer das Offline-Lesen ablegen.
 *
 * Nur auf ausdrueckliche Anforderung: der Stoff landet im Geraetespeicher,
 * und das soll niemand unbemerkt tun. Geloescht wird er beim Abmelden
 * (clearAll) — deshalb liegt er in einem eigenen Store und nicht im
 * Seiten-Cache.
 *
 * @param {string} enrollment
 * @param {string} url
 */
async function courseStore(enrollment, url) {
    // Ueber die HTTP-Naht, nicht ueber rohes fetch: dort haengen CSRF-Token,
    // credentials und die 419-Behandlung. Ein abgelaufenes Login endete sonst
    // als stiller Fehler, und der Kurs waere „gespeichert" ohne Inhalt.
    const result = /** @type {import("./lib/http.js").JsonResult<Record<string, unknown>>} */ (await getJson(url));

    if (!result.ok || !result.data) throw new Error("offline-bundle");

    const bundle = result.data;
    const db = await openDb();

    await tx(db, COURSES, "readwrite", (store) =>
        store.put({ ...bundle, enrollment }),
    );

    return bundle;
}

/**
 * @param {string} enrollment
 * @returns {Promise<unknown>} Gespeichertes Kursbündel oder undefined.
 */
async function courseGet(enrollment) {
    const db = await openDb();

    // Bewusst NICHT ueber tx(): dessen `result?.result ?? result` liefert bei
    // einem Treffer-losen get() das Request-Objekt statt undefined — genau
    // der Fall „Kurs nicht gespeichert".
    return new Promise((resolve, reject) => {
        const request = db
            .transaction(COURSES, "readonly")
            .objectStore(COURSES)
            .get(enrollment);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

/** @param {string} enrollment */
async function courseDelete(enrollment) {
    const db = await openDb();
    await tx(db, COURSES, "readwrite", (store) => store.delete(enrollment));
}

/** Knopf „Fuer offline speichern" an der Kursseite. */
function bindOfflineCourses() {
    for (const node of document.querySelectorAll("[data-offline-course]")) {
        if (!(node instanceof HTMLElement)) continue;

        const button = node;
        const enrollment = button.dataset.offlineCourse;
        const url = button.dataset.offlineCourseUrl;

        if (!enrollment || !url) continue;

        courseGet(enrollment)
            .then((stored) => {
                if (stored) button.dataset.offlineStored = "1";
            })
            .catch(() => {});

        button.addEventListener("click", async (event) => {
            event.preventDefault();

            try {
                if (button.dataset.offlineStored === "1") {
                    await courseDelete(enrollment);
                    delete button.dataset.offlineStored;
                } else {
                    await courseStore(enrollment, url);
                    button.dataset.offlineStored = "1";
                }
            } catch {
                // Ohne Netz laesst sich nichts herunterladen; der Zustand
                // bleibt, wie er war.
                button.dataset.offlineFailed = "1";
            }
        });
    }
}

/* ── Offline-Krisenmappe (Feature 070, MVP-914) ───────────────────────── */

/** @param {string} url */
async function crisisStore(url) {
    const result = /** @type {import("./lib/http.js").JsonResult<Record<string, unknown>>} */ (await getJson(url));
    if (!result.ok || !result.data) throw new Error("crisis-bundle");
    const db = await openDb();
    await tx(db, CRISIS, "readwrite", (store) =>
        store.put({ ...result.data, key: "bundle" }),
    );
}

/** @returns {Promise<boolean>} */
async function crisisStored() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const request = db.transaction(CRISIS, "readonly").objectStore(CRISIS).get("bundle");
        request.onsuccess = () => resolve(Boolean(request.result));
        request.onerror = () => reject(request.error);
    });
}

/** Knopf „Krisenmappe offline speichern“; einmal gespeichert, frischt jeder Besuch sie auf. */
function bindOfflineCrisis() {
    for (const button of document.querySelectorAll("[data-offline-crisis]")) {
        if (!(button instanceof HTMLElement)) continue;
        const url = button.dataset.offlineCrisis;
        if (!url) continue;

        crisisStored()
            .then((stored) => {
                if (!stored) return;
                button.dataset.offlineStored = "1";
                return crisisStore(url);
            })
            .catch(() => {});

        button.addEventListener("click", async (event) => {
            event.preventDefault();
            try {
                await crisisStore(url);
                button.dataset.offlineStored = "1";
                delete button.dataset.offlineFailed;
            } catch {
                button.dataset.offlineFailed = "1";
            }
        });
    }
}

export function initOfflineSync() {
    if (typeof indexedDB === "undefined") return;

    bindForms();
    bindOfflineCourses();
    bindOfflineCrisis();
    window.addEventListener("online", flush);
    window.addEventListener("offline", updateBadge);
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") flush();
    });

    // Erst der Kontoabgleich, dann der Flush (storage-1).
    enforceOwner()
        .catch(() => {})
        .then(flush);

    const changesRoot = /** @type {HTMLElement | null} */ (document.querySelector("[data-offline-changes]"));
    if (changesRoot) renderChangesPage(changesRoot);
}

// Nur für Unit-Tests (tests/frontend/offline-sync.test.mjs, node:test):
// pure Outbox-/Flush-/Payload-Logik ohne UI-Bindung. Kein App-Code
// importiert dieses Objekt.
export const __testables = {
    buildPayload,
    formFields,
    serverStateText,
    offersNegativeStock,
    resolveConflict,
    resendConflict,
    editorValue,
    isLogoutForm,
    courseStore,
    courseGet,
    courseDelete,
    flush,
    updateBadge,
    uploadPhotos,
    outboxAll,
    outboxPut,
    outboxDelete,
    storeAll,
    storePut,
    clearAll,
    currentOwner,
    enforceOwner,
};
