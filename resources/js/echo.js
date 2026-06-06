// Laravel Echo + Reverb (WebSockets) — lazy initialisiert.
// Wird von der Chat-Seite aufgerufen (initEcho()), damit nicht auf jeder Seite
// vorzeitig eine WS-Verbindung geöffnet wird. Liest die VITE_REVERB_*-Variablen.
import Echo from "laravel-echo";
import Pusher from "pusher-js";

let echoInstance = null;

export function initEcho() {
    if (echoInstance) return echoInstance;

    window.Pusher = Pusher;
    echoInstance = new Echo({
        broadcaster: "reverb",
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
        enabledTransports: ["ws", "wss"],
    });
    window.Echo = echoInstance;
    return echoInstance;
}
