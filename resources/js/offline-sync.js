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

const DB_NAME = "workdiary-sync";
// v2 (Audit 2026-08, W4.1): eigener Konflikt-Store + Foto-Warteschlange.
const DB_VERSION = 3;
const OUTBOX = "outbox";
const REJECTED = "rejected";
const CONFLICTS = "conflicts";
const PHOTOS = "photos";
/* Kursinhalte zum Offline-Lesen (Feature 149, MVP-748). Bewusst ein eigener
   Store: er wird beim Abmelden mitgeleert, und der Seiten-Cache bleibt
   unberuehrt (der Service Worker cacht keine angemeldeten Seiten). */
const COURSES = "courses";

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

function tx(db, store, mode, fn) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(store, mode);
        const result = fn(transaction.objectStore(store));
        transaction.oncomplete = () => resolve(result?.result ?? result);
        transaction.onerror = () => reject(transaction.error);
    });
}

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

async function outboxPut(command) {
    const db = await openDb();
    await tx(db, OUTBOX, "readwrite", (store) => store.put(command));
}

async function outboxDelete(clientUuid) {
    const db = await openDb();
    await tx(db, OUTBOX, "readwrite", (store) => store.delete(clientUuid));
}

async function storePut(name, entry) {
    const db = await openDb();
    await tx(db, name, "readwrite", (store) => store.put(entry));
}

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

async function storeDelete(name, key) {
    const db = await openDb();
    await tx(db, name, "readwrite", (store) => store.delete(key));
}

const rejectedPut = (entry) => storePut(REJECTED, entry);
const rejectedCount = () => storeCount(REJECTED);
const conflictPut = (entry) => storePut(CONFLICTS, entry);
const conflictCount = () => storeCount(CONFLICTS);
const conflictDelete = (clientUuid) => storeDelete(CONFLICTS, clientUuid);

/** Fotos der Warteschlange eines Befehls (Foto-Queue, W4.1). */
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
    for (const name of [OUTBOX, REJECTED, CONFLICTS, PHOTOS, COURSES]) {
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

let flushing = false;

async function flush() {
    if (flushing || !navigator.onLine) return;

    let commands;
    try {
        commands = await outboxAll();
    } catch (_) {
        return;
    }
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

/** Payload je Befehlstyp aus dem abgefangenen Formular ableiten. */
function buildPayload(type, form) {
    const now = new Date().toISOString();

    if (type === "attendance.clock-in") {
        return { started_at: now };
    }

    if (type === "attendance.clock-out") {
        const payload = { ended_at: now };
        const breakMinutes = form.querySelector('[name="break_minutes"]');
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

    if (type === "comment.diary") {
        return {
            diary: form.dataset.syncPayloadDiary || "",
            body: form.querySelector('[name="body"]')?.value || "",
        };
    }

    if (type === "form.submission") {
        // Werte exakt wie der normale Submit serialisieren (FormData respektiert
        // checked-Zustände); Dateien/Unterschriften bleiben dem Online-Weg
        // vorbehalten (Konzept §5) und werden übersprungen.
        const payload = {
            template:
                form.querySelector('[name="form_template_id"]')?.value || "",
            values: {},
        };
        const kind = form.querySelector('[name="subject_kind"]')?.value;
        const subjectId = form.querySelector('[name="subject_id"]')?.value;
        if (kind && subjectId) {
            payload.subject_kind = kind;
            payload.subject_id = subjectId;
        }
        // Foto-/Dateifelder ankuendigen: die Abgabe entsteht sofort mit
        // Nachreich-Marker, der Inhalt folgt nach dem Reconnect (W4.1).
        const pending = [];
        for (const input of form.querySelectorAll('input[type="file"]')) {
            const match = (input.name || "").match(/^files\[([^\]]+)\]$/);
            if (match && input.files && input.files.length > 0) {
                pending.push(match[1]);
            }
        }
        if (pending.length > 0) payload.pending_files = pending;

        for (const [name, value] of new FormData(form).entries()) {
            if (typeof value !== "string") continue;
            const match = name.match(/^values\[([^\]]+)\](\[\])?$/);
            if (!match) continue;
            if (match[2]) {
                (payload.values[match[1]] ||= []).push(value);
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
 */
async function queuePhotos(clientUuid, form, payload) {
    const keys = payload?.pending_files;
    if (!Array.isArray(keys) || keys.length === 0) return;

    for (const key of keys) {
        const input = form.querySelector(
            `input[type="file"][name="files[${key}]"]`,
        );
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
                captured_at: new Date().toISOString(),
            })
                .then(() => queuePhotos(clientUuid, form, payload))
                .then(() => {
                    form.reset();
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
        if (
            form instanceof HTMLFormElement &&
            form.action.includes("/logout")
        ) {
            clearAll().catch(() => {});
        }
    });
}

/**
 * Offline-Änderungs-Seite (Phase 3, MVP-367): rendert Outbox + abgelehnte
 * Befehle aus IndexedDB; Texte kommen als data-Attribute/Template aus Blade
 * (übersetzt, CSP-konform). Aktionen: „Erneut anwenden" (neue client_uuid →
 * Outbox) und „Verwerfen".
 */
const rejectedAll = () => storeAll(REJECTED);
const rejectedDelete = (clientUuid) => storeDelete(REJECTED, clientUuid);
const conflictAll = () => storeAll(CONFLICTS);

async function renderChangesPage(root) {
    const template = /** @type {HTMLTemplateElement} */ (
        document.querySelector("[data-sync-item-template]")
    );
    if (!template) return;

    const typeLabel = (type) =>
        root.dataset[
            "labelType" +
                (type || "")
                    .replace(/[.-](\w)/g, (_, c) => c.toUpperCase())
                    .replace(/^(\w)/, (_, c) => c.toUpperCase())
        ] || type;

    const sections = {
        outbox: {
            items: await outboxAll(),
            label: root.dataset.labelPending,
            retry: false,
        },
        // Konflikte stehen VOR den Ablehnungen: sie sind das Einzige auf der
        // Seite, das ohne Entscheidung des Nutzers nicht weitergeht.
        conflicts: {
            items: await conflictAll(),
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
        const el = root.querySelector(`[data-offline-section="${name}"]`);
        if (!el) continue;
        el.querySelector("[data-section-heading]").textContent =
            section.label || name;
        const list = el.querySelector("[data-section-list]");
        list.textContent = "";
        el.hidden = section.items.length === 0;
        total += section.items.length;

        for (const item of section.items) {
            const node = /** @type {HTMLElement} */ (
                template.content.firstElementChild.cloneNode(true)
            );
            node.querySelector("[data-item-type]").textContent = typeLabel(
                item.type,
            );
            node.querySelector("[data-item-time]").textContent =
                item.captured_at || item.rejected_at || "";

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
                        captured_at: new Date().toISOString(),
                    });
                    await rejectedDelete(item.client_uuid);
                    await flush();
                    renderChangesPage(root);
                });
            }

            if (section.conflict) {
                const hint = /** @type {HTMLElement} */ (
                    node.querySelector("[data-item-server]")
                );
                if (hint && item.server) {
                    hint.textContent = (
                        root.dataset.labelConflictHint || ":server"
                    ).replace(
                        ":server",
                        Object.entries(item.server)
                            .map(([k, v]) => `${k}: ${v ?? "—"}`)
                            .join(", "),
                    );
                    hint.hidden = false;
                }

                // „Meine Fassung senden": neu in die Outbox, aber mit dem
                // INZWISCHEN gueltigen Stand als base_version — sonst liefe
                // der Befehl sofort in denselben Konflikt.
                const forceBtn = /** @type {HTMLElement} */ (
                    node.querySelector("[data-item-force]")
                );
                if (forceBtn && item.payload && item.current_version) {
                    forceBtn.hidden = false;
                    forceBtn.addEventListener("click", async () => {
                        await outboxPut({
                            client_uuid: crypto.randomUUID(),
                            type: item.type,
                            payload: {
                                ...item.payload,
                                base_version: item.current_version,
                            },
                            captured_at: new Date().toISOString(),
                        });
                        await conflictDelete(item.client_uuid);
                        await flush();
                        renderChangesPage(root);
                    });
                }
            }

            const discardBtn = /** @type {HTMLElement} */ (
                node.querySelector("[data-item-discard]")
            );
            if (section.conflict && root.dataset.labelTakeServer) {
                // Im Konfliktfall heisst „Verwerfen" fachlich etwas anderes:
                // die eigene Fassung fallen lassen und den fremden Stand
                // stehen lassen. Der Knopf sagt das auch.
                discardBtn.textContent = root.dataset.labelTakeServer;
                discardBtn.classList.remove("text-error");
            }

            discardBtn.addEventListener("click", async () => {
                if (name === "outbox") await outboxDelete(item.client_uuid);
                else if (name === "conflicts")
                    await conflictDelete(item.client_uuid);
                else await rejectedDelete(item.client_uuid);
                updateBadge();
                renderChangesPage(root);
            });

            list.appendChild(node);
        }
    }

    const empty = root.querySelector("[data-offline-empty]");
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
 */
async function courseStore(enrollment, url) {
    // Ueber die HTTP-Naht, nicht ueber rohes fetch: dort haengen CSRF-Token,
    // credentials und die 419-Behandlung. Ein abgelaufenes Login endete sonst
    // als stiller Fehler, und der Kurs waere „gespeichert" ohne Inhalt.
    const result = await getJson(url);

    if (!result.ok || !result.data) throw new Error("offline-bundle");

    const bundle = result.data;
    const db = await openDb();

    await tx(db, COURSES, "readwrite", (store) =>
        store.put({ ...bundle, enrollment }),
    );

    return bundle;
}

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

export function initOfflineSync() {
    if (typeof indexedDB === "undefined") return;

    bindForms();
    bindOfflineCourses();
    window.addEventListener("online", flush);
    window.addEventListener("offline", updateBadge);
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") flush();
    });

    flush();

    const changesRoot = document.querySelector("[data-offline-changes]");
    if (changesRoot) renderChangesPage(changesRoot);
}

// Nur für Unit-Tests (tests/frontend/offline-sync.test.mjs, node:test):
// pure Outbox-/Flush-/Payload-Logik ohne UI-Bindung. Kein App-Code
// importiert dieses Objekt.
export const __testables = {
    buildPayload,
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
};
