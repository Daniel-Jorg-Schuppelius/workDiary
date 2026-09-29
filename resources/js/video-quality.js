/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : video-quality.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Qualitätswahl im Videoplayer (Feature 150, MVP-1022): wechselt die Fassung
 * ohne Neuladen der Seite, behält Position und Wiedergabe und speichert die
 * Wahl als Nutzerpräferenz — das nächste Video startet in derselben Stufe.
 */
import { postJson } from "./lib/http.js";

/** @param {HTMLSelectElement} select */
function bind(select) {
    const video = document.getElementById(select.dataset.videoQuality || "");
    if (!(video instanceof HTMLVideoElement)) return;

    select.addEventListener("change", () => {
        const src = select.selectedOptions[0]?.dataset.src;
        if (!src) return;

        const position = video.currentTime;
        const playing = !video.paused;
        video.addEventListener(
            "loadedmetadata",
            () => {
                video.currentTime = position;
                if (playing) video.play().catch(() => {});
            },
            { once: true },
        );
        video.src = src;
        video.load();

        const url = select.dataset.saveUrl;
        // Eine abgelaufene Sitzung darf das laufende Video nicht neu laden.
        if (url) postJson(url, { quality: select.value }, { on419: "ignore" }).catch(() => {});
    });
}

export function initVideoQuality(root = document) {
    for (const select of root.querySelectorAll("select[data-video-quality]")) {
        if (select instanceof HTMLSelectElement) bind(select);
    }
}
