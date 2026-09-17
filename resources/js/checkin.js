/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : checkin.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// QR-/NFC-Check-in mit Ortsprüfung (MVP-800): Nur geladen, wenn der Punkt einen
// Radius hat. Die Position wird genau einmal beim Absenden abgefragt und dient
// serverseitig allein der Radiusprüfung.
const form = document.querySelector("form[data-checkin-requires-location]");

if (form instanceof HTMLFormElement) {
    const latitude = form.querySelector("[data-checkin-latitude]");
    const longitude = form.querySelector("[data-checkin-longitude]");
    const error = form.querySelector("[data-checkin-location-error]");
    let located = false;

    form.addEventListener("submit", (event) => {
        if (located) {
            return;
        }
        event.preventDefault();
        error?.classList.add("hidden");

        if (!("geolocation" in navigator)) {
            error?.classList.remove("hidden");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                if (latitude instanceof HTMLInputElement && longitude instanceof HTMLInputElement) {
                    latitude.value = String(position.coords.latitude);
                    longitude.value = String(position.coords.longitude);
                }
                located = true;
                form.requestSubmit();
            },
            () => error?.classList.remove("hidden"),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
        );
    });
}
