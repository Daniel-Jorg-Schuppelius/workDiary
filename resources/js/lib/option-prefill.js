/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : option-prefill.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Vorbelegung aus der Elternauswahl: reine Entscheidungslogik ohne DOM, damit
 * sie unter node:test läuft. Die gewählte <option> trägt die Werte als
 * data-Attribute; belegt werden nur Felder, die leer sind oder noch den zuletzt
 * übernommenen Stand tragen. Nutzer: Auftragsdialog (Alpine) und der Binder
 * `data-prefill-from` in app.js (Objekt, Fahrtenbuch, Reisekosten,
 * Baustellenanzeige, Vermietung).
 */

/**
 * Werte aus dem Dataset einer Option: je Feld der getrimmte Wert des
 * zugeordneten Dataset-Schlüssels; null ohne Option oder wenn alle Werte leer sind.
 * @param {DOMStringMap | Record<string, string | undefined> | undefined} dataset
 * @param {Record<string, string>} keys Feld → Dataset-Schlüssel (camelCase)
 * @returns {Record<string, string> | null}
 */
export function valuesFromDataset(dataset, keys) {
    if (!dataset) return null;
    /** @type {Record<string, string>} */
    const values = {};
    let any = false;
    for (const [field, key] of Object.entries(keys)) {
        values[field] = String(dataset[key] ?? "").trim();
        if (values[field] !== "") any = true;
    }
    return any ? values : null;
}

/**
 * Entscheidet, ob der Elternwert die Felder belegen darf: nur wenn die
 * Wächterfelder leer sind oder noch den zuletzt übernommenen Stand tragen.
 * Eigene Eingaben bleiben stehen; ohne Elternwert wird nur Übernommenes geleert.
 * @param {Partial<Record<string, string>>} current aktuelle Feldwerte
 * @param {Record<string, string> | null} incoming Werte der gewählten Option
 * @param {Partial<Record<string, string>> | null} previous zuletzt übernommene Werte
 * @param {readonly string[]} [guard] Wächterfelder, Vorgabe: alle aktuellen Felder
 * @returns {{ values: Partial<Record<string, string>>, applied: boolean }}
 */
export function mergePrefill(current, incoming, previous, guard = Object.keys(current)) {
    const untouched = guard.every((key) => {
        const value = (current[key] ?? "").trim();
        return value === "" || value === (previous?.[key] ?? "");
    });
    if (!untouched) return { values: {}, applied: false };
    if (!incoming) {
        if (!previous) return { values: {}, applied: false };
        /** @type {Partial<Record<string, string>>} */
        const cleared = {};
        for (const key of Object.keys(previous)) cleared[key] = "";
        return { values: cleared, applied: true };
    }
    return { values: { ...incoming }, applied: true };
}

/** @typedef {{ line: string, zip: string, city: string, country: string, lat: string, lng: string }} CustomerAddress */

export const ADDRESS_FIELDS = /** @type {const} */ (["line", "zip", "city", "country", "lat", "lng"]);

/** Feldname im Auftragsdialog je Anschriftsteil. */
export const ADDRESS_INPUT_NAMES = /** @type {const} */ ({
    line: "address_line",
    zip: "address_zip",
    city: "address_city",
    country: "address_country",
    lat: "address_lat",
    lng: "address_lng",
});

/**
 * Einsatzort aus den data-address-*-Attributen der Kundenoption; null ohne
 * Kunde oder ohne Straße und Ort. Land in Großbuchstaben, Koordinaten nur als Paar.
 * @param {DOMStringMap | Record<string, string | undefined> | undefined} dataset
 * @returns {CustomerAddress | null}
 */
export function customerAddressFromDataset(dataset) {
    const values = valuesFromDataset(dataset, {
        line: "addressLine",
        zip: "addressZip",
        city: "addressCity",
        country: "addressCountry",
        lat: "addressLat",
        lng: "addressLng",
    });
    if (!values || (values.line === "" && values.city === "")) return null;
    const address = /** @type {CustomerAddress} */ (values);
    address.country = address.country.toUpperCase();
    if (address.lat === "" || address.lng === "") {
        address.lat = "";
        address.lng = "";
    }
    return address;
}
