/*
 * oauth-popup.js — öffnet Plugin-OAuth-Connect-Formulare (Kalender, Mail,
 * Kontakte, Drive, …) in einem Popup-Fenster statt per Ganzseiten-Redirect.
 *
 * Das Formular bleibt ein normales POST-Formular (CSRF!). Nur wenn das Popup
 * wirklich aufgeht, wird `popup=1` mitgesendet und das Submit-Ziel auf das
 * Popup gelenkt. Ist der Popup-Blocker aktiv, fällt der Klick ohne Umwege auf
 * den bisherigen Ganzseiten-Flow zurück. Die Callback-Seite meldet das
 * Ergebnis via postMessage (streng origin-geprüft) zurück; wir schließen das
 * Popup und laden die Übersicht neu, damit der Session-Flash erscheint.
 *
 * CSP-konform: reine Event-Delegation auf document, keine Inline-Handler.
 */

const POPUP_NAME = "workdiaryOAuth";
const POPUP_MESSAGE_SOURCE = "workdiary-oauth";

/** @type {Window | null} */
let activePopup = null;

function openNamedPopup() {
    const width = 520;
    const height = 640;
    const left = window.screenX + Math.max(0, (window.outerWidth - width) / 2);
    const top = window.screenY + Math.max(0, (window.outerHeight - height) / 2);
    const features = `popup=1,noopener=no,width=${width},height=${height},left=${Math.round(
        left,
    )},top=${Math.round(top)}`;
    return window.open("about:blank", POPUP_NAME, features);
}

document.addEventListener("submit", (event) => {
    const form =
        event.target instanceof HTMLFormElement ? event.target : null;
    if (!form || !form.matches("form[data-oauth-popup]")) return;

    const popup = openNamedPopup();
    if (!popup) return; // Popup blockiert → nativer Ganzseiten-Flow.

    event.preventDefault();
    activePopup = popup;
    try {
        popup.focus();
    } catch (e) {
        /* Fokus ist optional. */
    }

    // Popup-Flag nur für genau diesen Submit anhängen …
    const flag = document.createElement("input");
    flag.type = "hidden";
    flag.name = "popup";
    flag.value = "1";
    form.appendChild(flag);

    const previousTarget = form.target;
    form.target = POPUP_NAME;
    // form.submit() serialisiert den Body synchron — Aufräumen danach ist sicher.
    form.submit();
    form.target = previousTarget;
    if (flag.parentNode) flag.parentNode.removeChild(flag);
});

window.addEventListener("message", (event) => {
    if (event.origin !== window.location.origin) return;
    const data = event.data;
    if (!data || data.source !== POPUP_MESSAGE_SOURCE) return;

    if (activePopup && !activePopup.closed) {
        try {
            activePopup.close();
        } catch (e) {
            /* Popup schließt sich selbst. */
        }
    }
    activePopup = null;
    window.location.reload();
});
