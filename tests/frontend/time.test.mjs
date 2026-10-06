/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für resources/js/lib/time.js.
 *
 * Lauf: npm run test:frontend
 */
import test from "node:test";
import assert from "node:assert/strict";
import { toMinutes } from "../../resources/js/lib/time.js";

test("toMinutes liest h:mm", () => {
    assert.equal(toMinutes("1:30"), 90);
    assert.equal(toMinutes("0:05"), 5);
    assert.equal(toMinutes("12:00"), 720);
});

test("toMinutes weist andere Formen ab", () => {
    assert.equal(toMinutes(""), null);
    assert.equal(toMinutes(null), null);
    assert.equal(toMinutes("90"), null);
    assert.equal(toMinutes("1:60"), null);
    assert.equal(toMinutes("1:-5"), null);
    assert.equal(toMinutes("a:b"), null);
    assert.equal(toMinutes("1:30:00"), null);
});
