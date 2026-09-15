/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : video-position.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Wiedergabeposition je Videoblock merken (Feature 149, MVP-788): lokal im
 * Browser, je Block ein Schlüssel — kein Server, kein Nachweiswert. Kurz vor
 * dem Ende wird der Merker gelöscht, sonst startet ein fertig gesehenes
 * Video beim nächsten Mal in den letzten Sekunden.
 */
const KEY_PREFIX = "wd.video.";

function read(key) {
    try {
        const raw = window.localStorage.getItem(KEY_PREFIX + key);
        const value = raw === null ? NaN : Number(raw);
        return Number.isFinite(value) && value > 0 ? value : null;
    } catch {
        return null;
    }
}

function write(key, seconds) {
    try {
        window.localStorage.setItem(KEY_PREFIX + key, String(Math.floor(seconds)));
    } catch {
        // Privater Modus oder gesperrter Speicher: dann eben ohne Merker.
    }
}

function clear(key) {
    try {
        window.localStorage.removeItem(KEY_PREFIX + key);
    } catch {
        // siehe write()
    }
}

function bind(video) {
    const key = video.dataset.rememberPosition;
    if (!key) return;

    let lastSaved = 0;

    video.addEventListener("loadedmetadata", () => {
        const stored = read(key);
        if (stored !== null && video.duration && stored < video.duration - 5) {
            video.currentTime = stored;
        }
    });

    video.addEventListener("timeupdate", () => {
        const now = video.currentTime;
        if (now - lastSaved < 5) return;
        lastSaved = now;
        if (video.duration && now >= video.duration - 5) {
            clear(key);
        } else {
            write(key, now);
        }
    });

    video.addEventListener("ended", () => clear(key));
}

export function initVideoPositions(root = document) {
    for (const video of root.querySelectorAll("video[data-remember-position]")) {
        if (video instanceof HTMLVideoElement) bind(video);
    }
}
