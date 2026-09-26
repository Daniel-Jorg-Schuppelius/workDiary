/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : nfc.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * NFC-Scan (MVP-903) über Web NFC (Chrome auf Android). Knöpfe mit
 * `data-nfc-read="<Selektor des Eingabefelds>"` lesen einen Tag und tragen
 * den ersten URL- oder Textdatensatz ein; mit `data-nfc-submit` wird das
 * Formular danach abgeschickt. `data-nfc-write="<url>"` beschreibt einen Tag
 * mit dem Objekt-Link (derselbe Link wie im QR-Etikett). Ohne Web NFC bleiben
 * die Knöpfe verborgen, der QR-/Barcode-Weg gilt weiter.
 *
 * CSP-konform: Event-Delegation, keine Inline-Handler.
 */

import { __ } from "./i18n.js";
import { recordText } from "./lib/nfc.js";

const supported = typeof window !== "undefined" && "NDEFReader" in window;

function status(button, text) {
    const target = button.closest("form, [data-nfc-scope]")?.querySelector("[data-nfc-status]");
    if (target) target.textContent = text;
}

async function read(button) {
    const input = document.querySelector(button.dataset.nfcRead);
    if (!(input instanceof HTMLInputElement)) return;

    const controller = new AbortController();
    status(button, __("js.nfc.hold"));
    try {
        const reader = new window.NDEFReader();
        await reader.scan({ signal: controller.signal });
        reader.addEventListener(
            "reading",
            (event) => {
                controller.abort();
                const value = recordText(event.message);
                if (value === null) {
                    status(button, __("js.nfc.empty"));
                    return;
                }
                input.value = value;
                status(button, "");
                if (button.hasAttribute("data-nfc-submit")) {
                    input.form?.requestSubmit();
                }
            },
            { once: true },
        );
        reader.addEventListener("readingerror", () => status(button, __("js.nfc.failed")), { once: true });
    } catch (_) {
        status(button, __("js.nfc.failed"));
    }
}

async function write(button) {
    status(button, __("js.nfc.hold"));
    try {
        await new window.NDEFReader().write({ records: [{ recordType: "url", data: button.dataset.nfcWrite }] });
        status(button, __("js.nfc.written"));
    } catch (_) {
        status(button, __("js.nfc.failed"));
    }
}

export function initNfc() {
    if (!supported) return;
    for (const button of document.querySelectorAll("[data-nfc-read], [data-nfc-write]")) {
        button.classList.remove("hidden");
    }
    document.addEventListener("click", (event) => {
        const button = event.target instanceof Element ? event.target.closest("[data-nfc-read], [data-nfc-write]") : null;
        if (!button) return;
        event.preventDefault();
        if (button.hasAttribute("data-nfc-read")) {
            read(button);
        } else {
            write(button);
        }
    });
}
