/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help-recent.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Zuletzt angesehene Hilfethemen (MVP-972): nur im Browser, je Sprache, damit
// die gespeicherten Titel zur Oberfläche passen. Keine Daten an den Server.

const MAX_ENTRIES = 8;
const TOPIC_PATTERN = /^[a-z0-9.-]+$/;

function storageKey() {
    return "help.recent." + (document.documentElement.lang || "de");
}

/** @returns {{topic: string, title: string}[]} */
export function recentHelpTopics() {
    try {
        const parsed = JSON.parse(window.localStorage.getItem(storageKey()) || "[]");
        if (!Array.isArray(parsed)) return [];

        return parsed.filter(
            (entry) => entry && typeof entry.topic === "string" && TOPIC_PATTERN.test(entry.topic) && typeof entry.title === "string",
        );
    } catch {
        return [];
    }
}

/**
 * @param {unknown} topic
 * @param {unknown} title
 */
export function rememberHelpTopic(topic, title) {
    if (typeof topic !== "string" || !TOPIC_PATTERN.test(topic)) return;
    const entry = { topic, title: String(title || topic).slice(0, 200) };
    const entries = [entry, ...recentHelpTopics().filter((existing) => existing.topic !== topic)].slice(0, MAX_ENTRIES);
    try {
        window.localStorage.setItem(storageKey(), JSON.stringify(entries));
    } catch {
        // localStorage kann fehlen (Private Mode) — die Hilfe funktioniert trotzdem.
    }
}
