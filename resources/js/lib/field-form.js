/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : field-form.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Lange Formulare (Feature 203): reine Logik ohne DOM für `<x-field-form>`,
 * damit sie unter node:test läuft.
 *
 *  - Sichtbarkeit spiegelt `FieldSchema::isVisible()` (Bedingungslogik),
 *  - „beantwortet“ spiegelt `FieldValues::isEmpty()`,
 *  - Zählung je Abschnitt spiegelt `FieldValues::answeredBySection()`,
 *  - {@link SaveQueue} reiht das Speichern je Feld (MVP-1650): genau ein
 *    Vorgang unterwegs, jede Anfrage trägt die Version der vorigen Antwort,
 *    je Feld zählt nur der neueste Wert.
 */

/** @typedef {{field?: string, op?: string, value?: string}} FieldCondition */

/**
 * Sichtbarkeit eines Felds gegen die aktuellen Werte (skalarisiert:
 * Mehrfachauswahl kommagetrennt, Checkbox "1"/"0").
 *
 * @param {FieldCondition | undefined | null} condition
 * @param {Record<string, string>} vals
 * @returns {boolean}
 */
export function isVisible(condition, vals) {
    if (!condition || !condition.field) return true;
    const actual = String(vals[condition.field] ?? "").trim();
    const expected = String(condition.value ?? "");
    switch (condition.op || "eq") {
        case "filled":
            return actual !== "" && actual !== "0";
        case "ne":
            return actual !== expected;
        case "in":
            return expected
                .split(",")
                .map((s) => s.trim())
                .filter(Boolean)
                .includes(actual);
        default:
            return actual === expected;
    }
}

/**
 * Beantwortet? Leer zählt offen; eine nicht angehakte Checkbox ("0") auch.
 *
 * @param {string | undefined | null} value skalarisierter Wert
 * @param {boolean} [isBoolean]
 * @returns {boolean}
 */
export function isAnswered(value, isBoolean = false) {
    const v = String(value ?? "").trim();
    if (v === "") return false;
    return !(isBoolean && v === "0");
}

/**
 * Eingabename → Feld. `values[key]` (Wert), `values[key][]` (Liste) und die
 * Ergänzung `notes[key]`; alles andere (Dateien, Unterschriften, Kopffelder)
 * gehört nicht zum Wertedokument.
 *
 * @param {string | null | undefined} name
 * @param {string} prefix Eingabe-Array der Werte (Standard `values`)
 * @param {string} [notesPrefix] Eingabe-Array der Ergänzungen (Standard `notes`)
 * @returns {{key: string, kind: "value" | "list" | "note"} | null}
 */
export function parseFieldName(name, prefix, notesPrefix = "notes") {
    const m = String(name || "").match(/^([A-Za-z_]+)\[([^\]]+)\](\[\])?$/);
    if (!m) return null;
    if (m[1] === prefix) return { key: m[2], kind: m[3] ? "list" : "value" };
    if (m[1] === notesPrefix && !m[3]) return { key: m[2], kind: "note" };
    return null;
}

/**
 * Stand je Abschnitt; unsichtbare Felder zählen nicht mit.
 *
 * @param {Record<string, string[]>} sections Abschnitt → Feldschlüssel
 * @param {(key: string) => boolean} answered
 * @param {(key: string) => boolean} visible
 * @returns {Record<string, {answered: number, total: number}>}
 */
export function sectionProgress(sections, answered, visible) {
    /** @type {Record<string, {answered: number, total: number}>} */
    const out = {};
    for (const [section, keys] of Object.entries(sections)) {
        let total = 0;
        let done = 0;
        for (const key of keys) {
            if (!visible(key)) continue;
            total++;
            if (answered(key)) done++;
        }
        out[section] = { answered: done, total };
    }
    return out;
}

/**
 * `:answered von :total beantwortet` mit Zahlen füllen.
 *
 * @param {string} template
 * @param {{answered: number, total: number} | undefined} progress
 * @returns {string}
 */
export function progressText(template, progress) {
    const p = progress || { answered: 0, total: 0 };
    return String(template || "")
        .replace(/:answered\b/g, String(p.answered))
        .replace(/:total\b/g, String(p.total));
}

/**
 * @typedef {{key: string, value?: unknown, note?: string | null}} SaveJob
 * @typedef {{ok: true, version: number, data?: unknown}
 *   | {ok: false, kind: "conflict" | "closed" | "invalid" | "offline" | "error", version?: number, message?: string}} SaveOutcome
 * @typedef {"idle" | "saving" | "retrying" | "stalled" | "conflict" | "closed"} SaveState
 */

/**
 * Warteschlange fürs Speichern je Feld (MVP-1650).
 *
 * Genau ein Vorgang ist unterwegs: Die Versionsprüfung des Servers stimmt nur,
 * wenn jede Anfrage die Version trägt, die die vorige Antwort geliefert hat.
 * Ändert jemand ein Feld erneut, bevor es gesendet ist, ersetzt der neue Wert
 * den alten (auch während der alte unterwegs ist — dann folgt der neue).
 * Netzfehler werden mit Pausen wiederholt; ein Versionskonflikt hält die
 * Schlange an, bis der Nutzer entscheidet ({@link SaveQueue#resumeWith}).
 */
export class SaveQueue {
    /**
     * @param {{
     *   send: (job: SaveJob, version: number) => Promise<SaveOutcome>,
     *   version?: number | null,
     *   prepare?: () => Promise<number>,
     *   onState?: (state: SaveState, queue: SaveQueue) => void,
     *   onSaved?: (job: SaveJob, outcome: {ok: true, version: number, data?: unknown}) => void,
     *   onInvalid?: (job: SaveJob, outcome: SaveOutcome) => void,
     *   retryDelays?: number[],
     *   wait?: (ms: number) => Promise<void>,
     * }} options
     */
    constructor({ send, version = null, prepare, onState, onSaved, onInvalid, retryDelays = [1000, 3000, 8000], wait }) {
        this.send = send;
        /** @type {number | null} null: Träger entsteht erst mit dem ersten Speichern ({@link prepare}). */
        this.version = version;
        this.prepare = prepare || null;
        this.onState = onState || (() => {});
        this.onSaved = onSaved || (() => {});
        this.onInvalid = onInvalid || (() => {});
        this.retryDelays = retryDelays.length > 0 ? retryDelays : [1000];
        this.wait = wait || ((ms) => new Promise((resolve) => setTimeout(resolve, ms)));
        /** @type {SaveJob[]} */
        this.pending = [];
        this.running = false;
        /** @type {SaveState} */
        this.state = "idle";
        /** @type {number | null} Serverversion beim Konflikt */
        this.conflictVersion = null;
        /** @type {Array<() => void>} */
        this.idleWaiters = [];
    }

    /** Läuft noch etwas oder wartet etwas? */
    get busy() {
        return this.running || this.pending.length > 0;
    }

    /** @param {SaveJob} job */
    enqueue(job) {
        if (this.state === "closed") return;
        const index = this.pending.findIndex((p) => p.key === job.key);
        if (index >= 0) {
            this.pending[index] = { ...this.pending[index], ...job };
        } else {
            this.pending.push({ ...job });
        }
        if (this.state === "stalled") this.setState("idle");
        void this.run();
    }

    /**
     * Nach einem Konflikt: mit der Serverversion weitermachen — der Nutzer
     * hat „Meine Eingabe speichern“ gewählt.
     *
     * @param {number} version
     */
    resumeWith(version) {
        if (this.state !== "conflict") return;
        this.version = version;
        this.conflictVersion = null;
        this.setState("idle");
        void this.run();
    }

    /** Nach Netzausfall erneut anstoßen (z. B. Ereignis `online`). */
    resume() {
        if (this.state === "stalled" || this.state === "retrying") {
            this.setState("idle");
            void this.run();
        }
    }

    /** @returns {Promise<void>} erfüllt, sobald nichts mehr läuft oder die Schlange steht */
    whenIdle() {
        if (!this.busy || this.isHalted()) return Promise.resolve();
        return new Promise((resolve) => this.idleWaiters.push(resolve));
    }

    /** Steht die Schlange (Konflikt, abgeschlossen, aufgegeben)? */
    isHalted() {
        return this.state === "conflict" || this.state === "closed" || this.state === "stalled";
    }

    /** @param {SaveState} state */
    setState(state) {
        this.state = state;
        this.onState(state, this);
    }

    async run() {
        if (this.running || this.isHalted()) return;
        this.running = true;
        let attempt = 0;
        try {
            while (this.pending.length > 0 && !this.isHalted()) {
                const job = this.pending[0];
                this.setState(attempt > 0 ? "retrying" : "saving");
                if (this.version === null && this.prepare) {
                    try {
                        this.version = await this.prepare();
                    } catch (_error) {
                        if (!(await this.backoff(attempt++))) break;
                        continue;
                    }
                }
                const outcome = await this.send(job, /** @type {number} */ (this.version ?? 0));
                if (outcome.ok) {
                    attempt = 0;
                    this.version = outcome.version;
                    // Wurde der Auftrag unterwegs durch einen neueren Wert
                    // desselben Felds ersetzt, bleibt der neuere stehen.
                    if (this.pending[0] === job) this.pending.shift();
                    this.onSaved(job, outcome);
                    continue;
                }
                if (outcome.kind === "invalid") {
                    attempt = 0;
                    if (this.pending[0] === job) this.pending.shift();
                    this.onInvalid(job, outcome);
                    continue;
                }
                if (outcome.kind === "conflict") {
                    this.conflictVersion = outcome.version ?? null;
                    this.setState("conflict");
                    break;
                }
                if (outcome.kind === "closed") {
                    this.pending = [];
                    this.setState("closed");
                    break;
                }
                // offline / error: mit Pausen wiederholen, dann aufgeben.
                if (!(await this.backoff(attempt++))) break;
            }
        } finally {
            this.running = false;
            if (!this.isHalted()) this.setState("idle");
            if (!this.busy || this.isHalted()) {
                this.idleWaiters.splice(0).forEach((resolve) => resolve());
            }
        }
    }

    /**
     * @param {number} attempt bisherige Fehlversuche
     * @returns {Promise<boolean>} false: aufgegeben (Zustand `stalled`)
     */
    async backoff(attempt) {
        if (attempt >= this.retryDelays.length) {
            this.setState("stalled");
            return false;
        }
        this.setState("retrying");
        await this.wait(this.retryDelays[attempt]);
        return true;
    }
}
