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
const OFFLINE_CACHE = "workdiary-offline-v1";
const OFFLINE_URL = "/offline.html";

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(OFFLINE_CACHE)
            .then((cache) => cache.add(OFFLINE_URL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener("activate", (event) => {
    // Caches früherer Iterationen aufräumen (nur der aktuelle Offline-Cache
    // bleibt) und sofort die Kontrolle über alle offenen Tabs übernehmen.
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== OFFLINE_CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener("fetch", (event) => {
    if (event.request.mode !== "navigate") return;

    event.respondWith(
        fetch(event.request).catch(() =>
            caches
                .match(OFFLINE_URL)
                .then((cached) => cached || Response.error()),
        ),
    );
});

self.addEventListener("message", (event) => {
    if (event.data === "SKIP_WAITING") self.skipWaiting();
});

self.addEventListener("push", (event) => {
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
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || "/";
    event.waitUntil(
        clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then((list) => {
                for (const c of list) {
                    if ("focus" in c) {
                        c.navigate(url);
                        return c.focus();
                    }
                }
                if (clients.openWindow) return clients.openWindow(url);
            }),
    );
});
