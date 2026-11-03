/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-address.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Einsatzort aus der Kundenanschrift im Auftragsdialog: reine
 * Entscheidungslogik ohne DOM, damit sie unter node:test läuft.
 * Die Kundenoption trägt data-address-line/-zip/-city/-country/-lat/-lng.
 */

/** @typedef {{ line: string, zip: string, city: string, country: string, lat: string, lng: string }} CustomerAddress */

export const ADDRESS_FIELDS = /** @type {const} */ (["line", "zip", "city", "country", "lat", "lng"]);

/** Feldname im Formular je Anschriftsteil. */
export const ADDRESS_INPUT_NAMES = /** @type {const} */ ({
    line: "address_line",
    zip: "address_zip",
    city: "address_city",
    country: "address_country",
    lat: "address_lat",
    lng: "address_lng",
});

/**
 * Anschrift aus den data-Attributen der gewählten Kundenoption; null ohne
 * Kunde oder ohne Straße und Ort. Koordinaten zählen nur als Paar.
 * @param {DOMStringMap | Record<string, string | undefined> | undefined} dataset
 * @returns {CustomerAddress | null}
 */
export function customerAddressFromDataset(dataset) {
    if (!dataset) return null;
    /** @param {string} key */
    const read = (key) => String(dataset[key] ?? "").trim();
    const address = {
        line: read("addressLine"),
        zip: read("addressZip"),
        city: read("addressCity"),
        country: read("addressCountry").toUpperCase(),
        lat: read("addressLat"),
        lng: read("addressLng"),
    };
    if (address.line === "" && address.city === "") return null;
    if (address.lat === "" || address.lng === "") {
        address.lat = "";
        address.lng = "";
    }
    return address;
}

/**
 * Entscheidet, ob der Kundenwert die Felder belegen darf: nur wenn Straße,
 * PLZ und Ort leer sind oder noch den zuletzt übernommenen Stand tragen.
 * Eigene Eingaben bleiben stehen; ohne Kunden wird nur Übernommenes geleert.
 * @param {Partial<Record<string, string>>} current aktuelle Feldwerte
 * @param {CustomerAddress | null} incoming Anschrift des gewählten Kunden
 * @param {Partial<Record<string, string>> | null} previous zuletzt übernommene Werte
 * @returns {{ values: Partial<Record<string, string>>, applied: boolean }}
 */
export function mergeCustomerAddress(current, incoming, previous) {
    const untouched = ["line", "zip", "city"].every((key) => {
        const value = (current[key] ?? "").trim();
        return value === "" || value === (previous?.[key] ?? "");
    });
    if (!untouched) return { values: {}, applied: false };
    if (!incoming) {
        if (!previous) return { values: {}, applied: false };
        /** @type {Partial<Record<string, string>>} */
        const cleared = {};
        for (const key of ADDRESS_FIELDS) cleared[key] = "";
        return { values: cleared, applied: true };
    }
    /** @type {Partial<Record<string, string>>} */
    const values = {};
    for (const key of ADDRESS_FIELDS) values[key] = incoming[key];
    return { values, applied: true };
}
