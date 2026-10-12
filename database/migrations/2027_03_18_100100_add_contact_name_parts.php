<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_18_100100_add_contact_name_parts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use CommonToolkit\Helper\Data\PersonNameHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Ansprechpartner mit Anrede und Namensteilen (MVP-1136/1137). `contact_name`
 * bleibt die Anzeige und wird künftig aus den Teilen gebildet; der Bestand
 * wird mit dem Namenszerleger des common-toolkit zerlegt, ohne
 * `contact_name` zu ändern. Die Liste weiterer Ansprechpartner bekommt
 * dieselben Teile je Eintrag.
 */
return new class extends Migration {
    private const TABLES = ['customers', 'suppliers', 'foreign_customers', 'leads'];

    private const PARTS = ['contact_salutation', 'contact_title', 'contact_first_name', 'contact_middle_names', 'contact_last_name'];

    public function up(): void {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'contact_first_name')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t): void {
                $t->string('contact_salutation', 16)->nullable()->after('contact_name');
                $t->string('contact_title', 64)->nullable()->after('contact_salutation');
                $t->string('contact_first_name', 128)->nullable()->after('contact_title');
                $t->string('contact_middle_names', 128)->nullable()->after('contact_first_name');
                $t->string('contact_last_name', 128)->nullable()->after('contact_middle_names');
            });
        }

        foreach (self::TABLES as $table) {
            $hasList = Schema::hasColumn($table, 'contact_persons');
            $query = DB::table($table)->select(array_merge(['id', 'contact_name'], $hasList ? ['contact_persons'] : []));
            $hasList
                ? $query->where(fn ($q) => $q->whereNotNull('contact_name')->orWhereNotNull('contact_persons'))
                : $query->whereNotNull('contact_name');
            $query->chunkById(500, function ($rows) use ($table, $hasList): void {
                foreach ($rows as $row) {
                    $update = [];
                    if (trim((string) $row->contact_name) !== '') {
                        $parts = PersonNameHelper::split((string) $row->contact_name);
                        $update = [
                            'contact_salutation' => $parts['salutation'],
                            'contact_title' => $parts['title'],
                            'contact_first_name' => $parts['first_name'],
                            'contact_middle_names' => $parts['middle_names'],
                            'contact_last_name' => $parts['last_name'],
                        ];
                    }
                    if ($hasList && $row->contact_persons !== null) {
                        $persons = json_decode((string) $row->contact_persons, true);
                        if (is_array($persons)) {
                            $update['contact_persons'] = json_encode(array_map(static function (mixed $person): mixed {
                                if (! is_array($person) || array_key_exists('last_name', $person) || trim((string) ($person['name'] ?? '')) === '') {
                                    return $person;
                                }

                                return $person + PersonNameHelper::split((string) $person['name']);
                            }, $persons), JSON_UNESCAPED_UNICODE);
                        }
                    }
                    if ($update !== []) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
        }
    }

    public function down(): void {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'contact_first_name')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn(self::PARTS));
            }
        }
    }
};
