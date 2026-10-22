<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingDeletionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use App\Models\Platform\User;
use App\Models\Whistleblowing\{CaseTombstone, WhistleblowingCase};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Storage};
use RuntimeException;

/**
 * Kontrollierte Loeschung eines Falls (Abschnitt 16 / 25). Primaer per
 * Crypto-Shredding (per-Fall-DEK vernichten) – damit ist der Inhalt auch in
 * unveraenderlichen Backups wertlos – ergaenzt um das Loeschen der Inhalts-Zeilen
 * und Dateien. Es verbleibt ein inhaltsfreier Tombstone als Nachweis.
 *
 * Eine Loeschung ist nur aus `retention_review` zulaessig; eine aktive
 * Loeschsperre (`legal_hold`) wird ATOMAR in derselben Transaktion geprueft
 * (gegen TOCTOU, Abschnitt 25).
 */
class WhistleblowingDeletionService {
    public function __construct(
        private readonly WhistleblowingEventService $events,
        private readonly WhistleblowingTombstoneLedger $ledger,
    ) {}

    public function delete(WhistleblowingCase $case, User $actor): CaseTombstone {
        $disk = Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'));

        /** @var list<string> $storageKeys */
        $storageKeys = $case->attachments()->pluck('storage_key')->all();

        $tombstone = DB::transaction(function () use ($case, $actor): CaseTombstone {
            // Zeile sperren und Zustand FRISCH lesen (TOCTOU gegen legal_hold).
            /** @var WhistleblowingCase $locked */
            $locked = WhistleblowingCase::withoutGlobalScopes()
                ->whereKey($case->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $status = $locked->status;

            if ($status === CaseStatus::LegalHold) {
                throw new RuntimeException('Fall steht unter Loeschsperre (Legal Hold).');
            }
            if ($status !== CaseStatus::RetentionReview) {
                throw new RuntimeException('Loeschung nur nach Aufbewahrungspruefung (retention_review) zulaessig.');
            }

            // Event ZUERST (Inhalte noch vorhanden); sein Hash wandert in den Tombstone.
            $event = $this->events->record($locked, WhistleblowingEventService::CASE_DELETED, $actor);

            $tombstone = CaseTombstone::create([
                'organization_id' => $locked->getAttribute('organization_id'),
                'case_number' => $locked->getAttribute('case_number'),
                'public_id' => $locked->getAttribute('public_id'),
                'period_from' => $locked->getAttribute('occurred_from'),
                'period_to' => $locked->getAttribute('occurred_to'),
                'closed_category' => null,
                'deleted_at' => Carbon::now(),
                'audit_hash' => $event->getAttribute('hash'),
            ]);

            $this->shred($locked);

            return $tombstone;
        });

        // Dateien nach erfolgreichem Commit loeschen (nicht transaktional).
        foreach ($storageKeys as $key) {
            $disk->delete($key);
        }
        try {
            $this->ledger->append($tombstone);
        } catch (\Throwable $e) {
            // Die Löschung steht; ohne Ledger-Zeile fehlt nur der Nachweis für einen späteren Restore — melden, nicht scheitern.
            report($e);
        }

        return $tombstone;
    }

    /**
     * Löschungen nach einem Restore erneut anwenden (Konzept §16, Vollscan
     * 2026-09-15 Top 9b): ein Fall, den der Ledger als gelöscht führt, den
     * die zurückgespielte Datenbank aber noch mit Inhalt trägt, wird ohne
     * Status- und Sperrprüfung geschreddert — die Entscheidung fiel schon vor
     * dem Restore. Fehlt der Grabstein in der Datenbank, entsteht er aus dem
     * Ledger neu.
     *
     * @return array{shredded: int, tombstones_restored: int, unknown: int}
     */
    public function reapplyLedger(?string $path = null, bool $dryRun = false): array {
        $result = ['shredded' => 0, 'tombstones_restored' => 0, 'unknown' => 0];
        $disk = Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'));

        foreach ($this->ledger->entries($path) as $entry) {
            $case = WhistleblowingCase::withoutGlobalScopes()
                ->where('organization_id', $entry['organization_id'])
                ->where('public_id', $entry['public_id'])
                ->first();
            if ($case === null) {
                $result['unknown']++;

                continue;
            }
            $hasTombstone = CaseTombstone::query()->withoutGlobalScopes()
                ->where('organization_id', $entry['organization_id'])
                ->where('public_id', $entry['public_id'])
                ->exists();
            $needsShred = $case->status !== CaseStatus::Deleted;
            if ($dryRun) {
                $result['shredded'] += $needsShred ? 1 : 0;
                $result['tombstones_restored'] += $hasTombstone ? 0 : 1;

                continue;
            }

            /** @var list<string> $storageKeys */
            $storageKeys = $needsShred ? $case->attachments()->pluck('storage_key')->all() : [];
            DB::transaction(function () use ($case, $entry, $needsShred, $hasTombstone): void {
                /** @var WhistleblowingCase $locked */
                $locked = WhistleblowingCase::withoutGlobalScopes()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
                if ($needsShred) {
                    $this->events->record($locked, WhistleblowingEventService::CASE_DELETED, null, ['reason' => 'tombstone_ledger']);
                    $this->shred($locked);
                }
                if (! $hasTombstone) {
                    CaseTombstone::create([
                        'organization_id' => $entry['organization_id'],
                        'case_number' => $entry['case_number'],
                        'public_id' => $entry['public_id'],
                        'period_from' => $entry['period_from'],
                        'period_to' => $entry['period_to'],
                        'closed_category' => null,
                        'deleted_at' => $entry['deleted_at'] !== null ? Carbon::parse($entry['deleted_at']) : Carbon::now(),
                        'audit_hash' => $entry['audit_hash'],
                    ]);
                }
            });
            foreach ($storageKeys as $key) {
                $disk->delete($key);
            }
            $result['shredded'] += $needsShred ? 1 : 0;
            $result['tombstones_restored'] += $hasTombstone ? 0 : 1;
        }

        return $result;
    }

    private function shred(WhistleblowingCase $locked): void {
        // Crypto-Shredding + Inhalte entfernen.
        $locked->shredDek();
        DB::table('whistleblowing_messages')->where('case_id', $locked->getKey())->delete();
        DB::table('whistleblowing_attachments')->where('case_id', $locked->getKey())->delete();
        // Personenbezuege rundherum (Sicherheitsaudit 2026-09-13): Beschuldigte,
        // Konflikte, Bearbeiterzuweisungen, Fristen und Notfallfreigaben blieben
        // stehen. Ihre Inhalte sind durch die Schluesselvernichtung zwar
        // unlesbar, die Verknuepfung "diese Person, dieser Fall" blieb aber als
        // Metadatum erhalten — nach einer Loeschung darf nur der Grabstein
        // uebrig sein. Das Ereignisprotokoll bleibt bewusst: sein Hash traegt
        // den Grabstein.
        foreach ([
            'whistleblowing_case_subjects',
            'whistleblowing_case_conflicts',
            'whistleblowing_case_assignments',
            'whistleblowing_deadline_reminders',
            'whistleblowing_emergency_grants',
        ] as $table) {
            DB::table($table)->where('case_id', $locked->getKey())->delete();
        }
        DB::table('whistleblowing_cases')->where('id', $locked->getKey())->update([
            'subject_ciphertext' => null,
            'description_ciphertext' => null,
            'contact_ciphertext' => null,
            'status' => CaseStatus::Deleted->value,
        ]);
    }
}
