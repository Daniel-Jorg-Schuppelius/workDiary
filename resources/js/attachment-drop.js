/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attachment-drop.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/**
 * Ziehen und Ablegen auf Anhangsflächen (Feature 204, MVP-1653): eine Datei —
 * etwa eine Mail aus Outlook (.msg) oder eine .eml — auf eine Karte
 * `[data-attachment-drop-zone]` ziehen; sie wird in das Dateifeld des
 * Formulars `form[data-attachment-drop]` gelegt und normal abgesendet. Prüfung
 * von Typ, Größe und Mail-Inhalt bleibt beim Server. Über Ereignisdelegation am
 * Dokument, damit auch per AJAX geladene Dialoge mitmachen.
 */
import { carriesFiles, singleDroppedFile } from "./lib/file-drop.js";

const ACTIVE = ["ring-2", "ring-primary"];

/**
 * @param {EventTarget | null} target
 * @returns {HTMLElement | null}
 */
function zoneOf(target) {
    if (!(target instanceof Element)) return null;
    const zone = target.closest("[data-attachment-drop-zone]");
    return zone instanceof HTMLElement && zone.querySelector("form[data-attachment-drop]") ? zone : null;
}

/**
 * @param {HTMLElement} zone
 * @param {boolean} active
 */
function mark(zone, active) {
    for (const cls of ACTIVE) zone.classList.toggle(cls, active);
}

/**
 * @param {HTMLElement} zone
 * @param {DataTransfer} transfer
 */
function drop(zone, transfer) {
    const form = zone.querySelector("form[data-attachment-drop]");
    const input = form?.querySelector('input[type="file"]');
    const status = zone.querySelector("[data-attachment-drop-status]");
    if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement)) return;

    const file = singleDroppedFile(transfer.files);
    if (file === null) {
        if (status) status.textContent = form.dataset.dropSingle ?? "";
        return;
    }
    const selection = new DataTransfer();
    selection.items.add(file);
    input.files = selection.files;
    form.requestSubmit();
}

if (typeof document !== "undefined") {
    document.addEventListener("dragover", (event) => {
        const zone = zoneOf(event.target);
        if (zone === null || !carriesFiles(event.dataTransfer)) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
        mark(zone, true);
    });
    document.addEventListener("dragleave", (event) => {
        const zone = zoneOf(event.target);
        if (zone === null) return;
        const next = event.relatedTarget;
        if (!(next instanceof Node) || !zone.contains(next)) mark(zone, false);
    });
    document.addEventListener("drop", (event) => {
        const zone = zoneOf(event.target);
        if (zone === null || !event.dataTransfer || !carriesFiles(event.dataTransfer)) return;
        event.preventDefault();
        mark(zone, false);
        drop(zone, event.dataTransfer);
    });
}
