/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kiosk.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Kiosk-Modus (MVP-800): Tablet als Stempelterminal. Eigener Entry, nur auf
// der Kiosk-Seite geladen. USB-Leser tippen die Kennung wie eine Tastatur samt
// Enter ins Eingabefeld; auf Android-Chrome kann zusätzlich der NFC-Chip des
// Geräts lesen (Web NFC). Gestempelt wird über denselben Ingest-Endpunkt wie
// ein Hardware-Terminal.
import { postJson } from "./lib/http.js";
import { buildPayload, buildPinPayload, formatBalance, normalizeNfcSerial, statusTone } from "./lib/kiosk.js";

const root = document.querySelector("[data-kiosk]");

if (root instanceof HTMLElement) {
    const ingestUrl = root.dataset.ingestUrl ?? "";
    /** @type {Record<string, string>} */
    const messages = JSON.parse(root.dataset.messages ?? "{}");
    const input = root.querySelector("[data-kiosk-input]");
    const form = root.querySelector("[data-kiosk-form]");
    const result = root.querySelector("[data-kiosk-result]");
    const clock = root.querySelector("[data-kiosk-clock]");
    const nfcButton = root.querySelector("[data-kiosk-nfc]");
    const modeButtons = root.querySelectorAll("[data-kiosk-mode-button]");
    /** @type {"work"|"break"} */
    let mode = "work";
    let busy = false;
    /** @type {ReturnType<typeof setTimeout>|undefined} */
    let clearTimer;

    const focusInput = () => {
        if (input instanceof HTMLInputElement && document.activeElement !== input) {
            input.focus();
        }
    };

    const setMode = (/** @type {string} */ next) => {
        mode = next === "break" ? "break" : "work";
        modeButtons.forEach((button) => {
            if (button instanceof HTMLElement) {
                const active = button.dataset.kioskModeButton === mode;
                button.setAttribute("aria-pressed", active ? "true" : "false");
                button.classList.toggle("btn-primary", active);
            }
        });
    };

    const show = (/** @type {string} */ status, /** @type {Record<string, unknown>} */ payload = {}) => {
        if (!(result instanceof HTMLElement)) {
            return;
        }
        const tone = statusTone(status);
        result.className = `alert alert-${tone} text-lg`;
        result.replaceChildren();

        const line = document.createElement("div");
        const name = typeof payload.employee === "string" ? payload.employee : "";
        line.textContent = (name !== "" ? `${name} — ` : "") + (messages[status] ?? messages.error ?? status);
        result.append(line);

        if (typeof payload.flex_balance_minutes === "number") {
            const balance = document.createElement("div");
            balance.className = "text-sm opacity-80";
            balance.textContent = `${messages.flex_balance ?? ""} ${formatBalance(payload.flex_balance_minutes)}`.trim();
            result.append(balance);
        }

        clearTimeout(clearTimer);
        clearTimer = setTimeout(() => {
            result.className = "";
            result.replaceChildren();
            setMode("work");
        }, 5000);
    };

    const send = async (/** @type {Record<string, string>} */ payload) => {
        if (busy) {
            return;
        }
        busy = true;
        try {
            // Sessionloser Ingest: ein 419 kann nicht auftreten, ein Reload wäre am Terminal falsch.
            const { data } = await postJson(ingestUrl, payload, { on419: "ignore" });
            const answer = data !== null && typeof data === "object" ? data : {};
            show(typeof answer.status === "string" ? answer.status : "error", answer);
        } catch {
            show("network");
        } finally {
            busy = false;
            focusInput();
        }
    };

    const stamp = (/** @type {string} */ badgeUid) => {
        if (badgeUid.trim() !== "") {
            void send(buildPayload(badgeUid, mode, crypto.randomUUID()));
        }
    };

    const pinForm = root.querySelector("[data-kiosk-pin-form]");
    const pinToggle = root.querySelector("[data-kiosk-pin-toggle]");
    pinForm?.addEventListener("submit", (event) => {
        event.preventDefault();
        const personnel = root.querySelector("[data-kiosk-personnel]");
        const pin = root.querySelector("[data-kiosk-pin]");
        if (personnel instanceof HTMLInputElement && pin instanceof HTMLInputElement && personnel.value.trim() !== "" && pin.value !== "") {
            const payload = buildPinPayload(personnel.value, pin.value, mode, crypto.randomUUID());
            personnel.value = "";
            pin.value = "";
            if (pinToggle instanceof HTMLDetailsElement) {
                pinToggle.open = false;
            }
            void send(payload);
        }
    });

    form?.addEventListener("submit", (event) => {
        event.preventDefault();
        if (input instanceof HTMLInputElement) {
            const value = input.value;
            input.value = "";
            void stamp(value);
        }
    });

    modeButtons.forEach((button) => {
        button.addEventListener("click", () => {
            if (button instanceof HTMLElement) {
                setMode(button.dataset.kioskModeButton ?? "work");
            }
            focusInput();
        });
    });

    // Der Leser tippt blind: Das Feld muss immer den Fokus haben — außer während der PIN-Eingabe.
    input?.addEventListener("blur", () => setTimeout(() => {
        if (!(pinToggle instanceof HTMLDetailsElement) || !pinToggle.open) {
            focusInput();
        }
    }, 150));
    focusInput();

    if (clock instanceof HTMLElement) {
        const tick = () => {
            clock.textContent = new Date().toLocaleTimeString(document.documentElement.lang || undefined, { hour: "2-digit", minute: "2-digit" });
        };
        tick();
        setInterval(tick, 1000);
    }

    // Web NFC nur, wo der Browser es kann (Android-Chrome, sicherer Kontext).
    // Der Scan braucht eine Nutzergeste, deshalb ein eigener Knopf.
    if (nfcButton instanceof HTMLButtonElement && "NDEFReader" in window) {
        nfcButton.classList.remove("hidden");
        nfcButton.addEventListener("click", async () => {
            try {
                // NDEFReader fehlt in den DOM-Typen der TypeScript-Prüfung.
                const NdefReader = /** @type {any} */ (window).NDEFReader;
                const reader = new NdefReader();
                await reader.scan();
                nfcButton.disabled = true;
                nfcButton.textContent = messages.nfc_active ?? nfcButton.textContent;
                reader.addEventListener("reading", (/** @type {any} */ event) => {
                    void stamp(normalizeNfcSerial(event.serialNumber));
                });
            } catch {
                show("nfc_unavailable");
            }
        });
    }
}
