/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : storage-estimate.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Nennt beim Auswählen eines Videos den zu erwartenden Speicherbedarf
 * (Feature 150, MVP-1600) — vor dem Upload, nicht erst an der Quote.
 */
import { describeBytes, estimateTotalBytes } from "./lib/storage-estimate.js";

/** @param {HTMLInputElement} input */
function bind(input) {
    const target = document.getElementById(input.dataset.storageEstimate || "");
    if (!target) return;
    const factor = Number(input.dataset.storageEstimateFactor);
    const locale = document.documentElement.lang || "de";

    input.addEventListener("change", () => {
        const file = input.files?.[0];
        const total = file && file.type.startsWith("video/") ? estimateTotalBytes(file.size, factor) : 0;
        if (!file || total === 0) {
            target.hidden = true;
            target.textContent = "";
            return;
        }
        target.textContent = (target.dataset.template || "")
            .replace("__T__", describeBytes(total, locale))
            .replace("__O__", describeBytes(file.size, locale));
        target.hidden = false;
    });
}

export function initStorageEstimate(root = document) {
    for (const input of root.querySelectorAll("input[type=file][data-storage-estimate]")) {
        if (input instanceof HTMLInputElement) bind(input);
    }
}
