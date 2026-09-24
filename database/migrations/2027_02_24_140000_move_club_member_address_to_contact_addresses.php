<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_24_140000_move_club_member_address_to_contact_addresses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\Club\ClubMember;
use App\Models\Contacts\ContactAddress;
use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-869 Kontaktdaten-Satelliten: Anschrift des Vereinsmitglieds wandert
 * aus `club_members.street/postal_code/city` nach `contact_addresses`
 * (Primäradresse); danach entfallen die Spalten. Idempotent — Mitglieder mit
 * vorhandener Satelliten-Adresse werden übersprungen.
 */
return new class extends Migration {
    public function up(): void {
        $alias = MorphMap::alias(ClubMember::class);
        DB::table('club_members')
            ->select(['id', 'organization_id', 'street', 'postal_code', 'city'])
            ->where(static function ($query): void {
                $query->whereNotNull('street')->orWhereNotNull('postal_code')->orWhereNotNull('city');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($alias): void {
                foreach ($rows as $row) {
                    $exists = ContactAddress::query()->withoutGlobalScopes()
                        ->where('addressable_type', $alias)->where('addressable_id', $row->id)->exists();
                    if ($exists || trim((string) $row->street . $row->postal_code . $row->city) === '') {
                        continue;
                    }
                    ContactAddress::query()->create([
                        'organization_id' => $row->organization_id,
                        'addressable_type' => $alias,
                        'addressable_id' => $row->id,
                        'kind' => ContactAddress::KIND_DEFAULT,
                        'street' => $row->street,
                        'zip' => $row->postal_code,
                        'city' => $row->city,
                        'is_primary' => true,
                    ]);
                }
            });
        Schema::table('club_members', function (Blueprint $table): void {
            $table->dropColumn(['street', 'postal_code', 'city']);
        });
    }

    public function down(): void {
        // Spalten kommen leer zurück; die Anschrift bleibt im Satelliten.
        Schema::table('club_members', function (Blueprint $table): void {
            $table->string('street', 190)->nullable()->after('phone');
            $table->string('postal_code', 20)->nullable()->after('street');
            $table->string('city', 120)->nullable()->after('postal_code');
        });
    }
};
