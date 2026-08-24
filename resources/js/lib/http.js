/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : http.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Zentrale HTTP-Grenze für alle Frontend-Module (Vollscan 2026-08-23, I14).
 *
 * Vorher baute jedes Modul CSRF-/JSON-Header selbst — mit uneinheitlicher
 * Fehlerbehandlung. Hier gilt einheitlich:
 *  - CSRF-Token (Meta-Tag) + credentials: "same-origin" automatisch,
 *  - 419 (Session/CSRF abgelaufen): Hinweis + Seiten-Reload; Hintergrund-
 *    Sync kann via `on419: "ignore"` opt-outen (Outbox behalten!),
 *  - 422: Validierungsfehler als Objekt im Ergebnis (`errors`).
 *
 * `request()` ist die rohe Naht (Response), die *Json-Helfer liefern ein
 * einheitliches Ergebnisobjekt. `submitForm()` deckt die klassischen
 * Redirect+Flash-Flows ab (verstecktes <form> statt fetch).
 */

import { __ } from "../i18n.js";

/** @returns {string} CSRF-Token aus dem Layout-Meta-Tag. */
export function csrfToken() {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || ""
    );
}

/**
 * @typedef {Object} HttpOptions
 * @property {string} [method] HTTP-Methode (Default GET)
 * @property {Record<string, string>} [headers] Zusatz-/Override-Header (z. B. X-Socket-ID)
 * @property {unknown} [json] JSON-Payload (setzt Content-Type + Body)
 * @property {BodyInit} [body] Roh-Body (FormData/URLSearchParams/String) — ohne json
 * @property {"reload"|"ignore"} [on419] Default "reload": Hinweis + Seiten-Reload
 */

/**
 * @typedef {Object} JsonResult
 * @property {boolean} ok
 * @property {number} status
 * @property {any} data geparster JSON-Body (null, wenn keiner)
 * @property {Record<string, string[]>|null} errors 422er-Feldfehler (sonst null)
 */

// Nur EIN Reload, auch wenn mehrere Requests parallel in 419 laufen.
let sessionExpiredHandled = false;

function handleSessionExpired() {
    if (sessionExpiredHandled) return;
    sessionExpiredHandled = true;
    const message = __("js.http.session_expired");
    if (typeof window.notifyAction === "function") {
        window.notifyAction({ tone: "warning", message });
        // Kurze Sichtbarkeit des Hinweises, dann frische Session laden.
        setTimeout(() => window.location.reload(), 1200);
    } else {
        window.location.reload();
    }
}

/**
 * Roher fetch mit CSRF/credentials/419-Behandlung — für Aufrufer, die die
 * Response selbst auswerten (HTML-Fragmente, Sonder-Statuscodes).
 *
 * @param {string} url
 * @param {HttpOptions} [options]
 * @returns {Promise<Response>}
 */
export async function request(url, options = {}) {
    const { method = "GET", headers = {}, json, body, on419 = "reload" } = options;

    /** @type {Record<string, string>} */
    const h = {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    };
    if (method !== "GET" && method !== "HEAD") {
        h["X-CSRF-TOKEN"] = csrfToken();
    }
    Object.assign(h, headers);

    /** @type {RequestInit} */
    const init = { method, credentials: "same-origin", headers: h };
    if (json !== undefined) {
        h["Content-Type"] = "application/json";
        init.body = JSON.stringify(json);
    } else if (body !== undefined) {
        init.body = body;
    }

    const response = await fetch(url, init);
    if (response.status === 419 && on419 !== "ignore") {
        handleSessionExpired();
    }
    return response;
}

/**
 * @param {Response} response
 * @returns {Promise<JsonResult>}
 */
async function toJsonResult(response) {
    /** @type {any} */
    let data = null;
    try {
        data = await response.json();
    } catch {
        data = null;
    }
    const errors =
        response.status === 422 && data && typeof data.errors === "object"
            ? /** @type {Record<string, string[]>} */ (data.errors)
            : null;
    return { ok: response.ok, status: response.status, data, errors };
}

/**
 * @param {string} url
 * @param {HttpOptions} [options]
 * @returns {Promise<JsonResult>}
 */
export async function getJson(url, options = {}) {
    return toJsonResult(await request(url, { ...options, method: "GET" }));
}

/**
 * @param {string} url
 * @param {unknown} [body] JSON-Payload (weglassen für Body-lose POSTs)
 * @param {HttpOptions} [options]
 * @returns {Promise<JsonResult>}
 */
export async function postJson(url, body, options = {}) {
    return toJsonResult(
        await request(url, { ...options, method: "POST", json: body }),
    );
}

/**
 * @param {string} url
 * @param {unknown} [body]
 * @param {HttpOptions} [options]
 * @returns {Promise<JsonResult>}
 */
export async function putJson(url, body, options = {}) {
    return toJsonResult(
        await request(url, { ...options, method: "PUT", json: body }),
    );
}

/**
 * @param {string} url
 * @param {unknown} [body]
 * @param {HttpOptions} [options]
 * @returns {Promise<JsonResult>}
 */
export async function patchJson(url, body, options = {}) {
    return toJsonResult(
        await request(url, { ...options, method: "PATCH", json: body }),
    );
}

/**
 * @param {string} url
 * @param {unknown} [body]
 * @param {HttpOptions} [options]
 * @returns {Promise<JsonResult>}
 */
export async function del(url, body, options = {}) {
    return toJsonResult(
        await request(url, { ...options, method: "DELETE", json: body }),
    );
}

/**
 * FormData-POST (Datei-Uploads, Formular-Snapshots) mit JSON-Antwort.
 *
 * @param {string} url
 * @param {FormData} formData
 * @param {HttpOptions} [options]
 * @returns {Promise<JsonResult>}
 */
export async function postForm(url, formData, options = {}) {
    return toJsonResult(
        await request(url, { ...options, method: "POST", body: formData }),
    );
}

/**
 * Klassischer versteckter Form-POST (Server-Redirect + Flash statt fetch) —
 * für Flows, die die Seite ohnehin neu laden (Kanban-Lifecycle,
 * Backlog-Rerank). CSRF via _token, Methoden-Spoofing via _method.
 *
 * @param {string} url
 * @param {Record<string, string>} [fields]
 * @param {string|null} [spoofMethod] z. B. "PATCH" (sonst reiner POST)
 */
export function submitForm(url, fields = {}, spoofMethod = null) {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = url;
    form.hidden = true;

    /** @param {string} name @param {string} value */
    const add = (name, value) => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };
    add("_token", csrfToken());
    if (spoofMethod) add("_method", spoofMethod);
    Object.entries(fields).forEach(([name, value]) => add(name, value));

    document.body.appendChild(form);
    form.submit();
}
