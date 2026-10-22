/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : chunk-upload.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/**
 * Wiederaufnehmbarer Upload großer Medien (MVP-1265). Ein Dateifeld mit
 * `data-chunk-upload="<Start-URL>"` lädt Dateien oberhalb von
 * `data-chunk-threshold` (Bytes) vor dem Absenden in Teilen hoch und gibt dem
 * Formular nur noch das Kennzeichen im versteckten Feld
 * `data-chunk-target` mit. Bricht die Verbindung ab, setzt der nächste
 * Versuch beim ersten fehlenden Teil fort (Kennzeichen im localStorage).
 */
import { __ } from "./i18n.js";
import { getJson, postForm, postJson } from "./lib/http.js";

/**
 * @typedef {{ token: string, chunk_size: number, chunks: number, received: number[] }} UploadState
 */

/**
 * @param {File} file
 * @param {string} startUrl
 */
function storageKey(file, startUrl) {
    return "chunk-upload:" + startUrl + ":" + file.name + ":" + file.size + ":" + file.lastModified;
}

/**
 * @param {string} key
 * @returns {string | null}
 */
function readToken(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

/**
 * @param {string} key
 * @param {string | null} token
 */
function writeToken(key, token) {
    try {
        if (token === null) window.localStorage.removeItem(key);
        else window.localStorage.setItem(key, token);
    } catch {
        // Ohne Speicher beginnt ein abgebrochener Upload von vorn.
    }
}

/**
 * @param {unknown} data
 * @returns {UploadState | null}
 */
function asState(data) {
    const value = /** @type {Partial<UploadState> | null} */ (data);
    if (!value || typeof value.token !== "string") return null;
    return {
        token: value.token,
        chunk_size: Number(value.chunk_size || 0),
        chunks: Number(value.chunks || 0),
        received: Array.isArray(value.received) ? value.received.map(Number) : [],
    };
}

/**
 * @param {File} file
 * @param {string} startUrl
 * @param {(percent: number) => void} progress
 * @returns {Promise<string>}
 */
async function upload(file, startUrl, progress) {
    const key = storageKey(file, startUrl);
    /** @type {UploadState | null} */
    let state = null;
    const known = readToken(key);
    if (known) {
        const resumed = await getJson(startUrl + "/" + encodeURIComponent(known));
        state = resumed.ok ? asState(resumed.data) : null;
    }
    if (!state) {
        const started = await postJson(startUrl, { name: file.name, size: file.size, type: file.type });
        state = started.ok ? asState(started.data) : null;
        if (!state) {
            const first = started.errors ? Object.values(started.errors)[0] : null;
            throw new Error(first && first[0] ? first[0] : __("js.chunk_upload.failed"));
        }
        writeToken(key, state.token);
    }

    const done = new Set(state.received);
    for (let index = 0; index < state.chunks; index++) {
        if (done.has(index)) continue;
        const body = new FormData();
        body.append("chunk", file.slice(index * state.chunk_size, (index + 1) * state.chunk_size), "part");
        const sent = await postForm(startUrl + "/" + encodeURIComponent(state.token) + "/" + index, body);
        if (!sent.ok) throw new Error(__("js.chunk_upload.failed"));
        done.add(index);
        progress(Math.round((done.size / state.chunks) * 100));
    }
    writeToken(key, null);
    return state.token;
}

/**
 * @param {HTMLInputElement} input
 */
function bindChunkUpload(input) {
    const form = input.form;
    const startUrl = input.dataset.chunkUpload || "";
    const threshold = Number(input.dataset.chunkThreshold || 0);
    const target = form ? form.querySelector('input[name="' + (input.dataset.chunkTarget || "") + '"]') : null;
    const status = input.dataset.chunkStatus ? document.getElementById(input.dataset.chunkStatus) : null;
    if (!form || !startUrl || !(target instanceof HTMLInputElement)) return;

    let busy = false;
    form.addEventListener("submit", (event) => {
        const file = input.files && input.files[0];
        if (busy || !file || file.size <= threshold || target.value !== "") return;
        event.preventDefault();
        busy = true;
        const show = (/** @type {string} */ text) => {
            if (status) status.textContent = text;
        };
        show(__("js.chunk_upload.progress", { percent: 0 }));
        upload(file, startUrl, (percent) => show(__("js.chunk_upload.progress", { percent })))
            .then((token) => {
                target.value = token;
                input.value = "";
                show(__("js.chunk_upload.done"));
                form.requestSubmit();
            })
            .catch((/** @type {unknown} */ error) => {
                show(error instanceof Error ? error.message : __("js.chunk_upload.failed"));
            })
            .finally(() => {
                busy = false;
            });
    });
}

/**
 * @param {ParentNode} [root]
 */
export function bindChunkUploads(root = document) {
    root.querySelectorAll("input[type=file][data-chunk-upload]").forEach((node) => {
        const input = /** @type {HTMLInputElement} */ (node);
        if (input.dataset.chunkBound === "1") return;
        input.dataset.chunkBound = "1";
        bindChunkUpload(input);
    });
}

if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => bindChunkUploads());
    } else {
        bindChunkUploads();
    }
}
