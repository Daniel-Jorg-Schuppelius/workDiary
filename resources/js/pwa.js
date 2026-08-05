/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Filename     : pwa.js
 * License      : AGPL-3.0-or-later
 *
 * Registriert den Service Worker für PWA-Funktionalität
 * (App-Shell-Cache, Offline-Seite) und steuert einen optionalen
 * Installations-Prompt (beforeinstallprompt).
 */

const SW_VERSION =
    /** @type {HTMLMetaElement} */ (
        document.querySelector('meta[name="app-version"]')
    )?.content || "1";

export async function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return null;
    try {
        return await navigator.serviceWorker.register(
            `/sw.js?v=${encodeURIComponent(SW_VERSION)}`,
        );
    } catch (e) {
        console.warn("[PWA] Service Worker konnte nicht registriert werden", e);
        return null;
    }
}

export function bindInstallPrompt() {
    const btn = /** @type {HTMLElement} */ (
        document.querySelector("[data-pwa-install]")
    );

    // Ohne Install-Button: NICHT preventDefault aufrufen, sonst meckert Chrome
    // ("Banner not shown: beforeinstallpromptevent.preventDefault() called.").
    // Der Browser zeigt dann seinen eigenen Install-Hinweis (z. B. in der Adressleiste).
    if (!btn) {
        return;
    }

    let deferredPrompt = null;

    window.addEventListener("beforeinstallprompt", (e) => {
        e.preventDefault();
        deferredPrompt = e;
        btn.hidden = false;
    });

    btn.addEventListener("click", async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        btn.hidden = true;
    });

    window.addEventListener("appinstalled", () => {
        btn.hidden = true;
    });
}
