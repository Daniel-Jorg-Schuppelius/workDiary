/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : video-coverage.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für resources/js/lib/video-coverage.js.
 *
 * Lauf: npm run test:frontend
 */
import test from "node:test";
import assert from "node:assert/strict";
import { addInterval, coveredSeconds, isContinuous, parseIntervals } from "../../resources/js/lib/video-coverage.js";

test("Abschnitte verschmelzen und zählen nur einmal", () => {
    let intervals = addInterval([], 0, 10);
    intervals = addInterval(intervals, 5, 15);
    intervals = addInterval(intervals, 40, 50);
    assert.deepEqual(intervals, [[0, 15], [40, 50]]);
    assert.equal(coveredSeconds(intervals), 25);

    // Dieselbe Stelle noch einmal sehen bringt nichts dazu.
    assert.equal(coveredSeconds(addInterval(intervals, 2, 12)), 25);
    // Die Lücke schließen verbindet beide Teile.
    assert.deepEqual(addInterval(intervals, 15, 40), [[0, 50]]);
});

test("leere und verdrehte Abschnitte werden verworfen", () => {
    assert.deepEqual(addInterval([[0, 5]], 9, 9), [[0, 5]]);
    assert.deepEqual(addInterval([[0, 5]], 9, 7), [[0, 5]]);
    assert.deepEqual(addInterval([[0, 5]], NaN, 7), [[0, 5]]);
});

test("Vorspulen und Zurückspringen gelten nicht als Wiedergabe", () => {
    assert.equal(isContinuous(10, 10.25, 1), true);
    assert.equal(isContinuous(10, 12.9, 2), true);
    assert.equal(isContinuous(10, 95, 1), false);
    assert.equal(isContinuous(10, 4, 1), false);
    assert.equal(isContinuous(null, 4, 1), false);
    assert.equal(isContinuous(10, 10, 1), false);
});

test("gespeicherte Abschnitte werden geprüft gelesen", () => {
    assert.deepEqual(parseIntervals("[[0,4],[3,9],[20,21]]"), [[0, 9], [20, 21]]);
    assert.deepEqual(parseIntervals("{\"a\":1}"), []);
    assert.deepEqual(parseIntervals("kaputt"), []);
    assert.deepEqual(parseIntervals(null), []);
    assert.deepEqual(parseIntervals("[[5,1],[\"x\",2],[1]]"), []);
});
