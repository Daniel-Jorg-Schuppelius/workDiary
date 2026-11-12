/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : storage-estimate.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für resources/js/lib/storage-estimate.js.
 *
 * Lauf: npm run test:frontend
 */
import test from "node:test";
import assert from "node:assert/strict";
import { describeBytes, estimateTotalBytes } from "../../resources/js/lib/storage-estimate.js";

test("Gesamtbedarf ist Original plus Fassungen", () => {
    assert.equal(estimateTotalBytes(500 * 1024 * 1024, 1.5), 1250 * 1024 * 1024);
    assert.equal(estimateTotalBytes(1000, 0), 1000);
    assert.equal(estimateTotalBytes(1000, NaN), 1000);
    assert.equal(estimateTotalBytes(0, 1.5), 0);
    assert.equal(estimateTotalBytes(NaN, 1.5), 0);
});

test("Größen werden in der Sprache der Seite beschriftet", () => {
    assert.equal(describeBytes(512, "de"), "512 B");
    assert.equal(describeBytes(1536, "de"), "1,5 KB");
    assert.equal(describeBytes(1250 * 1024 * 1024, "de"), "1,2 GB");
    assert.equal(describeBytes(1536, "en"), "1.5 KB");
});
