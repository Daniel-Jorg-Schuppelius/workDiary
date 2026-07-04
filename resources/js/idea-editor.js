// Ideenlandkarten-Editor (Feature 054, MVP-106/108): Gliederungs- und
// Canvas-Ansicht über denselben Alpine-Store. Kleine, knotenbezogene
// API-Aufrufe (nie "ganze Karte speichern"); jede Mutation sendet die geladene
// lock_version — ein 409 öffnet den sichtbaren Konfliktdialog (kein stilles
// Last-write-wins). Gliederung ist vollständig per Tastatur bedienbar
// (Enter = neuer Knoten, Tab/Shift+Tab = ein-/ausrücken, Alt+Pfeil = verschieben).
export function registerIdeaEditor(Alpine) {
    Alpine.data("ideaEditor", (configElId) => ({
        cfg: {},
        nodes: {}, // sqid -> node
        order: [], // flache Anzeige-Reihenfolge der Gliederung (sqids)
        rootSqid: null,
        selected: null,
        editingTitle: null,
        detailOpen: false,
        view: "outline",
        collapsed: {},
        busy: false,
        error: null,
        conflict: null, // {node(sqid), mine(payload), current(server-node)}
        lastDeleted: null,
        editing: [], // Präsenz (MVP-108): Namen anderer aktiver Bearbeiter
        historyOpen: false,
        history: [],
        convertResult: null, // MVP-109: {reference, existing} nach Überführung
        // Canvas-Zustand
        zoom: 1,
        panX: 40,
        panY: 40,
        dragging: null,

        init() {
            const el = document.getElementById(configElId);
            this.cfg = JSON.parse(el?.textContent || "{}");
            (this.cfg.nodes || []).forEach((n) => (this.nodes[n.sqid] = n));
            this.rootSqid = (this.cfg.nodes || []).find((n) => n.is_root)?.sqid || null;
            this.autoLayout();
            this.rebuildOrder();
            this.selected = this.rootSqid;

            // Präsenz-Heartbeat (MVP-108): alle 30 s, nur solange die Seite offen ist.
            if (this.cfg.urls.presence) {
                this.heartbeat();
                const timer = setInterval(() => this.heartbeat(), 30000);
                window.addEventListener("beforeunload", () => clearInterval(timer));
            }
        },

        async heartbeat() {
            const json = await this.api("POST", this.cfg.urls.presence).catch(() => null);
            if (json?.editing) this.editing = json.editing;
        },

        // Überführung (MVP-109): idempotent — ein zweiter Versuch liefert den
        // Hinweis aufs bestehende Ziel statt eines Duplikats.
        async convertNode(target) {
            if (!this.selected) return;
            this.convertResult = null;
            const json = await this.api("POST", this.urlFor("convert", this.selected), { target });
            if (json?.reference) {
                const n = this.node(this.selected);
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

        t(key) {
            return (this.cfg.labels || {})[key] || key;
        },
        node(sqid) {
            return this.nodes[sqid] || null;
        },
        childrenOf(sqid) {
            return Object.values(this.nodes)
                .filter((n) => n.parent === sqid)
                .sort((a, b) => a.sort_order - b.sort_order);
        },
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
            const out = [];
            const walk = (sqid) => {
                out.push(sqid);
                if (!this.collapsed[sqid]) {
                    this.childrenOf(sqid).forEach((c) => walk(c.sqid));
                }
            };
            if (this.rootSqid) walk(this.rootSqid);
            this.order = out;
        },

        async api(method, url, body) {
            this.busy = true;
            this.error = null;
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const json = await res.json().catch(() => ({}));
                if (res.status === 409) {
                    return { conflict: json.current };
                }
                if (!res.ok) {
                    this.error = json.message || res.statusText;
                    return null;
                }
                return json;
            } finally {
                this.busy = false;
            }
        },
        urlFor(action, sqid) {
            return (this.cfg.urls[action] || "").replace("__NODE__", sqid || "");
        },

        applyNode(node) {
            this.nodes[node.sqid] = node;
            this.rebuildOrder();
        },

        async addChild(parentSqid) {
            const json = await this.api("POST", this.cfg.urls.store, {
                parent: parentSqid,
                title: this.t("new_node"),
            });
            if (json?.node) {
                this.applyNode(json.node);
                this.selected = json.node.sqid;
                this.editingTitle = json.node.sqid;
                this.$nextTick(() => this.focusTitleInput(json.node.sqid));
            }
        },
        async addSibling(sqid) {
            const n = this.node(sqid);
            if (!n) return;
            await this.addChild(n.parent || this.rootSqid);
        },

        startRename(sqid) {
            if (!this.cfg.can_update) return;
            this.editingTitle = sqid;
            this.$nextTick(() => this.focusTitleInput(sqid));
        },
        focusTitleInput(sqid) {
            const input = this.$root.querySelector(`[data-title-input="${sqid}"]`);
            if (input) {
                input.focus();
                input.select();
            }
        },
        async saveTitle(sqid, title) {
            this.editingTitle = null;
            const n = this.node(sqid);
            if (!n || title.trim() === "" || title === n.title) return;
            await this.patchNode(sqid, { title: title.trim() });
        },

        async patchNode(sqid, payload) {
            const n = this.node(sqid);
            if (!n) return;
            const json = await this.api("PATCH", this.urlFor("update", sqid), {
                ...payload,
                lock_version: n.lock_version,
            });
            if (json?.conflict) {
                this.conflict = { node: sqid, mine: payload, current: json.conflict };
                return;
            }
            if (json?.node) this.applyNode(json.node);
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

        async indent(sqid) {
            const n = this.node(sqid);
            if (!n || !n.parent) return;
            const siblings = this.childrenOf(n.parent);
            const idx = siblings.findIndex((s) => s.sqid === sqid);
            if (idx <= 0) return; // kein vorheriges Geschwister → nicht einrückbar
            await this.moveNode(sqid, siblings[idx - 1].sqid);
        },
        async outdent(sqid) {
            const n = this.node(sqid);
            const parent = n ? this.node(n.parent) : null;
            if (!n || !parent || !parent.parent) return; // direkt unter Wurzel bleibt
            await this.moveNode(sqid, parent.parent);
        },
        async moveNode(sqid, newParentSqid) {
            const json = await this.api("POST", this.urlFor("move", sqid), { parent: newParentSqid });
            if (json?.node) this.applyNode(json.node);
        },
        async moveUp(sqid) {
            await this.shift(sqid, -1);
        },
        async moveDown(sqid) {
            await this.shift(sqid, 1);
        },
        async shift(sqid, delta) {
            const n = this.node(sqid);
            if (!n || !n.parent) return;
            const siblings = this.childrenOf(n.parent).map((s) => s.sqid);
            const idx = siblings.indexOf(sqid);
            const target = idx + delta;
            if (target < 0 || target >= siblings.length) return;
            siblings.splice(idx, 1);
            siblings.splice(target, 0, sqid);
            const json = await this.api("POST", this.urlFor("reorder", n.parent), { children: siblings });
            if (json?.ok) {
                siblings.forEach((s, i) => {
                    if (this.nodes[s]) this.nodes[s].sort_order = i;
                });
                this.rebuildOrder();
            }
        },

        async removeNode(sqid) {
            const n = this.node(sqid);
            if (!n || n.is_root) return;
            if (!window.confirm(this.t("confirm_delete_node"))) return;
            const json = await this.api("DELETE", this.urlFor("destroy", sqid));
            if (json?.ok) {
                const removeTree = (s) => {
                    this.childrenOf(s).forEach((c) => removeTree(c.sqid));
                    delete this.nodes[s];
                };
                this.lastDeleted = sqid;
                removeTree(sqid);
                this.selected = n.parent || this.rootSqid;
                this.rebuildOrder();
            }
        },
        async undoDelete() {
            if (!this.lastDeleted) return;
            const json = await this.api("POST", this.urlFor("restore", this.lastDeleted));
            if (json?.node) {
                this.lastDeleted = null;
                await this.reload();
            }
        },
        async reload() {
            const json = await this.api("GET", this.cfg.urls.tree);
            if (json?.nodes) {
                this.nodes = {};
                json.nodes.forEach((n) => (this.nodes[n.sqid] = n));
                this.autoLayout();
                this.rebuildOrder();
            }
        },

        toggleCollapse(sqid) {
            this.collapsed[sqid] = !this.collapsed[sqid];
            this.rebuildOrder();
        },

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
                const next = this.order[idx + (event.key === "ArrowDown" ? 1 : -1)];
                if (next) {
                    this.selected = next;
                    this.$nextTick(() => this.$root.querySelector(`[data-node-row="${next}"]`)?.focus());
                }
            }
        },

        // ── Canvas ──────────────────────────────────────────────────────
        autoLayout() {
            // Positionen nur für Knoten ohne gespeicherte Position ableiten:
            // x nach Tiefe, y nach Gliederungsreihenfolge.
            let row = 0;
            const walk = (sqid, depth) => {
                const n = this.node(sqid);
                if (!n) return;
                if (n.pos_x === null || n.pos_y === null) {
                    n.pos_x = depth * 240;
                    n.pos_y = row * 72;
                }
                row++;
                this.childrenOf(sqid).forEach((c) => walk(c.sqid, depth + 1));
            };
            if (this.rootSqid) walk(this.rootSqid, 0);
        },
        canvasNodes() {
            const visible = [];
            const walk = (sqid) => {
                visible.push(sqid);
                if (!this.collapsed[sqid]) this.childrenOf(sqid).forEach((c) => walk(c.sqid));
            };
            if (this.rootSqid) walk(this.rootSqid);
            return visible.map((s) => this.node(s)).filter(Boolean);
        },
        edges() {
            return this.canvasNodes()
                .filter((n) => n.parent && this.canvasNodes().some((p) => p.sqid === n.parent))
                .map((n) => {
                    const p = this.node(n.parent);
                    return { x1: p.pos_x + 90, y1: p.pos_y + 20, x2: n.pos_x + 90, y2: n.pos_y + 20, key: n.sqid };
                });
        },
        canvasTransform() {
            return `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`;
        },
        zoomIn() {
            this.zoom = Math.min(2, this.zoom + 0.1);
        },
        zoomOut() {
            this.zoom = Math.max(0.3, this.zoom - 0.1);
        },
        startPan(event) {
            if (event.target.closest("[data-canvas-node]")) return;
            this.dragging = { pan: true, x: event.clientX, y: event.clientY, panX: this.panX, panY: this.panY };
        },
        startNodeDrag(event, sqid) {
            if (!this.cfg.can_update) return;
            const n = this.node(sqid);
            this.dragging = { node: sqid, x: event.clientX, y: event.clientY, posX: n.pos_x, posY: n.pos_y };
        },
        onPointerMove(event) {
            if (!this.dragging) return;
            const dx = event.clientX - this.dragging.x;
            const dy = event.clientY - this.dragging.y;
            if (this.dragging.pan) {
                this.panX = this.dragging.panX + dx;
                this.panY = this.dragging.panY + dy;
            } else if (this.dragging.node) {
                const n = this.node(this.dragging.node);
                n.pos_x = Math.round(this.dragging.posX + dx / this.zoom);
                n.pos_y = Math.round(this.dragging.posY + dy / this.zoom);
            }
        },
        async endPointer() {
            const d = this.dragging;
            this.dragging = null;
            if (d?.node) {
                const n = this.node(d.node);
                if (n && (n.pos_x !== d.posX || n.pos_y !== d.posY)) {
                    await this.patchNode(d.node, { pos_x: n.pos_x, pos_y: n.pos_y });
                }
            }
        },
    }));
}
