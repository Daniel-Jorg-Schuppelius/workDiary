/* Push Service Worker + minimaler Offline-Fallback (Feature 035, MVP-368)
 *
 * Abwägung (ersetzt das frühere „bewusst KEIN fetch-Handler"): Der Handler
 * greift AUSSCHLIESSLICH bei Navigationen und arbeitet strikt network-first —
 * online kommt also weiterhin IMMER das frische Laravel-Rendering (Header
 * inkl. Org-Switch, Sprache, Theme, Benutzermenü) unverändert beim Browser
 * an. Erst wenn das Netz fehlt, wird das beim install vorgecachte
 * offline.html geliefert. Authentifizierte Seiten werden NIE gecacht;
 * Assets/XHR laufen unverändert am SW vorbei (Schreibpfade gehen explizit
 * über die IndexedDB-Outbox, resources/js/offline-sync.js).
 */
const OFFLINE_CACHE = "workdiary-offline-v3";
// Offline-Leser der Krisenmappe (MVP-914), mit offline.html vorgecacht.
const OFFLINE_READER = "/offline-reader.js";
const OFFLINE_URL = "/offline.html";

// lib.webworker kennt nur WorkerGlobalScope; der Cast macht die SW-Ereignisse typisiert.
const sw = /** @type {ServiceWorkerGlobalScope} */ (/** @type {unknown} */ (self));

sw.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(OFFLINE_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL, OFFLINE_READER]))
            .then(() => sw.skipWaiting()),
    );
});

sw.addEventListener("activate", (event) => {
    // Caches früherer Iterationen aufräumen (nur der aktuelle Offline-Cache
    // bleibt) und sofort die Kontrolle über alle offenen Tabs übernehmen.
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== OFFLINE_CACHE).map((k) => caches.delete(k))))
            .then(() => sw.clients.claim()),
    );
});

sw.addEventListener("fetch", (event) => {
    // Das Leseskript der Offline-Seite kommt ohne Netz aus dem Cache.
    if (new URL(event.request.url).pathname === OFFLINE_READER) {
        event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_READER).then((cached) => cached || Response.error())));
        return;
    }
    if (event.request.mode !== "navigate") return;

    // `.well-known` nie aus dem Offline-Cache bedienen (CRA-Tabletop
    // 2026-09-01): Der CVD-Meldekanal soll den Zustand des Servers zeigen,
    // nicht den des Browsers. Sonst sieht ein Sicherheitsforscher, dessen
    // Verbindung kurz hakt, unsere Offline-Seite statt einer Kontaktadresse —
    // und hält den Kanal für tot.
    const path = new URL(event.request.url).pathname;
    if (path.startsWith("/.well-known/") || path === "/security.txt") return;

    event.respondWith(
        fetch(event.request).catch(() =>
            caches
                .match(OFFLINE_URL)
                .then((cached) => cached || Response.error()),
        ),
    );
});

sw.addEventListener("message", (event) => {
    if (event.data === "SKIP_WAITING") sw.skipWaiting();
});

sw.addEventListener("push", (event) => {
    /** @type {{ title?: string, body?: string, icon?: string, tag?: string, url?: string }} */
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (_) {
        data = {
            title: "Workdiary",
            body: event.data ? event.data.text() : "",
        };
    }
    const title = data.title || "Workdiary";
    const options = {
        body: data.body || "",
        icon: data.icon || "/favicon.ico",
        tag: data.tag || undefined,
        data: { url: data.url || "/" },
    };
    event.waitUntil(sw.registration.showNotification(title, options));
});

sw.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || "/";
    event.waitUntil(
        sw.clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then((list) => {
                for (const c of list) {
                    if ("focus" in c) {
                        c.navigate(url);
                        return c.focus();
                    }
                }
                if (sw.clients.openWindow) return sw.clients.openWindow(url);
            }),
    );
});
