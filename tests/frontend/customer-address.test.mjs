/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-address.test.mjs
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Unit-Tests (node:test) für den Einsatzort aus der Kundenanschrift
 * (resources/js/lib/customer-address.js): Lesen der Option, Belegen leerer
 * oder übernommener Felder, Schutz eigener Eingaben, Leeren ohne Kunden.
 *
 * Lauf: npm run test:frontend
 */

import { test } from "node:test";
import assert from "node:assert/strict";
import { customerAddressFromDataset, mergeCustomerAddress } from "../../resources/js/lib/customer-address.js";

const berlin = {
    addressLine: " Musterweg 7 ",
    addressZip: "12345",
    addressCity: "Musterstadt",
    addressCountry: "de",
    addressLat: "52.5200000",
    addressLng: "13.4050000",
};

test("Option: Anschrift getrimmt, Land in Großbuchstaben, Koordinaten als Paar", () => {
    const address = customerAddressFromDataset(berlin);
    assert.deepEqual(address, { line: "Musterweg 7", zip: "12345", city: "Musterstadt", country: "DE", lat: "52.5200000", lng: "13.4050000" });
    assert.deepEqual(customerAddressFromDataset({ ...berlin, addressLng: "" })?.lat, "");
});

test("Option: ohne Kunde, ohne Straße und Ort → keine Anschrift", () => {
    assert.equal(customerAddressFromDataset(undefined), null);
    assert.equal(customerAddressFromDataset({}), null);
    assert.equal(customerAddressFromDataset({ addressZip: "12345", addressCountry: "DE" }), null);
});

test("Leere Felder werden mit der Kundenanschrift belegt", () => {
    const incoming = customerAddressFromDataset(berlin);
    const { values, applied } = mergeCustomerAddress({ line: "", zip: "", city: "" }, incoming, null);
    assert.equal(applied, true);
    assert.equal(values.line, "Musterweg 7");
    assert.equal(values.lat, "52.5200000");
});

test("Übernommene Werte folgen dem Kundenwechsel, eigene Eingaben bleiben", () => {
    const first = customerAddressFromDataset(berlin);
    const second = customerAddressFromDataset({ ...berlin, addressLine: "Hafenstraße 1", addressCity: "Hamburg", addressLat: "", addressLng: "" });
    const previous = mergeCustomerAddress({ line: "", zip: "", city: "" }, first, null).values;

    const followed = mergeCustomerAddress({ line: "Musterweg 7", zip: "12345", city: "Musterstadt" }, second, previous);
    assert.equal(followed.applied, true);
    assert.equal(followed.values.line, "Hafenstraße 1");
    assert.equal(followed.values.lat, "");

    const own = mergeCustomerAddress({ line: "Baustelle 3", zip: "12345", city: "Musterstadt" }, second, previous);
    assert.equal(own.applied, false);
    assert.deepEqual(own.values, {});
});

test("Ohne Kunden wird nur Übernommenes geleert", () => {
    const previous = mergeCustomerAddress({ line: "", zip: "", city: "" }, customerAddressFromDataset(berlin), null).values;
    const cleared = mergeCustomerAddress({ line: "Musterweg 7", zip: "12345", city: "Musterstadt" }, null, previous);
    assert.equal(cleared.applied, true);
    assert.equal(cleared.values.line, "");
    assert.equal(cleared.values.lng, "");

    const fresh = mergeCustomerAddress({ line: "Eigene Straße 9", zip: "", city: "" }, null, null);
    assert.equal(fresh.applied, false);
});
