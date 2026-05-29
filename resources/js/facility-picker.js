// Alpine.js-Komponente für den hierarchischen Liegenschafts-Picker
// (Customer → Site → Building → Floor → Room). Wird global registriert,
// damit Blade-Komponenten sie via `x-data="facilityPicker(...)"` nutzen können.

window.facilityPicker = function (cfg) {
    const data = cfg?.data ?? {
        customers: [],
        sites: [],
        buildings: [],
        floors: [],
        rooms: [],
    };

    const initial = cfg?.initial ?? {};

    return {
        data,
        withRoom: !!cfg?.withRoom,

        customer_id: initial.customer_id ?? null,
        site_id: initial.site_id ?? null,
        building_id: initial.building_id ?? null,
        floor_id: initial.floor_id ?? null,
        room_id: initial.room_id ?? null,

        init() {
            // Initial konsistent halten: falls die Initialwerte nicht mehr in die
            // gefilterte Liste passen, leeren wir die nachgelagerten Selects.
            this.syncFromCurrent();
            this.autoSelectSingles();
        },

        // Bleibt auf einer Ebene nach der Filterung genau eine Option übrig,
        // wird sie automatisch gewählt — kaskadierend von oben nach unten,
        // sodass eindeutige Ketten (z. B. Kunde mit nur einem Standort/Gebäude)
        // direkt durchgereicht werden. Nur greifen, wenn die übergeordnete Ebene
        // gesetzt ist (echte Eingrenzung), damit nichts „voreilig" gewählt wird.
        autoSelectSingles() {
            if (
                this.customer_id != null &&
                this.site_id == null &&
                this.filteredSites.length === 1
            ) {
                this.site_id = this.filteredSites[0].id;
            }
            if (
                this.site_id != null &&
                this.building_id == null &&
                this.filteredBuildings.length === 1
            ) {
                this.building_id = this.filteredBuildings[0].id;
            }
            if (
                this.building_id != null &&
                this.floor_id == null &&
                this.filteredFloors.length === 1
            ) {
                this.floor_id = this.filteredFloors[0].id;
            }
            if (
                this.withRoom &&
                this.floor_id != null &&
                this.room_id == null &&
                this.filteredRooms.length === 1
            ) {
                this.room_id = this.filteredRooms[0].id;
            }
        },

        get filteredSites() {
            if (this.customer_id == null) return this.data.sites;
            return this.data.sites.filter(
                (s) =>
                    s.customer_id == null || s.customer_id === this.customer_id,
            );
        },

        get filteredBuildings() {
            if (this.site_id == null) {
                // Wenn ein Kunde gewählt ist, zeige nur Gebäude an Sites dieses Kunden.
                if (this.customer_id == null) return this.data.buildings;
                const siteIds = new Set(
                    this.filteredSites.map((s) => s.id),
                );
                return this.data.buildings.filter((b) =>
                    siteIds.has(b.site_id),
                );
            }
            return this.data.buildings.filter(
                (b) => b.site_id === this.site_id,
            );
        },

        get filteredFloors() {
            if (this.building_id == null) {
                const bIds = new Set(this.filteredBuildings.map((b) => b.id));
                return this.data.floors.filter((f) => bIds.has(f.building_id));
            }
            return this.data.floors.filter(
                (f) => f.building_id === this.building_id,
            );
        },

        get filteredRooms() {
            let rooms = this.data.rooms;
            if (this.floor_id != null) {
                rooms = rooms.filter((r) => r.floor_id === this.floor_id);
            } else {
                const fIds = new Set(this.filteredFloors.map((f) => f.id));
                rooms = rooms.filter(
                    (r) => r.floor_id == null || fIds.has(r.floor_id),
                );
            }
            if (this.customer_id != null) {
                rooms = rooms.filter(
                    (r) =>
                        r.customer_id == null ||
                        r.customer_id === this.customer_id,
                );
            }
            return rooms;
        },

        syncFromCurrent() {
            // Falls Room gesetzt, leite floor/building/site/customer ab.
            if (this.room_id != null) {
                const room = this.data.rooms.find(
                    (r) => r.id === this.room_id,
                );
                if (room) {
                    if (this.floor_id == null) this.floor_id = room.floor_id;
                    if (this.customer_id == null && room.customer_id != null) {
                        this.customer_id = room.customer_id;
                    }
                }
            }
            if (this.floor_id != null && this.building_id == null) {
                const floor = this.data.floors.find(
                    (f) => f.id === this.floor_id,
                );
                if (floor) this.building_id = floor.building_id;
            }
            if (this.building_id != null && this.site_id == null) {
                const building = this.data.buildings.find(
                    (b) => b.id === this.building_id,
                );
                if (building) this.site_id = building.site_id;
            }
            if (this.site_id != null && this.customer_id == null) {
                const site = this.data.sites.find((s) => s.id === this.site_id);
                if (site && site.customer_id != null) {
                    this.customer_id = site.customer_id;
                }
            }
        },

        onCustomerChange() {
            // Wenn Site nicht mehr zum Kunden passt, zurücksetzen.
            if (this.site_id != null) {
                const site = this.data.sites.find((s) => s.id === this.site_id);
                if (
                    !site ||
                    (site.customer_id != null &&
                        site.customer_id !== this.customer_id)
                ) {
                    this.site_id = null;
                    this.building_id = null;
                    this.floor_id = null;
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
        },

        onSiteChange() {
            if (this.building_id != null) {
                const b = this.data.buildings.find(
                    (b) => b.id === this.building_id,
                );
                if (!b || b.site_id !== this.site_id) {
                    this.building_id = null;
                    this.floor_id = null;
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
        },

        onBuildingChange() {
            if (this.floor_id != null) {
                const f = this.data.floors.find(
                    (f) => f.id === this.floor_id,
                );
                if (!f || f.building_id !== this.building_id) {
                    this.floor_id = null;
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
        },

        onFloorChange() {
            if (this.room_id != null) {
                const r = this.data.rooms.find((r) => r.id === this.room_id);
                if (!r || r.floor_id !== this.floor_id) {
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
        },
    };
};
