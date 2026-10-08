// Ideenlandkarten-Gliederungseditor (Feature 054, MVP-106/108/135): die
// barrierefreie, vollständig tastaturbedienbare Baum-Ansicht. Kleine,
// knotenbezogene API-Aufrufe (nie "ganze Karte speichern"); jede Mutation
// sendet die geladene lock_version — ein 409 öffnet den sichtbaren
// Konfliktdialog (kein stilles Last-write-wins). Tastatur: Enter = neuer
// Knoten, Tab/Shift+Tab = ein-/ausrücken, Alt+Pfeil = verschieben, F2 =
// umbenennen.
//
// Die Canvas-Ansicht ist eine EIGENE Komponente (idea-canvas.js, Mind Elixir);
// dieser Editor stößt sie beim Tab-Wechsel nur über ein Fenster-Event an.
// Sicht-Synchronisation ohne F5: jede erfolgreiche Gliederungs-Mutation meldet
// "idea-outline-changed" (der Canvas lädt beim nächsten Anzeigen neu); nach
// einem Canvas-Sync kommt "idea-map-synced" zurück und die Gliederung lädt den
// Serverbaum nach — sofort, wenn sie sichtbar ist, sonst beim Tab-Wechsel.
import { request } from "./lib/http.js";

/**
 * Verweis eines Knotens (IdeaNodeController::serializeReference).
 * @typedef {Object} IdeaReference
 * @property {string} kind
 * @property {string} type
 * @property {string} label
 * @property {string | null} url
 */

/**
 * Knoten aus der Blade-Config (ideas/show) bzw. IdeaNodeController::serialize();
 * `references` liefert nur der Controller.
 * @typedef {Object} IdeaNode
 * @property {string} sqid
 * @property {string | null} parent
 * @property {boolean} is_root
 * @property {string} title
 * @property {string | null} note
 * @property {string} color
 * @property {string | null} node_status
 * @property {number | null} pos_x
 * @property {number | null} pos_y
 * @property {number} sort_order
 * @property {number} lock_version
 * @property {number} comment_count
 * @property {number} attachment_count
 * @property {IdeaReference[]} [references]
 */

/** @typedef {{from: string, to: string, label: string | null, color: string | null}} IdeaLink */
/** @typedef {{parent: string, start: number, end: number, label: string | null}} IdeaSummary */
/** @typedef {{sqid: string, title: string, lock_version: number, archived?: boolean}} IdeaMapHead */

/**
 * Editor-Config aus ideas/show (`#idea-editor-config`); idea-canvas.js liest
 * dieselbe Config.
 * @typedef {Object} IdeaMapConfig
 * @property {boolean} can_update
 * @property {IdeaNode[]} nodes
 * @property {IdeaMapHead} map
 * @property {IdeaLink[]} links
 * @property {IdeaSummary[]} summaries
 * @property {Record<string, string>} urls Routen, Knoten-Platzhalter `__NODE__`
 * @property {string[]} convert_targets
 * @property {Record<string, string>} labels
 * @property {Array<{value: string, label: string}>} colors
 * @property {Array<{value: string, label: string}>} statuses
 */

/** @typedef {{at: string | null, user: string | null, event: string, subject: string | null}} IdeaHistoryEntry */

/** @typedef {{title?: string, note?: string | null, color?: string, node_status?: string | null}} IdeaNodePatch */

/**
 * Antwort der Knoten-/Karten-Endpunkte; je Endpunkt ist nur ein Teil gesetzt.
 * `conflict` setzt api() aus dem `current` einer 409-Antwort.
 * @typedef {Object} IdeaApiResponse
 * @property {IdeaNode} [node]
 * @property {IdeaNode[]} [nodes]
 * @property {boolean} [ok]
 * @property {IdeaNode} [conflict]
 * @property {IdeaReference} [reference]
 * @property {boolean} [existing]
 * @property {string[]} [editing]
 * @property {IdeaHistoryEntry[]} [entries]
 */

/** @param {import("alpinejs").Alpine} Alpine */
export function registerIdeaEditor(Alpine) {
    Alpine.data("ideaEditor", (/** @type {string} */ configElId) => ({
        cfg: /** @type {IdeaMapConfig} */ ({}),
        /** @type {Record<string, IdeaNode>} */
        nodes: {}, // sqid -> node
        /** @type {string[]} */
        order: [], // flache Anzeige-Reihenfolge der Gliederung (sqids)
        /** @type {string | null} */
        rootSqid: null,
        /** @type {string | null} */
        selected: null,
        /** @type {string | null} */
        editingTitle: null,
        detailOpen: false,
        view: "outline",
        /** @type {Record<string, boolean>} */
        collapsed: {},
        busy: false,
        /** @type {string | null} */
        error: null,
        /** @type {{node: string, mine: IdeaNodePatch, current: IdeaNode} | null} */
        conflict: null, // Konflikt: Knoten-Sqid, eigener Stand, Server-Stand
        /** @type {string | null} */
        lastDeleted: null,
        canvasDirty: false, // Canvas hat gespeichert, während die Gliederung verborgen war
        /** @type {string[]} */
        editing: [], // Präsenz (MVP-108): Namen anderer aktiver Bearbeiter
        historyOpen: false,
        /** @type {IdeaHistoryEntry[]} */
        history: [],
        /** @type {IdeaApiResponse | null} */
        convertResult: null, // MVP-109: {reference, existing} nach Überführung

        init() {
            const el = document.getElementById(configElId);
            this.cfg = JSON.parse(el?.textContent || "{}");
            (this.cfg.nodes || []).forEach((n) => (this.nodes[n.sqid] = n));
            this.rootSqid =
                (this.cfg.nodes || []).find((n) => n.is_root)?.sqid || null;
            this.rebuildOrder();
            this.selected = this.rootSqid;

            // Canvas (idea-canvas.js, Mind Elixir) beim Wechsel auf den
            // Canvas-Tab über ein Fenster-Event zum idempotenten Mount anstoßen;
            // zurück zur Gliederung ggf. den vom Canvas geänderten Stand nachladen.
            this.$watch("view", (v) => {
                if (v === "canvas")
                    window.dispatchEvent(new CustomEvent("idea-canvas-show"));
                if (v === "outline" && this.canvasDirty) {
                    this.canvasDirty = false;
                    this.reload();
                }
            });

            // Canvas hat gespeichert (Whole-Map-Sync): sichtbare Gliederung
            // sofort nachladen — der debounced Sync kann auch NACH dem
            // Tab-Wechsel zur Gliederung noch eintreffen. Verborgen reicht
            // das Nachladen beim nächsten Tab-Wechsel.
            window.addEventListener("idea-map-synced", () => {
                if (this.view === "outline") {
                    this.reload();
                } else {
                    this.canvasDirty = true;
                }
            });

            // Präsenz-Heartbeat (MVP-108): alle 30 s, nur solange die Seite offen ist.
            if (this.cfg.urls.presence) {
                this.heartbeat();
                const timer = setInterval(() => this.heartbeat(), 30000);
                window.addEventListener("beforeunload", () =>
                    clearInterval(timer),
                );
            }
        },

        async heartbeat() {
            const json = await this.api("POST", this.cfg.urls.presence).catch(
                () => null,
            );
            if (json?.editing) this.editing = json.editing;
        },

        // Überführung (MVP-109): idempotent — ein zweiter Versuch liefert den
        // Hinweis aufs bestehende Ziel statt eines Duplikats.
        /** @param {string} target */
        async convertNode(target) {
            if (!this.selected) return;
            this.convertResult = null;
            const json = await this.api(
                "POST",
                this.urlFor("convert", this.selected),
                { target },
            );
            if (json?.reference) {
                // Detailansicht zeigt nur einen vorhandenen Knoten.
                const n = /** @type {IdeaNode} */ (this.node(this.selected));
                if (!json.existing) {
                    n.references = [...(n.references || []), json.reference];
                }
                this.convertResult = json;
            }
        },

        async toggleHistory() {
            this.historyOpen = !this.historyOpen;
            if (this.historyOpen) {
                const json = await this.api("GET", this.cfg.urls.history);
                if (json?.entries) this.history = json.entries;
            }
        },

        /** @param {string} key */
        t(key) {
            return (this.cfg.labels || {})[key] || key;
        },
        /**
         * @param {string | null} sqid null trifft wie ein unbekannter Schlüssel nichts
         * @returns {IdeaNode | null}
         */
        node(sqid) {
            return this.nodes[/** @type {string} */ (sqid)] || null;
        },
        // Null-sichere Feld-Helfer für Direktiven: der @alpinejs/csp-Parser
        // kennt kein Optional Chaining (node(sqid)?.title) und keine
        // Mehrfach-Statements — die Logik lebt deshalb hier.
        /** @param {string | null} sqid */
        nodeTitle(sqid) {
            return this.node(sqid)?.title ?? "";
        },
        /** @param {string} sqid */
        nodeColor(sqid) {
            return this.node(sqid)?.color ?? "";
        },
        /** @param {string} sqid */
        nodeStatus(sqid) {
            return this.node(sqid)?.node_status ?? "";
        },
        /** @param {string | null} sqid */
        isRoot(sqid) {
            return !!this.node(sqid)?.is_root;
        },
        selectedNote() {
            return this.node(this.selected)?.note ?? "";
        },
        selectedColor() {
            return this.node(this.selected)?.color ?? "";
        },
        selectedStatus() {
            return this.node(this.selected)?.node_status ?? "";
        },
        selectedReferences() {
            return this.node(this.selected)?.references || [];
        },
        /** @param {string | null} sqid */
        commentCount(sqid) {
            return this.node(sqid)?.comment_count ?? 0;
        },
        /** @param {string | null} sqid */
        attachmentCount(sqid) {
            return this.node(sqid)?.attachment_count ?? 0;
        },
        /** @param {string} sqid */
        openDetails(sqid) {
            this.selected = sqid;
            this.detailOpen = true;
        },
        /** @param {string} sqid */
        childrenOf(sqid) {
            return Object.values(this.nodes)
                .filter((n) => n.parent === sqid)
                .sort((a, b) => a.sort_order - b.sort_order);
        },
        /** @param {string} sqid */
        depthOf(sqid) {
            let d = 0;
            let n = this.node(sqid);
            while (n && n.parent) {
                d++;
                n = this.node(n.parent);
            }
            return d;
        },
        rebuildOrder() {
            /** @type {string[]} */
            const out = [];
            const walk = (/** @type {string} */ sqid) => {
                out.push(sqid);
                if (!this.collapsed[sqid]) {
                    this.childrenOf(sqid).forEach((c) => walk(c.sqid));
                }
            };
            if (this.rootSqid) walk(this.rootSqid);
            this.order = out;
        },

        /**
         * @param {string} method
         * @param {string} url
         * @param {object} [body]
         * @returns {Promise<IdeaApiResponse | null>}
         */
        async api(method, url, body) {
            this.busy = true;
            this.error = null;
            try {
                const res = await request(url, {
                    method,
                    json: body ? body : undefined,
                });
                /** @type {IdeaApiResponse & {current?: IdeaNode, message?: string, errors?: Record<string, string[]>}} */
                const json = await res.json().catch(() => ({}));
                if (res.status === 409) {
                    return { conflict: json.current };
                }
                if (!res.ok) {
                    // 422: Laravel liefert {message, errors:{feld:[…]}} — erste
                    // konkrete Feldmeldung zeigen statt des generischen Rohtexts.
                    const firstFieldError = json.errors
                        ? Object.values(json.errors)[0]?.[0]
                        : null;
                    this.error =
                        firstFieldError || json.message || res.statusText;
                    return null;
                }
                return json;
            } finally {
                this.busy = false;
            }
        },
        /**
         * @param {string} action
         * @param {string | null} sqid
         */
        urlFor(action, sqid) {
            return (this.cfg.urls[action] || "").replace(
                "__NODE__",
                sqid || "",
            );
        },

        // Canvas über eine Gliederungs-Änderung informieren: er lädt beim
        // nächsten Anzeigen den Serverbaum neu statt seinen Mount-Stand zu zeigen.
        notifyCanvas() {
            window.dispatchEvent(new CustomEvent("idea-outline-changed"));
        },

        /** @param {IdeaNode} node */
        applyNode(node) {
            this.nodes[node.sqid] = node;
            this.rebuildOrder();
            this.notifyCanvas();
        },

        /** @param {string} parentSqid */
        async addChild(parentSqid) {
            const json = await this.api("POST", this.cfg.urls.store, {
                parent: parentSqid,
                title: this.t("new_node"),
            });
            if (json?.node) {
                if (this.collapsed[parentSqid]) this.toggleCollapse(parentSqid);
                this.applyNode(json.node);
                this.selected = json.node.sqid;
                this.editingTitle = json.node.sqid;
                this.$nextTick(() => this.focusTitleInput(/** @type {IdeaNode} */ (json.node).sqid));
            }
        },
        /** @param {string} sqid */
        async addSibling(sqid) {
            const n = this.node(sqid);
            if (!n) return;
            // Ohne Elternteil ist n die Wurzel, rootSqid ist dann gesetzt.
            await this.addChild(/** @type {string} */ (n.parent || this.rootSqid));
        },

        /** @param {string} sqid */
        startRename(sqid) {
            if (!this.cfg.can_update) return;
            this.editingTitle = sqid;
            this.$nextTick(() => this.focusTitleInput(sqid));
        },
        /** @param {string} sqid */
        focusTitleInput(sqid) {
            const input = /** @type {HTMLInputElement | null} */ (
                this.$root?.querySelector(
                    `[data-title-input="${sqid}"]`,
                )
            );
            if (input) {
                input.focus();
                input.select();
            }
        },
        /**
         * @param {string} sqid
         * @param {string} title
         */
        async saveTitle(sqid, title) {
            this.editingTitle = null;
            const n = this.node(sqid);
            if (!n || title.trim() === "" || title === n.title) return;
            await this.patchNode(sqid, { title: title.trim() });
        },

        /**
         * @param {string} sqid
         * @param {IdeaNodePatch} payload
         */
        async patchNode(sqid, payload) {
            const n = this.node(sqid);
            if (!n) return;
            const json = await this.api("PATCH", this.urlFor("update", sqid), {
                ...payload,
                lock_version: n.lock_version,
            });
            if (json?.conflict) {
                this.conflict = {
                    node: sqid,
                    mine: payload,
                    current: json.conflict,
                };
                return;
            }
            if (json?.node) this.applyNode(json.node);
        },

        // Detail-Ansicht speichern (MVP-135): Notiz + Status werden explizit
        // gesichert (nicht mehr per change-Event, das beim Schließen verloren
        // ging). Nur bei tatsächlicher Änderung patchen.
        /**
         * @param {string | undefined} note
         * @param {string | undefined} status
         */
        async saveDetails(note, status) {
            if (!this.selected || !this.cfg.can_update) return;
            const n = this.node(this.selected);
            if (!n) return;
            /** @type {IdeaNodePatch} */
            const payload = {};
            if ((note ?? "") !== (n.note ?? "")) payload.note = note || null;
            const st = status || null;
            if (st !== (n.node_status ?? null)) payload.node_status = st;
            if (Object.keys(payload).length > 0)
                await this.patchNode(this.selected, payload);
        },
        /**
         * @param {string | undefined} note
         * @param {string | undefined} status
         */
        async closeDetails(note, status) {
            await this.saveDetails(note, status);
            this.detailOpen = false;
        },
        // Varianten für Direktiven: lesen Notiz/Status selbst aus $refs —
        // `$refs.detailNote?.value` wäre im CSP-Build nicht auswertbar.
        saveDetailsFromRefs() {
            return this.saveDetails(
                /** @type {HTMLTextAreaElement | undefined} */ (this.$refs.detailNote)?.value,
                /** @type {HTMLSelectElement | undefined} */ (this.$refs.detailStatus)?.value,
            );
        },
        closeDetailsFromRefs() {
            return this.closeDetails(
                /** @type {HTMLTextAreaElement | undefined} */ (this.$refs.detailNote)?.value,
                /** @type {HTMLSelectElement | undefined} */ (this.$refs.detailStatus)?.value,
            );
        },

        // Farb-Swatches der Detail-Ansicht (MVP-135): Farbwert → DaisyUI-bg-Klasse.
        /** @param {string} color */
        swatchClass(color) {
            return (
                /** @type {Record<string, string>} */ ({
                    default: "bg-base-300",
                    primary: "bg-primary",
                    success: "bg-success",
                    warning: "bg-warning",
                    error: "bg-error",
                    info: "bg-info",
                })[color] || "bg-base-300"
            );
        },

        // Konfliktdialog (MVP-108): fremden Stand übernehmen ODER eigenen
        // Text auf dem neuen Stand erneut anwenden — nie still überschreiben.
        conflictTakeServer() {
            if (!this.conflict) return;
            this.applyNode(this.conflict.current);
            this.conflict = null;
        },
        async conflictRetryMine() {
            if (!this.conflict) return;
            const { node, mine, current } = this.conflict;
            this.applyNode(current); // neue lock_version übernehmen …
            this.conflict = null;
            await this.patchNode(node, mine); // … und eigene Änderung erneut anwenden
        },

        /** @param {string} sqid */
        async indent(sqid) {
            const n = this.node(sqid);
            if (!n || !n.parent) return;
            const siblings = this.childrenOf(n.parent);
            const idx = siblings.findIndex((s) => s.sqid === sqid);
            if (idx <= 0) return; // kein vorheriges Geschwister → nicht einrückbar
            await this.moveNode(sqid, siblings[idx - 1].sqid);
        },
        /** @param {string} sqid */
        async outdent(sqid) {
            const n = this.node(sqid);
            const parent = n ? this.node(n.parent) : null;
            if (!n || !parent || !parent.parent) return; // direkt unter Wurzel bleibt
            await this.moveNode(sqid, parent.parent);
        },
        /**
         * @param {string} sqid
         * @param {string} newParentSqid
         */
        async moveNode(sqid, newParentSqid) {
            const json = await this.api("POST", this.urlFor("move", sqid), {
                parent: newParentSqid,
            });
            if (json?.node) this.applyNode(json.node);
        },
        /** @param {string} sqid */
        async moveUp(sqid) {
            await this.shift(sqid, -1);
        },
        /** @param {string} sqid */
        async moveDown(sqid) {
            await this.shift(sqid, 1);
        },
        /**
         * @param {string} sqid
         * @param {number} delta
         */
        async shift(sqid, delta) {
            const n = this.node(sqid);
            if (!n || !n.parent) return;
            const siblings = this.childrenOf(n.parent).map((s) => s.sqid);
            const idx = siblings.indexOf(sqid);
            const target = idx + delta;
            if (target < 0 || target >= siblings.length) return;
            siblings.splice(idx, 1);
            siblings.splice(target, 0, sqid);
            const json = await this.api(
                "POST",
                this.urlFor("reorder", n.parent),
                { children: siblings },
            );
            if (json?.ok) {
                siblings.forEach((s, i) => {
                    if (this.nodes[s]) this.nodes[s].sort_order = i;
                });
                this.rebuildOrder();
                this.notifyCanvas();
            }
        },

        /** @param {string} sqid */
        async removeNode(sqid) {
            const n = this.node(sqid);
            if (!n || n.is_root) return;
            const ok = await (window.confirmAction
                ? window.confirmAction({
                      message: this.t("confirm_delete_node"),
                      label: this.t("delete"),
                      icon: "delete",
                  })
                : Promise.resolve(true));
            if (!ok) return;
            const json = await this.api("DELETE", this.urlFor("destroy", sqid));
            if (json?.ok) {
                const removeTree = (/** @type {string} */ s) => {
                    this.childrenOf(s).forEach((c) => removeTree(c.sqid));
                    delete this.nodes[s];
                };
                this.lastDeleted = sqid;
                removeTree(sqid);
                this.selected = n.parent || this.rootSqid;
                this.rebuildOrder();
                this.notifyCanvas();
            }
        },
        async undoDelete() {
            if (!this.lastDeleted) return;
            const json = await this.api(
                "POST",
                this.urlFor("restore", this.lastDeleted),
            );
            if (json?.node) {
                this.lastDeleted = null;
                await this.reload();
                this.notifyCanvas();
            }
        },
        async reload() {
            const json = await this.api("GET", this.cfg.urls.tree);
            if (json?.nodes) {
                this.nodes = {};
                json.nodes.forEach((n) => (this.nodes[n.sqid] = n));
                this.rebuildOrder();
                // Auswahl kann durch den neuen Stand verschwunden sein
                // (z. B. Knoten im Canvas gelöscht) → zurück auf die Wurzel.
                if (this.selected && !this.nodes[this.selected]) {
                    this.selected = this.rootSqid;
                }
            }
        },

        /** @param {string} sqid */
        toggleCollapse(sqid) {
            this.collapsed[sqid] = !this.collapsed[sqid];
            this.rebuildOrder();
        },

        /**
         * @param {KeyboardEvent} event
         * @param {string} sqid
         */
        onKeydown(event, sqid) {
            if (!this.cfg.can_update || this.editingTitle) return;
            if (event.key === "Enter") {
                event.preventDefault();
                event.shiftKey ? this.addChild(sqid) : this.addSibling(sqid);
            } else if (event.key === "Tab") {
                event.preventDefault();
                event.shiftKey ? this.outdent(sqid) : this.indent(sqid);
            } else if (event.altKey && event.key === "ArrowUp") {
                event.preventDefault();
                this.moveUp(sqid);
            } else if (event.altKey && event.key === "ArrowDown") {
                event.preventDefault();
                this.moveDown(sqid);
            } else if (event.key === "F2") {
                event.preventDefault();
                this.startRename(sqid);
            } else if (event.key === "Delete" && event.shiftKey) {
                event.preventDefault();
                this.removeNode(sqid);
            } else if (event.key === "ArrowUp" || event.key === "ArrowDown") {
                event.preventDefault();
                const idx = this.order.indexOf(sqid);
                const next =
                    this.order[idx + (event.key === "ArrowDown" ? 1 : -1)];
                if (next) {
                    this.selected = next;
                    this.$nextTick(() =>
                        /** @type {HTMLElement | null} */ (
                            this.$root.querySelector(`[data-node-row="${next}"]`)
                        )?.focus(),
                    );
                }
            }
        },
    }));
}
