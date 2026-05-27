<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_14_150000_migrate_legacy_room_facility_strings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Übernimmt die alten rooms.building / rooms.floor Freitext-Spalten in die
     * neue FM-Hierarchie (Site → Building → Floor) und setzt rooms.floor_id.
     *
     * Idempotent: Nachschlag-Schlüssel sind Name / Level — bei erneutem Lauf
     * werden keine Duplikate erzeugt. Räume mit bereits gesetztem floor_id
     * oder ohne customer_id (kein Anker) werden übersprungen.
     */
    public function up(): void {
        $now = now();

        $rooms = DB::table('rooms')
            ->whereNull('floor_id')
            ->whereNotNull('customer_id')
            ->where(function ($q): void {
                $q->whereNotNull('building')->orWhereNotNull('floor');
            })
            ->get(['id', 'organization_id', 'customer_id', 'building', 'floor']);

        foreach ($rooms as $room) {
            $buildingName = trim((string) ($room->building ?? '')) !== ''
                ? trim((string) $room->building)
                : 'Hauptgebäude';
            $floorLabel = trim((string) ($room->floor ?? '')) !== ''
                ? trim((string) $room->floor)
                : 'EG';
            $level = $this->parseLevel($floorLabel);

            $siteId = $this->ensureSite($room->organization_id, $room->customer_id, $now);
            $buildingId = $this->ensureBuilding($room->organization_id, $siteId, $buildingName, $now);
            $floorId = $this->ensureFloor($room->organization_id, $buildingId, $level, $floorLabel, $now);

            DB::table('rooms')->where('id', $room->id)->update([
                'floor_id' => $floorId,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Down lässt sich nicht verlustfrei umkehren — die Legacy-Spalten
     * (rooms.building / rooms.floor) bleiben unverändert erhalten und können
     * jederzeit als Fallback gelesen werden.
     */
    public function down(): void {
        // no-op: Datenmigration bewahrt die ursprünglichen String-Spalten.
    }

    private function ensureSite(int $organizationId, int $customerId, \DateTimeInterface $now): int {
        $existing = DB::table('sites')
            ->where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->where('name', 'Standort')
            ->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('sites')->insertGetId([
            'organization_id' => $organizationId,
            'customer_id'     => $customerId,
            'name'            => 'Standort',
            'code'            => null,
            'is_active'       => true,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    private function ensureBuilding(int $organizationId, int $siteId, string $name, \DateTimeInterface $now): int {
        $existing = DB::table('buildings')
            ->where('site_id', $siteId)
            ->where('name', $name)
            ->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('buildings')->insertGetId([
            'organization_id' => $organizationId,
            'site_id'         => $siteId,
            'name'            => $name,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    private function ensureFloor(int $organizationId, int $buildingId, int $level, string $label, \DateTimeInterface $now): int {
        $existing = DB::table('floors')
            ->where('building_id', $buildingId)
            ->where('level', $level)
            ->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('floors')->insertGetId([
            'organization_id' => $organizationId,
            'building_id'     => $buildingId,
            'level'           => $level,
            'label'           => mb_substr($label, 0, 40),
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /**
     * Heuristische Ebenen-Ableitung aus Freitext-Labels wie
     * "EG", "1. OG", "2.UG", "Dachgeschoss", "-1".
     */
    private function parseLevel(string $label): int {
        $normalized = mb_strtolower(trim($label));

        if (preg_match('/^(-?\d+)/', $normalized, $m) === 1) {
            return (int) $m[1];
        }

        if (preg_match('/(-?\d+)\s*\.?\s*ug/u', $normalized, $m) === 1) {
            return -1 * abs((int) $m[1]);
        }
        if (preg_match('/(-?\d+)\s*\.?\s*og/u', $normalized, $m) === 1) {
            return (int) $m[1];
        }

        return match (true) {
            str_contains($normalized, 'unterges') => -1,
            $normalized === 'ug'                  => -1,
            str_contains($normalized, 'erdges')   => 0,
            $normalized === 'eg'                  => 0,
            str_contains($normalized, 'dachges')  => 99,
            $normalized === 'dg'                  => 99,
            default                               => 0,
        };
    }
};
