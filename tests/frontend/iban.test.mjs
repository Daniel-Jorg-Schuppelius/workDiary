/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : iban.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für die IBAN-Sofortprüfung in Formularen
 * (resources/js/lib/iban.js).
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { isValidIban, normalizeIban } from "../../resources/js/lib/iban.js";

test("gültige IBANs, auch mit Leerzeichen und klein geschrieben", () => {
    assert.ok(isValidIban("DE89370400440532013000"));
    assert.ok(isValidIban("DE02120300000000202051"));
    assert.ok(isValidIban(" de89 3704 0044 0532 0130 00 "));
    assert.ok(isValidIban("GB29NWBK60161331926819"));
    assert.ok(isValidIban("AT611904300234573201"));
});

test("Zahlendreher und falsche Prüfziffer fallen auf", () => {
    assert.equal(isValidIban("DE89370400440532013001"), false);
    assert.equal(isValidIban("DE89370400440532031000"), false);
    assert.equal(isValidIban("DE98370400440532013000"), false);
});

test("eine Stelle zu viel fällt auf (Realfall)", () => {
    assert.equal(isValidIban("DE791005000001068540253"), false);
});

test("leere Eingabe ist kein Fehler", () => {
    assert.ok(isValidIban(""));
    assert.ok(isValidIban("   "));
});

test("Unfug wird abgelehnt", () => {
    assert.equal(isValidIban("NOT-AN-IBAN"), false);
    assert.equal(isValidIban("DE00"), false);
});

test("normalisiert Leerraum und Schreibweise", () => {
    assert.equal(normalizeIban(" de89 3704\t0044 "), "DE8937040044");
});
