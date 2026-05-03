/* Push Service Worker */
self.addEventListener("install", (e) => self.skipWaiting());
self.addEventListener("activate", (e) => e.waitUntil(self.clients.claim()));

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
