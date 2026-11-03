/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : option-prefill.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für die Vorbelegung aus der Elternauswahl
 * (resources/js/lib/option-prefill.js): Lesen der Option, Belegen leerer oder
 * übernommener Felder, Schutz eigener Eingaben, Leeren ohne Elternwert; dazu
 * die Adressregeln des Auftragsdialogs.
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { customerAddressFromDataset, mergePrefill, valuesFromDataset } from "../../resources/js/lib/option-prefill.js";

const berlin = {
    addressLine: " Musterweg 7 ",
    addressZip: "12345",
    addressCity: "Musterstadt",
    addressCountry: "de",
    addressLat: "52.5200000",
    addressLng: "13.4050000",
};

test("Option: Werte je Feld getrimmt, null ohne Option oder ohne jeden Wert", () => {
    const keys = { location: "prefillLocation", country: "prefillCountry" };
    assert.deepEqual(valuesFromDataset({ prefillLocation: " Musterstadt ", prefillCountry: "DE" }, keys), { location: "Musterstadt", country: "DE" });
    assert.deepEqual(valuesFromDataset({ prefillLocation: "Musterstadt" }, keys), { location: "Musterstadt", country: "" });
    assert.equal(valuesFromDataset({ prefillLocation: " " }, keys), null);
    assert.equal(valuesFromDataset(undefined, keys), null);
});

test("Leere Felder werden belegt, eigene Eingaben bleiben, Übernommenes folgt dem Wechsel", () => {
    const first = mergePrefill({ location: "", country: "" }, { location: "Musterstadt", country: "DE" }, null);
    assert.deepEqual(first, { values: { location: "Musterstadt", country: "DE" }, applied: true });

    const followed = mergePrefill({ location: "Musterstadt", country: "DE" }, { location: "Hamburg", country: "DE" }, first.values);
    assert.equal(followed.values.location, "Hamburg");

    const own = mergePrefill({ location: "Baustelle 3", country: "DE" }, { location: "Hamburg", country: "DE" }, first.values);
    assert.deepEqual(own, { values: {}, applied: false });
});

test("Wächterfelder: nur sie entscheiden, die übrigen folgen", () => {
    const current = { street: "", city: "", lat: "eigene" };
    const result = mergePrefill(current, { street: "Musterweg 7", city: "Musterstadt", lat: "52.5" }, null, ["street", "city"]);
    assert.equal(result.applied, true);
    assert.equal(result.values.lat, "52.5");
});

test("Ohne Elternwert wird nur Übernommenes geleert", () => {
    const previous = mergePrefill({ location: "" }, { location: "Musterstadt" }, null).values;
    assert.deepEqual(mergePrefill({ location: "Musterstadt" }, null, previous), { values: { location: "" }, applied: true });
    assert.deepEqual(mergePrefill({ location: "Eigene Straße 9" }, null, null), { values: {}, applied: false });
});

test("Adresse: getrimmt, Land in Großbuchstaben, Koordinaten als Paar", () => {
    const address = customerAddressFromDataset(berlin);
    assert.deepEqual(address, { line: "Musterweg 7", zip: "12345", city: "Musterstadt", country: "DE", lat: "52.5200000", lng: "13.4050000" });
    assert.deepEqual(customerAddressFromDataset({ ...berlin, addressLng: "" })?.lat, "");
});

test("Adresse: ohne Kunde, ohne Straße und Ort → keine Anschrift", () => {
    assert.equal(customerAddressFromDataset(undefined), null);
    assert.equal(customerAddressFromDataset({}), null);
    assert.equal(customerAddressFromDataset({ addressZip: "12345", addressCountry: "DE" }), null);
});

test("Adresse: Übernommene Werte folgen dem Kundenwechsel, eigene Eingaben bleiben", () => {
    const guard = ["line", "zip", "city"];
    const first = customerAddressFromDataset(berlin);
    const second = customerAddressFromDataset({ ...berlin, addressLine: "Hafenstraße 1", addressCity: "Hamburg", addressLat: "", addressLng: "" });
    const previous = mergePrefill({ line: "", zip: "", city: "" }, first, null, guard).values;

    const followed = mergePrefill({ line: "Musterweg 7", zip: "12345", city: "Musterstadt" }, second, previous, guard);
    assert.equal(followed.applied, true);
    assert.equal(followed.values.line, "Hafenstraße 1");
    assert.equal(followed.values.lat, "");

    const own = mergePrefill({ line: "Baustelle 3", zip: "12345", city: "Musterstadt" }, second, previous, guard);
    assert.equal(own.applied, false);

    const cleared = mergePrefill({ line: "Musterweg 7", zip: "12345", city: "Musterstadt" }, null, previous, guard);
    assert.equal(cleared.applied, true);
    assert.equal(cleared.values.lng, "");
});
