/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : video-progress.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Pflichtanteil eines Kursvideos (Feature 149, MVP-1598): meldet neu gesehene
 * Sekunden an den Server und gibt „Abschließen" frei, sobald der Anteil
 * erreicht ist. Die Abschnitte bleiben im Browser; der Server erhält und
 * speichert nur die Summe.
 */
import { postJson } from "./lib/http.js";
import { addInterval, coveredSeconds, isContinuous, parseIntervals } from "./lib/video-coverage.js";

const KEY_PREFIX = "wd.video-progress.";
const FLUSH_AFTER_SECONDS = 10;
const RETRY_MS = 6000;
const MAX_IDLE_RETRIES = 20;

/**
 * @typedef {Object} ProgressReply
 * @property {number} [accepted]
 * @property {number} [percent]
 * @property {boolean} [satisfied]
 * @property {boolean} [unit_satisfied]
 */

/**
 * @param {string} key
 * @returns {{ intervals: import("./lib/video-coverage.js").Interval[], acknowledged: number }}
 */
function readState(key) {
    try {
        /** @type {unknown} */
        const data = JSON.parse(window.localStorage.getItem(KEY_PREFIX + key) || "null");
        if (data && typeof data === "object") {
            const record = /** @type {{ i?: unknown, a?: unknown }} */ (data);
            const acknowledged = Number(record.a);
            return {
                intervals: parseIntervals(JSON.stringify(record.i ?? [])),
                acknowledged: Number.isFinite(acknowledged) && acknowledged > 0 ? acknowledged : 0,
            };
        }
    } catch {
        // Privater Modus oder beschädigter Wert: dann von vorn.
    }
    return { intervals: [], acknowledged: 0 };
}

/**
 * @param {string} key
 * @param {import("./lib/video-coverage.js").Interval[]} intervals
 * @param {number} acknowledged
 */
function writeState(key, intervals, acknowledged) {
    try {
        const rounded = intervals.map(([a, b]) => [Math.round(a * 10) / 10, Math.round(b * 10) / 10]);
        window.localStorage.setItem(KEY_PREFIX + key, JSON.stringify({ i: rounded, a: acknowledged }));
    } catch {
        // siehe readState()
    }
}

/**
 * @param {string} gate
 * @param {boolean} open
 */
function setGate(gate, open) {
    if (!gate || !open) return;
    for (const form of document.querySelectorAll("form[data-video-gate]")) {
        if (!(form instanceof HTMLFormElement) || form.dataset.videoGate !== gate) continue;
        for (const button of form.querySelectorAll("button[type=submit]")) {
            if (button instanceof HTMLButtonElement) button.disabled = false;
        }
    }
    for (const hint of document.querySelectorAll("[data-video-gate-hint]")) {
        if (hint instanceof HTMLElement && hint.dataset.videoGateHint === gate) hint.hidden = true;
    }
}

/** @param {HTMLVideoElement} video */
function bind(video) {
    const url = video.dataset.videoProgress || "";
    if (!url) return;

    const required = Number(video.dataset.progressRequired) || 0;
    const gate = video.dataset.progressGate || "";
    const status = document.getElementById(video.dataset.progressStatus || "");
    const state = readState(url);

    /** @type {number|null} */
    let last = null;
    let sending = false;
    let stopped = false;
    let idleRetries = 0;
    /** @type {number|undefined} */
    let retryTimer;

    /** @param {number} percent */
    function show(percent) {
        if (!status) return;
        const template = status.dataset.template || "";
        status.textContent = template.replace("__P__", String(percent));
        status.classList.toggle("text-success", percent >= required);
        status.classList.toggle("text-warning", percent < required);
    }

    function pending() {
        return coveredSeconds(state.intervals) - state.acknowledged;
    }

    async function flush() {
        const seconds = Math.floor(pending());
        if (stopped || sending || seconds < 1) return;
        sending = true;
        window.clearTimeout(retryTimer);
        try {
            // Eine abgelaufene Sitzung darf das laufende Video nicht neu laden.
            const result = await postJson(url, { watched_seconds: Math.min(seconds, 600) }, { on419: "ignore" });
            if (result.ok) {
                const reply = /** @type {ProgressReply} */ (result.data ?? {});
                const accepted = Number(reply.accepted) || 0;
                const percent = Number(reply.percent) || 0;
                state.acknowledged += accepted;
                idleRetries = accepted > 0 ? 0 : idleRetries + 1;
                if (percent >= 100) {
                    // Voll gesehen: ein Rest aus Rundung bleibt nicht offen.
                    state.acknowledged = coveredSeconds(state.intervals);
                }
                writeState(url, state.intervals, state.acknowledged);
                show(percent);
                setGate(gate, reply.unit_satisfied === true);
            } else if (result.status >= 400 && result.status < 500 && result.status !== 419 && result.status !== 429) {
                // Abgeschlossen, gesperrt oder Video ohne Dauer: weiteres Melden ist sinnlos.
                stopped = true;
            }
        } catch {
            // Ohne Netz bleibt der Rest stehen und geht mit der nächsten Meldung.
        } finally {
            sending = false;
        }
        // Der Server rechnet höchstens im Tempo der Uhr an — den Rest nachreichen.
        if (!stopped && pending() >= 1 && idleRetries < MAX_IDLE_RETRIES) {
            retryTimer = window.setTimeout(flush, RETRY_MS);
        }
    }

    video.addEventListener("timeupdate", () => {
        const now = video.currentTime;
        if (!video.seeking && !video.paused && isContinuous(last, now, video.playbackRate)) {
            state.intervals = addInterval(state.intervals, /** @type {number} */ (last), now);
            writeState(url, state.intervals, state.acknowledged);
        }
        last = video.seeking ? null : now;
        if (pending() >= FLUSH_AFTER_SECONDS) flush();
    });
    // Ein Sprung trennt die Abschnitte: was dazwischen liegt, wurde nicht gesehen.
    video.addEventListener("seeking", () => {
        last = null;
    });
    video.addEventListener("playing", () => {
        last = video.currentTime;
    });
    video.addEventListener("pause", () => {
        last = null;
        flush();
    });
    video.addEventListener("ended", () => {
        last = null;
        flush();
    });

    if (pending() >= 1) flush();
}

export function initVideoProgress(root = document) {
    for (const video of root.querySelectorAll("video[data-video-progress]")) {
        if (video instanceof HTMLVideoElement) bind(video);
    }
}
