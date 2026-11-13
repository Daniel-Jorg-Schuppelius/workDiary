/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : file-drop.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für resources/js/lib/file-drop.js (Feature 204).
 *
 * Lauf: npm run test:frontend
 */
import test from "node:test";
import assert from "node:assert/strict";
import { carriesFiles, singleDroppedFile } from "../../resources/js/lib/file-drop.js";

test("carriesFiles erkennt Dateien, nicht Text", () => {
    assert.equal(carriesFiles({ types: ["Files"] }), true);
    assert.equal(carriesFiles({ types: ["text/plain", "Files"] }), true);
    assert.equal(carriesFiles({ types: ["text/plain"] }), false);
    assert.equal(carriesFiles({ types: null }), false);
    assert.equal(carriesFiles(null), false);
});

test("singleDroppedFile nimmt genau eine Datei", () => {
    assert.equal(singleDroppedFile(["anfrage.msg"]), "anfrage.msg");
    assert.equal(singleDroppedFile(["a.msg", "b.eml"]), null);
    assert.equal(singleDroppedFile([]), null);
    assert.equal(singleDroppedFile(null), null);
});
