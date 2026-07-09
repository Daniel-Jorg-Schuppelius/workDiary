/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : header-autohide.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Auto-Hide-Header für kleine Viewports (Feature 037/004, MVP-182):
 * Beim Runterscrollen blendet der Sticky-Header aus, beim Hochscrollen
 * (oder am Seitenanfang) wieder ein — schafft auf Mobilgeräten und im
 * Smartphone-Querformat nutzbare Höhe.
 *
 * WICHTIG: Auf App-Shell-Seiten scrollt NICHT window, sondern der
 * innere Container `.wd-page-fill > main` — die Richtungs-Erkennung
 * hängt sich an beide Quellen. Das Ausblenden setzt `.wd-header-hidden`
 * auf <html>; das Header-Mess-Skript im Layout meldet dann 0 für
 * --app-header-h, wodurch wd-page-fill die Höhe sofort nutzt.
 * Fokus im Header (offene Menüs) verhindert das Ausblenden.
 */
const MEDIA = "(max-width: 767px), (orientation: landscape) and (max-height: 480px)";
const SHOW_AT_TOP = 60; // px: oberhalb immer sichtbar
const DELTA = 8; // px: Mindestbewegung gegen Zitter-Toggling

function initHeaderAutoHide() {
    const header = document.getElementById("app-header");
    if (!header) {
        return;
    }
    const root = document.documentElement;
    const media = window.matchMedia(MEDIA);
    let lastTop = 0;

    const setHidden = (hidden) => {
        if (root.classList.contains("wd-header-hidden") === hidden) {
            return;
        }
        if (hidden && header.contains(document.activeElement)) {
            return; // offenes Menü/Fokus im Header nicht wegziehen
        }
        root.classList.toggle("wd-header-hidden", hidden);
        // Mess-Skript neu ausführen lassen (liest die Klasse und schreibt
        // --app-header-h entsprechend 0 bzw. gemessene Höhe).
        window.dispatchEvent(new Event("resize"));
    };

    const onScroll = (getTop) => () => {
        if (!media.matches) {
            setHidden(false);
            return;
        }
        const top = getTop();
        if (top <= SHOW_AT_TOP) {
            setHidden(false);
        } else if (top > lastTop + DELTA) {
            setHidden(true);
        } else if (top < lastTop - DELTA) {
            setHidden(false);
        }
        lastTop = top;
    };

    // Quelle 1: window/body (Mobil-Escape-Layout scrollt natürlich).
    window.addEventListener("scroll", onScroll(() => window.scrollY), { passive: true });

    // Quelle 2: innerer App-Shell-Scroller.
    const main = document.querySelector(".wd-page-fill > main");
    if (main) {
        main.addEventListener("scroll", onScroll(() => main.scrollTop), { passive: true });
    }

    // Verlassen des kleinen Viewports (Rotation/Resize) → Header zeigen.
    media.addEventListener?.("change", (event) => {
        if (!event.matches) {
            setHidden(false);
        }
    });
}

document.addEventListener("DOMContentLoaded", initHeaderAutoHide);

export { initHeaderAutoHide };
