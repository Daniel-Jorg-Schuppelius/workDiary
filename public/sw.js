/* Push Service Worker (PWA-Manifest separat im Layout)
 *
 * Bewusst KEIN fetch-Handler und KEIN clients.claim(): der SW soll Navigationen
 * NICHT abfangen, damit das normale Laravel-Rendering (Header inkl. Org-Switch,
 * Sprache, Theme, Benutzermenü) unverändert beim Browser ankommt. PWA-Installation
 * wird allein über das Web App Manifest (link rel="manifest") und Theme-Color
 * gesteuert.
 */
self.addEventListener("install", (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener("activate", (event) => {
    // Eventuell vorhandene Caches aus früheren PWA-Iterationen aufräumen UND
    // sofort die Kontrolle über alle offenen Tabs übernehmen, damit ein alter
    // SW mit fetch-Handler (Offline-Fallback) garantiert nicht weiter aktiv ist.
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
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
