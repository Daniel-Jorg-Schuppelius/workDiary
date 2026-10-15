/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : collapsible-section.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Aufklappbare Seitenabschnitte (x-collapsible-section, Feature 165):
 * merkt den Zustand je Schlüssel im Browser der Person. Rein kosmetisch —
 * der Server rendert immer den vollständigen Inhalt.
 */

const STORAGE_PREFIX = "wd.collapsible.";

/** @param {string} key */
function readState(key) {
    try {
        return window.localStorage.getItem(STORAGE_PREFIX + key);
    } catch {
        return null;
    }
}

/**
 * @param {string} key
 * @param {boolean} open
 */
function writeState(key, open) {
    try {
        window.localStorage.setItem(STORAGE_PREFIX + key, open ? "1" : "0");
    } catch {
        // Privater Modus oder gesperrter Speicher: Zustand bleibt nur für diese Seite.
    }
}

/** @param {ParentNode} root */
function initCollapsibleSections(root) {
    root.querySelectorAll("details[data-collapsible]").forEach((node) => {
        if (!(node instanceof HTMLDetailsElement) || node.dataset.collapsibleBound === "1") {
            return;
        }
        node.dataset.collapsibleBound = "1";
        const key = node.dataset.collapsible ?? "";
        if (key === "") {
            return;
        }
        const stored = readState(key);
        if (stored === "1") {
            node.open = true;
        } else if (stored === "0") {
            node.open = false;
        }
        // `toggle` bubbelt nicht — deshalb je Element statt per Delegation.
        node.addEventListener("toggle", () => writeState(key, node.open));
    });
}

document.addEventListener("DOMContentLoaded", () => initCollapsibleSections(document));
// Per Dialog oder Fragment nachgeladene Abschnitte.
document.addEventListener("wd:fragment-loaded", (event) => {
    const target = event.target;
    if (target instanceof Element) {
        initCollapsibleSections(target);
    }
});
