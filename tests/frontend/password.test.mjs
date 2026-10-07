/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : password.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für den Passwort-Vorschlag im Mitarbeiter-Dialog
 * (resources/js/lib/password.js).
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { PASSWORD_CLASSES, suggestPassword } from "../../resources/js/lib/password.js";

test("enthält jede Zeichenklasse und hat die verlangte Länge", () => {
    for (let run = 0; run < 200; run++) {
        const password = suggestPassword(16);
        assert.equal(password.length, 16);
        for (const set of PASSWORD_CLASSES) {
            assert.ok([...password].some((c) => set.includes(c)), `${password} ohne ${set}`);
        }
    }
});

test("erfüllt die Mindestlänge von Password::defaults()", () => {
    assert.ok(suggestPassword().length >= 12);
});

test("zu kurze Länge wird auf die Zahl der Klassen angehoben", () => {
    assert.equal(suggestPassword(2).length, PASSWORD_CLASSES.length);
});

test("nutzt keine verwechselbaren Zeichen", () => {
    for (let run = 0; run < 200; run++) {
        assert.doesNotMatch(suggestPassword(32), /[0O1lI]/);
    }
});

test("verwirft Zufallswerte oberhalb der gleichverteilten Grenze", () => {
    const values = [0xffffffff, 0];
    let calls = 0;
    const fill = (buffer) => {
        buffer[0] = values[Math.min(calls++, values.length - 1)];
        return buffer;
    };
    const password = suggestPassword(4, fill);
    assert.equal(password.length, 4);
    assert.ok(calls > 4);
});
