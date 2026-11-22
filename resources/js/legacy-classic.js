/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : legacy-classic.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Klassische Ansicht des Support-Portals: Die Jahresauswahl schickt das
 * Formular beim Wechsel ab wie im Altportal; ohne Skript bleibt der Knopf.
 */

document.addEventListener("change", (event) => {
    const target = event.target;
    if (target instanceof HTMLSelectElement && target.hasAttribute("data-kp-autosubmit") && target.form) {
        target.form.requestSubmit();
    }
});
