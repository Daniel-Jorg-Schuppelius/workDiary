import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/schedule.js",
                "resources/js/map.js",
                "resources/js/calendar.js",
                "resources/js/chat.js",
                "resources/js/signature.js",
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            // Rolldown (Vite 8) warnt, wenn Plugins viel Build-Zeit
            // beanspruchen. Hier dominieren @tailwindcss/vite und das
            // laravel-Plugin erwartungsgemäß den kleinen JS-Build – das ist
            // Normalfall, kein Problem. Den rauschenden Check daher abschalten,
            // damit der Build-Output sauber bleibt.
            checks: {
                pluginTimings: false,
            },
        },
    },
    server: {
        // 0.0.0.0 lässt Vite auf allen Interfaces lauschen, damit auch
        // Windows-Browser auf WSL2 die Assets (Fonts, Symbols, woff2)
        // unter http://localhost:5173 erreichen können. Vorher band 127.0.0.1
        // nur auf das WSL2-interne Loopback — Fonts schlugen dann silent
        // fehl (Browser zeigte Times als Fallback, Material-Symbol-Ligatures
        // blieben als Text sichtbar).
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        cors: true,
        hmr: {
            // Aus Browser-Sicht ist der HMR-Endpoint via localhost erreichbar
            // (sowohl direkt in Linux als auch über WSL2-Localhost-Forwarding).
            host: "localhost",
            port: 5173,
        },
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
