// Ideenlandkarten-Canvas (Feature 054, MVP-136): Mind-Elixir-Mindmap als
// Canvas-Ansicht. Bewusst isoliert von der handgebauten Gliederung
// (idea-editor.js), die die barrierefreie zweite Sicht bleibt. **Die DB ist die
// Wahrheit:** der Canvas hydratisiert aus dem Server-Baum und speichert die
// GANZE Karte über den Sync-Endpunkt (karten-weite lock_version → 409 statt
// stillem Überschreiben). Knoten-Identität läuft über die Sqid (als Mind-Elixir-
// `id`); neue Knoten tragen die generierte Mind-Elixir-id als client_id, deren
// Sqid der Server zurückliefert.
// Mind Elixir wird dynamisch importiert (Code-Splitting): das Paket landet in
// einem eigenen Vite-Chunk und lädt erst beim Öffnen des Canvas, nicht im
// Haupt-Bundle aller Seiten.
//
// Sicht-Synchronisation ohne F5: nach erfolgreichem Sync meldet der Canvas
// "idea-map-synced" (die Gliederung lädt den Serverbaum nach); umgekehrt setzt
// "idea-outline-changed" aus der Gliederung den Canvas auf stale — beim
// nächsten Anzeigen holt er den Serverbaum neu statt den Mount-Stand zu zeigen.

const DEBOUNCE_MS = 1200;

export function registerIdeaCanvas(Alpine) {
    Alpine.data("ideaCanvas", (configElId) => ({
        cfg: {},
        me: null, // Mind-Elixir-Instanz
        ME: null, // Mind-Elixir-Konstruktor (dynamisch geladen)
        lockVersion: 1,
        knownSqids: new Set(), // ids, die bereits echte Sqids sind (bestehende Knoten)
        idMap: {}, // Mind-Elixir-id (neu) → vom Server vergebene Sqid
        saveTimer: null,
        busy: false,
        error: null,
        conflict: false,
        mounted: false,
        stale: false, // Gliederung hat den Serverstand geändert → vor Anzeige neu laden

        init() {
            const el = document.getElementById(configElId);
            this.cfg = JSON.parse(el?.textContent || "{}");
            this.lockVersion = this.cfg.map?.lock_version || 1;
            // Lazy-Mount, sobald der Canvas-Tab sichtbar wird (vorher hat der
            // Container keine Maße): primär über das Fenster-Event aus dem
            // umschließenden ideaEditor, zusätzlich abgesichert per x-effect
            // im Blade. mount() ist idempotent.
            window.addEventListener("idea-canvas-show", () => this.show());
            // Gliederungs-Änderungen invalidieren den Canvas-Stand (auch vor
            // dem Erst-Mount: dann ist das eingebettete cfg bereits veraltet).
            window.addEventListener("idea-outline-changed", () => {
                this.stale = true;
            });
            // Bei Fenster-Resize die Resthöhe neu berechnen und die Karte
            // zentrieren (Mind Elixir rendert in den Container).
            window.addEventListener("resize", () => {
                if (!this.mounted) return;
                this.fitHeight();
                this.me?.toCenter?.();
            });
        },

        // Wird bei jedem Wechsel auf den Canvas-Tab aufgerufen: mountet einmalig
        // und passt die Höhe an den aktuell verfügbaren Platz an. Hat die
        // Gliederung zwischenzeitlich gespeichert, kommt vorher der frische
        // Serverbaum (statt des Mount-/Seitenlade-Stands).
        async show() {
            if (this.stale) {
                this.stale = false;
                if (this.mounted) {
                    await this.refresh();
                } else {
                    await this.refetchTree(); // frisches cfg VOR dem Erst-Mount
                }
            }
            await this.mount();
            this.$nextTick(() => {
                this.fitHeight();
                this.me?.toCenter?.();
            });
        },

        // Füllt den Restplatz, OHNE Scroll zu erzeugen. Wichtig: auf Desktop ist
        // NICHT das Dokument der Scroll-Container, sondern `main.wd-surface`
        // (feste Höhe, overflow:auto) — deshalb gegen dessen Unterkante messen.
        // Untere Grenze = das Näherliegende von main-Unterkante und Viewport
        // (mobil scrollt das Dokument, da hat `main` Höhe auto). Anschließend
        // einen Rest-Überhang im tatsächlich scrollenden Container abziehen
        // (Karten-/main-Padding unter dem Canvas). Untergrenze 24rem (Smartphone).
        fitHeight() {
            const host = this.$refs.meHost;
            if (!host || host.offsetParent === null) return; // nur wenn sichtbar
            const scroller = host.closest("main") || document.documentElement;
            const sRect = scroller.getBoundingClientRect();
            const hostTop = host.getBoundingClientRect().top;
            const GAP = 40; // kleiner Sicherheitsabstand, damit kein Rest-Scroll bleibt
            const bottom = Math.min(
                window.innerHeight,
                sRect.top + scroller.clientHeight,
            );
            const h = Math.max(384, Math.round(bottom - hostTop - GAP));
            host.style.height = h + "px";
            // scrollHeight-Lesen erzwingt Reflow → Messung ist aktuell.
            const overflow = Math.max(
                scroller.scrollHeight - scroller.clientHeight,
                document.documentElement.scrollHeight -
                    document.documentElement.clientHeight,
            );
            if (overflow > 0) {
                host.style.height = Math.max(384, h - overflow - GAP) + "px";
            }
        },

        async mount() {
            if (this.mounted) return;
            this.mounted = true;

            const [{ default: MindElixir }, , i18n] = await Promise.all([
                import("mind-elixir"),
                import("mind-elixir/style"),
                import("mind-elixir/i18n"),
            ]);
            this.ME = MindElixir;

            // Kontextmenü in der App-Sprache: Mind Elixir bringt Sprachpakete
            // mit (de/fr/it/es/…); Auswahl über das <html lang>-Attribut,
            // Fallback Englisch.
            const lang = (document.documentElement.lang || "en")
                .slice(0, 2)
                .toLowerCase();
            const locale = i18n[lang] || i18n.en;

            const host = this.$refs.meHost;
            this.fitHeight(); // Container-Höhe VOR init setzen, damit ME korrekt layoutet
            this.me = new MindElixir({
                el: host,
                direction: MindElixir.SIDE,
                editable: !!this.cfg.can_update,
                draggable: !!this.cfg.can_update,
                contextMenu: this.cfg.can_update
                    ? { focus: true, link: true, locale }
                    : false,
                toolBar: true,
                keypress: !!this.cfg.can_update,
                allowUndo: true, // Undo/Redo aus der Bibliothek
                newTopicName: this.cfg.labels?.new_node || undefined,
            });

            const data = this.hydrate();
            this.me.init(data);
            this.applyTheme();

            if (this.cfg.can_update) {
                // Jede undo-fähige Änderung (add/remove/move/edit) stößt einen
                // gedrosselten Whole-Map-Sync an.
                this.me.bus.addListener("operation", () => this.scheduleSave());
            }

            // Theme dem System-Hell/Dunkel-Wechsel nachführen.
            window
                .matchMedia?.("(prefers-color-scheme: dark)")
                ?.addEventListener?.("change", () => this.applyTheme());
        },

        // ── Hydrate: Server-Baum → Mind-Elixir-Daten ────────────────────
        hydrate() {
            const nodes = this.cfg.nodes || [];
            const byParent = {};
            let root = null;
            nodes.forEach((n) => {
                this.knownSqids.add(n.sqid);
                if (n.is_root) root = n;
                (byParent[n.parent || "__root__"] ||= []).push(n);
            });

            const build = (n) => ({
                topic: n.title || "—",
                id: n.sqid,
                note: n.note || undefined,
                metadata: {
                    color: n.color || "default",
                    status: n.node_status || null,
                },
                style: this.colorStyle(n.color),
                children: (byParent[n.sqid] || [])
                    .sort((a, b) => a.sort_order - b.sort_order)
                    .map(build),
            });

            const rootObj = root
                ? build(root)
                : { topic: this.cfg.map?.title || "Idee", id: "root" };
            return {
                nodeData: rootObj,
                arrows: this.hydrateArrows(),
                summaries: this.hydrateSummaries(),
                direction: this.ME.SIDE,
            };
        },

        // Querverbindungen (MVP-137) → Mind-Elixir-`arrows`. from/to sind
        // Knoten-Sqids (= Mind-Elixir-`id`).
        hydrateArrows() {
            return (this.cfg.links || []).map((l) => ({
                id: `${l.from}-${l.to}`,
                from: l.from,
                to: l.to,
                label: l.label || "",
            }));
        },

        // Boundaries (MVP-137) → Mind-Elixir-`summaries`. parent ist ein
        // Knoten-Sqid; start/end sind Kinder-Indizes.
        hydrateSummaries() {
            return (this.cfg.summaries || []).map((s) => ({
                id: `${s.parent}-${s.start}-${s.end}`,
                parent: s.parent,
                start: s.start,
                end: s.end,
                label: s.label || "",
            }));
        },

        // Server-Identifikator für einen Mind-Elixir-Knoten: Sqid (bestehend)
        // oder die Mind-Elixir-id (neu, = client_id). Der Sync-Endpunkt löst
        // beides über dieselbe refToId-Tabelle auf.
        nodeRef(meId) {
            const realId = this.idMap[meId] || meId;
            return this.knownSqids.has(realId) ? realId : meId;
        },

        colorStyle(color) {
            if (!color || color === "default") return undefined;
            const bg = this.cssColor(`bg-${color}`);
            return bg
                ? {
                      background: bg,
                      color:
                          this.cssColor(`text-${color}-content`) || undefined,
                  }
                : undefined;
        },

        // Liest die konkrete Farbe einer DaisyUI-Utility-Klasse über eine
        // unsichtbare Sonde (robust über DaisyUI-Versionen/Theme hinweg).
        cssColor(utility) {
            const probe = document.createElement("span");
            probe.className = utility;
            probe.style.cssText =
                "position:absolute;visibility:hidden;pointer-events:none";
            document.body.appendChild(probe);
            const cs = getComputedStyle(probe);
            const value = utility.startsWith("text-")
                ? cs.color
                : cs.backgroundColor;
            probe.remove();
            return value && value !== "rgba(0, 0, 0, 0)" ? value : null;
        },

        // ── Theming-Bridge: DaisyUI → Mind-Elixir-Theme ─────────────────
        applyTheme() {
            if (!this.me) return;
            const base100 = this.cssColor("bg-base-100");
            const base200 = this.cssColor("bg-base-200");
            const baseContent = this.cssColor("text-base-content");
            const primary = this.cssColor("bg-primary");
            const primaryContent = this.cssColor("text-primary-content");
            const accent = this.cssColor("bg-accent") || primary;

            const dark =
                document.documentElement
                    .getAttribute("data-theme")
                    ?.includes("dark") ||
                window.matchMedia?.("(prefers-color-scheme: dark)")?.matches;

            const theme = {
                name: "workdiary",
                type: dark ? "dark" : "light",
                palette: [
                    primary,
                    accent,
                    this.cssColor("bg-success"),
                    this.cssColor("bg-warning"),
                    this.cssColor("bg-info"),
                    this.cssColor("bg-error"),
                ].filter(Boolean),
                cssVar: {
                    "--main-color": baseContent,
                    "--main-bgcolor": base100,
                    "--color": baseContent,
                    "--bgcolor": base200,
                    "--selected": primary,
                    "--accent-color": accent,
                    "--root-color": primaryContent,
                    "--root-bgcolor": primary,
                    "--root-border-color": primary,
                },
            };
            // Mind Elixir übernimmt das Theme über die Daten bzw. changeTheme.
            if (typeof this.me.changeTheme === "function") {
                this.me.changeTheme(theme, false);
            }
        },

        // ── Sync: Mind-Elixir-Daten → Server ────────────────────────────
        scheduleSave() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.save(), DEBOUNCE_MS);
        },

        async save() {
            if (!this.me || this.busy) return;
            this.busy = true;
            this.error = null;
            const data = this.me.getData();
            const tree = this.toCanonical(data.nodeData);
            // Querverbindungen mitschicken (leeres Array = alle löschen). Endpunkte
            // über nodeRef, damit auch im selben Sync neu angelegte Knoten passen.
            const links = (data.arrows || []).map((a) => ({
                from: this.nodeRef(a.from),
                to: this.nodeRef(a.to),
                label: a.label || null,
            }));
            const summaries = (data.summaries || []).map((s) => ({
                parent: this.nodeRef(s.parent),
                start: s.start,
                end: s.end,
                label: s.label || null,
            }));
            try {
                const res = await fetch(this.cfg.urls.sync, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN":
                            /** @type {HTMLMetaElement} */ (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                )
                            )?.content || "",
                    },
                    body: JSON.stringify({
                        lock_version: this.lockVersion,
                        tree,
                        links,
                        summaries,
                    }),
                });
                const json = await res.json().catch(() => ({}));
                if (res.status === 409) {
                    this.conflict = true;
                    return;
                }
                if (!res.ok) {
                    this.error =
                        (json.errors && Object.values(json.errors)[0]?.[0]) ||
                        json.message ||
                        res.statusText;
                    return;
                }
                this.lockVersion = json.lock_version;
                // Neue Knoten: client_id (Mind-Elixir-id) → vergebene Sqid merken.
                Object.entries(json.created || {}).forEach(
                    ([clientId, sqid]) => {
                        this.idMap[clientId] = sqid;
                        this.knownSqids.add(sqid);
                    },
                );
                // Gliederung nachziehen (kein F5 nötig): sie lädt den
                // Serverbaum sofort bzw. beim nächsten Tab-Wechsel.
                window.dispatchEvent(new CustomEvent("idea-map-synced"));
            } catch (e) {
                this.error = String(e);
            } finally {
                this.busy = false;
            }
        },

        // Baut den kanonischen Sync-Baum. Bekannte Knoten → {sqid}, neue → {client_id}.
        toCanonical(node) {
            const realId = this.idMap[node.id] || node.id;
            const isExisting = this.knownSqids.has(realId);
            const meta = node.metadata || {};
            return {
                ...(isExisting ? { sqid: realId } : { client_id: node.id }),
                title: node.topic || "—",
                note: node.note || null,
                color: meta.color || "default",
                node_status: meta.status || null,
                children: (node.children || []).map((c) => this.toCanonical(c)),
            };
        },

        // ── Export (MVP-138): Mind Elixir rendert SVG/PNG clientseitig ──
        exportSvg() {
            if (!this.me) return;
            this.download(this.me.exportSvg(), this.exportName("svg"));
        },
        async exportPng() {
            if (!this.me) return;
            const blob = await this.me.exportPng();
            if (blob) this.download(blob, this.exportName("png"));
        },
        exportName(ext) {
            const base =
                (this.cfg.map?.title || "idea-map")
                    .replace(/[^\p{L}\p{N}_-]+/gu, "-")
                    .replace(/^-+|-+$/g, "")
                    .slice(0, 60) || "idea-map";
            return `${base}.${ext}`;
        },
        download(blob, name) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = name;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        },

        // Holt den aktuellen Serverbaum ins cfg (inkl. karten-weiter
        // lock_version) und setzt die Identitäts-Tabellen zurück — hydrate()
        // befüllt sie beim nächsten Aufbau neu.
        async refetchTree() {
            const res = await fetch(this.cfg.urls.tree, {
                headers: { Accept: "application/json" },
            }).catch(() => null);
            const json = res ? await res.json().catch(() => null) : null;
            if (!json) return false;
            this.cfg.nodes = json.nodes;
            this.cfg.links = json.links;
            this.cfg.summaries = json.summaries;
            this.cfg.map = json.map;
            this.lockVersion = json.map?.lock_version || 1;
            this.knownSqids = new Set();
            this.idMap = {};
            return true;
        },

        // Serverbaum neu laden und den gemounteten Canvas darauf umstellen.
        // clearHistory verhindert, dass Undo in den alten Stand zurückführt.
        async refresh() {
            if (!(await this.refetchTree())) return;
            this.me.refresh(this.hydrate());
            this.me.clearHistory?.();
            this.applyTheme();
        },

        async reloadFromConflict() {
            this.conflict = false;
            await this.refresh();
            // Der fremde Stand gilt jetzt auch für die Gliederung.
            window.dispatchEvent(new CustomEvent("idea-map-synced"));
        },
    }));
}
