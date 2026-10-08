/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dictation.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Sprachdiktat (MVP-1060): erster Klick nimmt auf, zweiter schickt die
// Aufnahme; das Ergebnis landet im Zielfeld als Vorschlag — gespeichert wird
// erst mit dem Formular. Delegiert, weil die Knöpfe in nachgeladenen Dialogen stehen.

import { getJson, postForm } from "./lib/http.js";

const POLL_MS = 2000;
const POLL_LIMIT = 150;
/** @type {{button: HTMLElement, recorder: MediaRecorder} | null} */
let recording = null;

/**
 * Antwort von DictationController::show().
 * @typedef {object} DictationResult
 * @property {string} [status]
 * @property {string | null} [transcript]
 * @property {Record<string, string>} [fields]
 * @property {string | null} [error]
 */

/**
 * @param {HTMLElement} button
 * @param {string} state
 */
function setState(button, state) {
    button.dataset.dictationState = state;
    const label = button.dataset["label" + state.charAt(0).toUpperCase() + state.slice(1)];
    if (label) {
        button.setAttribute("aria-label", label);
        button.title = label;
        const text = button.querySelector("[data-dictation-text]");
        if (text) text.textContent = label;
    }
    button.classList.toggle("btn-error", state === "recording");
}

/**
 * @param {HTMLElement} button
 * @param {Record<string, string>} fields
 * @param {string | null | undefined} transcript
 */
function compose(button, fields, transcript) {
    const parts = [];
    for (const key of (button.dataset.dictationFields || "").split(",").filter(Boolean)) {
        if (fields[key]) {
            const heading = button.dataset["heading" + key.replace(/(^|_)(\w)/g, (_, __, c) => c.toUpperCase())];
            parts.push(heading ? heading + ":\n" + fields[key] : fields[key]);
        }
    }
    return parts.length > 0 ? parts.join("\n\n") : transcript || "";
}

/**
 * @param {HTMLElement} button
 * @param {string} text
 */
function insert(button, text) {
    const target = /** @type {HTMLInputElement | HTMLTextAreaElement | null} */ (document.querySelector(button.dataset.dictationTarget || ""));
    if (!target || !text) return;
    target.value = target.value.trim() === "" ? text : target.value.replace(/\s+$/, "") + "\n\n" + text;
    target.dispatchEvent(new Event("input", { bubbles: true }));
    target.focus();
}

/**
 * @param {HTMLElement} button
 * @param {string} url
 */
async function poll(button, url) {
    for (let i = 0; i < POLL_LIMIT; i++) {
        await new Promise((resolve) => setTimeout(resolve, POLL_MS));
        let response;
        try {
            response = await getJson(url);
        } catch (_) {
            continue;
        }
        const result = /** @type {DictationResult} */ (response.ok ? response.data || {} : {});
        if (result.status === "done") {
            insert(button, compose(button, result.fields || {}, result.transcript));
            setState(button, "idle");
            return;
        }
        if (result.status === "failed") {
            setState(button, "idle");
            if (result.error) button.title = result.error;
            return;
        }
    }
    setState(button, "idle");
}

/**
 * @param {HTMLElement} button
 * @param {Blob} blob
 */
async function upload(button, blob) {
    setState(button, "working");
    const body = new FormData();
    body.append("audio", blob, "diktat." + (blob.type.includes("ogg") ? "ogg" : blob.type.includes("mp4") ? "m4a" : "webm"));
    body.append("context", button.dataset.dictationContext || "diary");
    let response;
    try {
        response = /** @type {import("./lib/http.js").JsonResult<{ id?: string }>} */ (
            await postForm(/** @type {string} */ (button.dataset.dictationStore), body)
        );
    } catch (_) {
        setState(button, "idle");
        return;
    }
    if (!response.ok || !response.data?.id) {
        setState(button, "idle");
        return;
    }
    await poll(button, /** @type {string} */ (button.dataset.dictationShow).replace("__ID__", encodeURIComponent(response.data.id)));
}

/** @param {HTMLElement} button */
async function start(button) {
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === "undefined") {
        setState(button, "unsupported");
        return;
    }
    let stream;
    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (_) {
        setState(button, "denied");
        return;
    }
    /** @type {Blob[]} */
    const chunks = [];
    const recorder = new MediaRecorder(stream);
    recorder.addEventListener("dataavailable", (event) => {
        if (event.data.size > 0) chunks.push(event.data);
    });
    recorder.addEventListener("stop", () => {
        stream.getTracks().forEach((track) => track.stop());
        upload(button, new Blob(chunks, { type: recorder.mimeType || "audio/webm" }));
    });
    recording = { button, recorder };
    recorder.start();
    setState(button, "recording");
}

document.addEventListener("click", (event) => {
    const button = /** @type {HTMLElement | null} */ (
        event.target instanceof Element
            ? event.target.closest("[data-dictation]")
            : null
    );
    if (!button) return;
    event.preventDefault();
    if (recording && recording.button === button) {
        recording.recorder.stop();
        recording = null;
        return;
    }
    if (recording || button.dataset.dictationState === "working") return;
    start(button);
});
