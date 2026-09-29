/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : icon-font.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Die verkleinerte Icon-Schrift (resources/fonts) muss aus der installierten
 * Paketversion erzeugt sein; nach einem Update von `material-symbols`
 * `python3 scripts/build-icon-font.py` ausführen.
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { readFileSync } from "node:fs";

const root = new URL("../../", import.meta.url);
const read = (path) => readFileSync(new URL(path, root));
const manifest = JSON.parse(read("resources/fonts/material-symbols-outlined.json"));

test("Icon-Schrift ist aus der installierten Paketdatei erzeugt", () => {
    const installed = JSON.parse(read("node_modules/material-symbols/package.json")).version;
    const sha = createHash("sha256").update(read("node_modules/material-symbols/material-symbols-outlined.woff2")).digest("hex");
    assert.equal(manifest.version, installed, "python3 scripts/build-icon-font.py ausführen");
    assert.equal(manifest.source_sha256, sha, "python3 scripts/build-icon-font.py ausführen");
});

test("app.css bindet die verkleinerte Schrift mit passendem Gewichtsbereich ein", () => {
    const css = read("resources/css/app.css").toString();
    assert.doesNotMatch(css, /@import\s+["']material-symbols\//, "Paket-CSS lädt die 3,8-MB-Datei");
    assert.match(css, /url\("\.\.\/fonts\/material-symbols-outlined\.woff2"\)/);
    assert.match(css, new RegExp(`font-weight:\\s*${manifest.limits.wght.join(" ")};`));
});
