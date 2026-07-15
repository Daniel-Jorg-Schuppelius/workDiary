/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline-sync.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
 */

const DB_NAME = "workdiary-sync";
const DB_VERSION = 1;
const OUTBOX = "outbox";
const REJECTED = "rejected";

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

async function rejectedPut(entry) {
    const db = await openDb();
    await tx(db, REJECTED, "readwrite", (store) => store.put(entry));
}

async function rejectedCount() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const request = db
            .transaction(REJECTED, "readonly")
            .objectStore(REJECTED)
            .count();
        request.onsuccess = () => resolve(request.result || 0);
        request.onerror = () => reject(request.error);
    });
}

/** §3.4: Logout leert die Outbox (Gerätewechsel/Abmeldung). */
async function clearAll() {
    const db = await openDb();
    await tx(db, OUTBOX, "readwrite", (store) => store.clear());
    await tx(db, REJECTED, "readwrite", (store) => store.clear());
}

async function updateBadge() {
    const badge = document.querySelector("[data-sync-status]");
    if (!badge) return;

    try {
        const pending = (await outboxAll()).length;
        const rejected = await rejectedCount();
        const offline = !navigator.onLine;

        badge.hidden = pending === 0 && rejected === 0 && !offline;
        const countEl = badge.querySelector("[data-sync-pending-count]");
        if (countEl) countEl.textContent = String(pending);
        badge.dataset.syncOffline = offline ? "1" : "0";
        badge.dataset.syncRejected = rejected > 0 ? "1" : "0";
    } catch (_) {
        /* IndexedDB nicht verfügbar (privater Modus) — Badge bleibt leer. */
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
        const csrf = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        const endpoint = document
            .querySelector('meta[name="sync-endpoint"]')
            ?.getAttribute("content");
        if (!csrf || !endpoint) return;

        // Batches von 50 (Server-Limit), sequentiell.
        for (let i = 0; i < commands.length; i += 50) {
            const batch = commands.slice(i, i + 50);
            const response = await fetch(endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf,
                    Accept: "application/json",
                },
                credentials: "same-origin",
                body: JSON.stringify({ commands: batch }),
            });

            // Session abgelaufen o. ä.: Outbox behalten, später erneut.
            if (!response.ok) return;

            const data = await response.json();
            for (const result of data.results || []) {
                if (result.status === "applied" || result.status === "duplicate") {
                    await outboxDelete(result.client_uuid);
                } else {
                    // rejected/conflict: aus der Outbox nehmen (kein Endlos-
                    // Retry) und für die Anzeige aufheben (§3.3 MVP).
                    const original = batch.find(
                        (c) => c.client_uuid === result.client_uuid,
                    );
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
            template: form.querySelector('[name="form_template_id"]')?.value || "",
            values: {},
        };
        const kind = form.querySelector('[name="subject_kind"]')?.value;
        const subjectId = form.querySelector('[name="subject_id"]')?.value;
        if (kind && subjectId) {
            payload.subject_kind = kind;
            payload.subject_id = subjectId;
        }
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

            outboxPut({
                client_uuid: crypto.randomUUID(),
                type,
                payload,
                captured_at: new Date().toISOString(),
            })
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
        if (form instanceof HTMLFormElement && form.action.includes("/logout")) {
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
async function rejectedAll() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const request = db
            .transaction(REJECTED, "readonly")
            .objectStore(REJECTED)
            .getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

async function rejectedDelete(clientUuid) {
    const db = await openDb();
    await tx(db, REJECTED, "readwrite", (store) => store.delete(clientUuid));
}

async function renderChangesPage(root) {
    const template = document.querySelector("[data-sync-item-template]");
    if (!template) return;

    const typeLabel = (type) =>
        root.dataset["labelType" + (type || "").replace(/[.-](\w)/g, (_, c) => c.toUpperCase()).replace(/^(\w)/, (_, c) => c.toUpperCase())] || type;

    const sections = {
        outbox: { items: await outboxAll(), label: root.dataset.labelPending, retry: false },
        rejected: { items: await rejectedAll(), label: root.dataset.labelRejected, retry: true },
    };

    let total = 0;
    for (const [name, section] of Object.entries(sections)) {
        const el = root.querySelector(`[data-offline-section="${name}"]`);
        if (!el) continue;
        el.querySelector("[data-section-heading]").textContent = section.label || name;
        const list = el.querySelector("[data-section-list]");
        list.textContent = "";
        el.hidden = section.items.length === 0;
        total += section.items.length;

        for (const item of section.items) {
            const node = template.content.firstElementChild.cloneNode(true);
            node.querySelector("[data-item-type]").textContent = typeLabel(item.type);
            node.querySelector("[data-item-time]").textContent = item.captured_at || item.rejected_at || "";

            const errorsEl = node.querySelector("[data-item-errors]");
            if (item.errors) {
                errorsEl.textContent = Object.values(item.errors).flat().join(" ");
                errorsEl.hidden = false;
            }

            const retryBtn = node.querySelector("[data-item-retry]");
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

            node.querySelector("[data-item-discard]").addEventListener("click", async () => {
                await (name === "outbox" ? outboxDelete(item.client_uuid) : rejectedDelete(item.client_uuid));
                updateBadge();
                renderChangesPage(root);
            });

            list.appendChild(node);
        }
    }

    const empty = root.querySelector("[data-offline-empty]");
    if (empty) empty.hidden = total > 0;
}

export function initOfflineSync() {
    if (typeof indexedDB === "undefined") return;

    bindForms();
    window.addEventListener("online", flush);
    window.addEventListener("offline", updateBadge);
    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") flush();
    });

    flush();

    const changesRoot = document.querySelector("[data-offline-changes]");
    if (changesRoot) renderChangesPage(changesRoot);
}
