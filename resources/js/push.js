function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, "+")
        .replace(/_/g, "/");
    const raw = atob(base64);
    const out = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
    return out;
}

function csrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : "";
}

async function ensureRegistration() {
    if (!("serviceWorker" in navigator) || !("PushManager" in window))
        return null;
    return navigator.serviceWorker.register("/sw.js");
}

export async function pushSupported() {
    return (
        "serviceWorker" in navigator &&
        "PushManager" in window &&
        "Notification" in window
    );
}

export async function pushStatus() {
    if (!(await pushSupported())) return "unsupported";
    const reg = await navigator.serviceWorker.getRegistration("/sw.js");
    if (!reg) return "off";
    const sub = await reg.pushManager.getSubscription();
    return sub ? "on" : "off";
}

export async function pushSubscribe() {
    if (!(await pushSupported())) return false;
    const perm = await Notification.requestPermission();
    if (perm !== "granted") return false;
    const reg = await ensureRegistration();
    if (!reg) return false;
    const r = await fetch("/push/vapid", {
        headers: { Accept: "application/json" },
    });
    const { publicKey } = await r.json();
    if (!publicKey) return false;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
        sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
    }
    const json = sub.toJSON();
    await fetch("/push/subscribe", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf(),
        },
        body: JSON.stringify({
            endpoint: json.endpoint,
            keys: json.keys,
            contentEncoding: "aesgcm",
        }),
    });
    return true;
}

export async function pushUnsubscribe() {
    const reg = await navigator.serviceWorker.getRegistration("/sw.js");
    if (!reg) return true;
    const sub = await reg.pushManager.getSubscription();
    if (!sub) return true;
    const endpoint = sub.endpoint;
    await sub.unsubscribe();
    await fetch("/push/unsubscribe", {
        method: "DELETE",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf(),
        },
        body: JSON.stringify({ endpoint }),
    });
    return true;
}

export function bindPushToggle(selector = "[data-push-toggle]") {
    document.querySelectorAll(selector).forEach(async (el) => {
        const status = await pushStatus();
        el.dataset.state = status;
        updateLabel(el, status);
        el.addEventListener("click", async (ev) => {
            ev.preventDefault();
            const cur = el.dataset.state;
            if (cur === "unsupported") return;
            if (cur === "on") {
                await pushUnsubscribe();
                el.dataset.state = "off";
            } else if (cur === "off") {
                const ok = await pushSubscribe();
                el.dataset.state = ok ? "on" : "off";
            }
            updateLabel(el, el.dataset.state);
            el.dispatchEvent(
                new CustomEvent("push:changed", {
                    detail: { state: el.dataset.state },
                }),
            );
        });
    });
}

function updateLabel(el, state) {
    const label = el.querySelector("[data-push-label]");
    if (!label) return;
    if (state === "on") {
        label.textContent = "🔔";
        label.className = "badge badge-sm badge-success";
    } else if (state === "off") {
        label.textContent = "🔕";
        label.className = "badge badge-sm badge-ghost";
    } else {
        label.textContent = "—";
        label.className = "badge badge-sm badge-error";
    }
}
