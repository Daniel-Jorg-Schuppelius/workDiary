<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RegisterFingerprint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\Platform\Organization;
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Cache, DB};

/**
 * Fingerabdrücke für Teilstände des Registers (Abgleichsübersicht je Empfänger,
 * Prüffälle je Abo): die Rohzeilen von Perioden und Bezügen ohne Modelle, je
 * Request und Registerstand einmal gelesen, und Hashes über Abo und Position.
 * Ein Teilstand gilt, solange der Fingerabdruck seiner Eingaben passt.
 *
 * @phpstan-type RegisterRows array{periods: array<int, list<string>>, links: array<int, list<string>>, links_by_line: array<string, list<string>>, lines: array<int, array<string, true>>, months_by_line: array<string, float>}
 */
final class RegisterFingerprint {
    /**
     * Rohzeilen der Perioden und Bezüge einer Organisation, nach Abo und nach Position gruppiert.
     *
     * @return RegisterRows
     */
    public static function rows(int $organizationId): array {
        return app(ResaleRequestMemo::class)->remember('register-rows:' . $organizationId . ':' . ResaleCacheStamp::current($organizationId), static function () use ($organizationId): array {
            $periods = [];
            $subscriptionOfPeriod = [];
            foreach (DB::table('resale_periods')->where('organization_id', $organizationId)->orderBy('id')->get() as $period) {
                $periods[(int) $period->subscription_id][] = self::raw((array) $period);
                $subscriptionOfPeriod[(int) $period->id] = (int) $period->subscription_id;
            }
            $links = [];
            $linksByLine = [];
            $lines = [];
            $monthsByLine = [];
            foreach (DB::table('resale_period_links')->where('organization_id', $organizationId)->orderBy('id')->get() as $link) {
                $raw = self::raw((array) $link);
                $identity = MirrorLine::identityOf((string) $link->linkable_type, (int) $link->linkable_id);
                $subscriptionId = $subscriptionOfPeriod[(int) $link->period_id] ?? (int) $link->subscription_id;
                $links[$subscriptionId][] = $raw;
                $linksByLine[$identity][] = $raw;
                $lines[$subscriptionId][$identity] = true;
                $monthsByLine[$identity] = ($monthsByLine[$identity] ?? 0.0) + (float) $link->months;
            }

            return ['periods' => $periods, 'links' => $links, 'links_by_line' => $linksByLine, 'lines' => $lines, 'months_by_line' => $monthsByLine];
        });
    }

    /**
     * Positionen hinter allen Bezügen der Organisation: aus der Gesamtladung des Spiegels, fehlende
     * (etwa stornierte Belege) einzeln nachgeladen — der Fingerabdruck soll jede verknüpfte Position kennen.
     *
     * @param  \Closure(): iterable<MirrorLine>  $allLines  Gesamtladung, nur beim ersten Aufruf je Request gebraucht
     * @return array<string, MirrorLine> Kennung → Position
     */
    public static function linkedLines(InvoiceMirror $mirror, Organization $organization, \Closure $allLines): array {
        return app(ResaleRequestMemo::class)->remember('register-lines:' . $organization->id . ':' . ResaleCacheStamp::current((int) $organization->id), static function () use ($mirror, $organization, $allLines): array {
            $index = [];
            foreach ($allLines() as $line) {
                $index[$line->identity()] = $line;
            }
            $missing = [];
            foreach (array_keys(self::rows((int) $organization->id)['links_by_line']) as $identity) {
                if (! isset($index[$identity])) {
                    $missing[] = $identity;
                }
            }

            return $missing === [] ? $index : $index + $mirror->linesByIdentity($organization, $missing);
        });
    }

    /**
     * Fingerabdruck über alle Rohzeilen dieser Tabellen der Organisation — für Zähler, die an wenigen
     * kleinen Tabellen hängen. Je Request und Registerstand einmal gelesen.
     *
     * @param  list<string>  $tables
     */
    public static function tables(int $organizationId, array $tables): string {
        $memo = app(ResaleRequestMemo::class);
        $stamp = ResaleCacheStamp::current($organizationId);
        $parts = [];
        foreach ($tables as $table) {
            $parts[] = $table . ':' . $memo->remember('register-table:' . $table . ':' . $organizationId . ':' . $stamp, static function () use ($table, $organizationId): string {
                $rows = [];
                foreach (DB::table($table)->where('organization_id', $organizationId)->orderBy('id')->get() as $row) {
                    $rows[] = self::raw((array) $row);
                }

                return self::hash($rows);
            });
        }

        return self::hash($parts);
    }

    /**
     * Wert aus dem Cache, solange der Fingerabdruck seiner Eingaben passt; sonst neu gerechnet und abgelegt.
     * Nur Skalare und Arrays.
     *
     * @template T of int|float|string|bool|array<array-key, mixed>
     *
     * @param  \Closure(): T  $compute
     * @return T
     */
    public static function remember(string $key, string $fingerprint, int $ttl, \Closure $compute): mixed {
        $cached = Cache::get($key);
        if (is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint && array_key_exists('value', $cached)) {
            return $cached['value'];
        }
        $value = $compute();
        Cache::put($key, ['fingerprint' => $fingerprint, 'value' => $value], $ttl);

        return $value;
    }

    /** @param  list<string>  $parts */
    public static function hash(array $parts): string {
        return (string) CryptoHelper::hash(implode("\n", $parts));
    }

    /**
     * Rohwerte einer Zeile als Zeichenkette: Spalte und Wert, `null` von der leeren Zeichenkette unterscheidbar.
     *
     * @param  array<array-key, mixed>  $values
     */
    public static function raw(array $values): string {
        $parts = [];
        foreach ($values as $column => $value) {
            $parts[] = $column . '=' . ($value === null ? "\0" : (is_scalar($value) ? (string) $value : serialize($value)));
        }

        return implode("\x1f", $parts);
    }

    /** Rohwerte eines Modells samt geladener Relationen (Halter, Anbieterabrechnung). */
    public static function model(Model $model): string {
        $parts = [self::raw($model->getAttributes())];
        foreach ($model->getRelations() as $name => $related) {
            if ($related instanceof Model) {
                $parts[] = $name . ':' . self::model($related);
            } elseif ($related instanceof Collection) {
                foreach ($related as $item) {
                    if ($item instanceof Model) {
                        $parts[] = $name . ':' . self::model($item);
                    }
                }
            }
        }

        return implode('|', $parts);
    }

    /** Alles an einer Position, was Bilanz, Prüffall und Abweichung beeinflusst — ohne Adressen und Empfängernamen. */
    public static function line(MirrorLine $line): string {
        return implode("\x1f", [
            $line->sourceKey, $line->voucherKey, (string) $line->voucherNumber, (string) $line->voucherDate?->format('Y-m-d'), $line->voucherStatus, $line->isCreditNote ? '1' : '0',
            (string) $line->recipientCustomerId, $line->recipientKey, (string) $line->articleKey, (string) $line->articleName, $line->articleIsLicence ? '1' : '0',
            $line->name, (string) $line->description, (string) $line->quantity, (string) $line->unitName, $line->unitNet->getAmount(), $line->totalNet->getAmount(), $line->currency->value,
            (string) $line->serviceFrom?->format('Y-m-d'), (string) $line->serviceTo?->format('Y-m-d'), (string) $line->voucherText, (string) $line->position, (string) $line->planKey,
        ]);
    }
}
