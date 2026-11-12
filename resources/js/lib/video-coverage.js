/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : video-coverage.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Gesehene Abschnitte eines Videos (Feature 149, MVP-1598): Intervalle in
 * Sekunden, überlappungsfrei gehalten. Gezählt wird die Fläche, nicht die
 * Abspielposition — ein Sprung nach vorn füllt nichts.
 */

/** @typedef {[number, number]} Interval */

/**
 * Abschnitt aufnehmen und mit Nachbarn verschmelzen.
 *
 * @param {Interval[]} intervals sortiert, überlappungsfrei
 * @param {number} from
 * @param {number} to
 * @returns {Interval[]}
 */
export function addInterval(intervals, from, to) {
    if (!Number.isFinite(from) || !Number.isFinite(to) || to <= from) {
        return intervals;
    }
    /** @type {Interval[]} */
    const merged = [];
    let start = from;
    let end = to;
    for (const [a, b] of intervals) {
        if (b < start || a > end) {
            merged.push([a, b]);
        } else {
            start = Math.min(start, a);
            end = Math.max(end, b);
        }
    }
    merged.push([start, end]);
    merged.sort((x, y) => x[0] - y[0]);
    return merged;
}

/**
 * @param {Interval[]} intervals
 * @returns {number} gesehene Sekunden
 */
export function coveredSeconds(intervals) {
    let sum = 0;
    for (const [a, b] of intervals) sum += b - a;
    return sum;
}

/**
 * Liegt zwischen zwei Zeitmarken echte Wiedergabe? Ein Sprung (vor oder
 * zurück) und ein hängender Player zählen nicht.
 *
 * @param {number|null} last
 * @param {number} now
 * @param {number} rate Wiedergabegeschwindigkeit
 * @returns {boolean}
 */
export function isContinuous(last, now, rate) {
    if (last === null || !Number.isFinite(now)) return false;
    const step = now - last;
    // timeupdate kommt mindestens viermal je Sekunde; 1,5 s Spielraum je Tempo.
    return step > 0 && step <= 1.5 * Math.max(1, rate || 1);
}

/**
 * Gespeicherte Abschnitte lesen — fremde oder beschädigte Werte ergeben eine
 * leere Liste.
 *
 * @param {string|null} raw
 * @returns {Interval[]}
 */
export function parseIntervals(raw) {
    if (!raw) return [];
    try {
        /** @type {unknown} */
        const data = JSON.parse(raw);
        if (!Array.isArray(data)) return [];
        /** @type {Interval[]} */
        let intervals = [];
        for (const item of data) {
            if (Array.isArray(item) && item.length === 2) {
                intervals = addInterval(intervals, Number(item[0]), Number(item[1]));
            }
        }
        return intervals;
    } catch {
        return [];
    }
}
