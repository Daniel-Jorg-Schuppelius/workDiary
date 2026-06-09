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
use App\Models\User;
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
    public function __construct(private readonly WhistleblowingEventService $events) {}

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

            // Crypto-Shredding + Inhalte entfernen.
            $locked->shredDek();
            DB::table('whistleblowing_messages')->where('case_id', $locked->getKey())->delete();
            DB::table('whistleblowing_attachments')->where('case_id', $locked->getKey())->delete();
            DB::table('whistleblowing_cases')->where('id', $locked->getKey())->update([
                'subject_ciphertext' => null,
                'description_ciphertext' => null,
                'contact_ciphertext' => null,
                'status' => CaseStatus::Deleted->value,
            ]);

            return $tombstone;
        });

        // Dateien nach erfolgreichem Commit loeschen (nicht transaktional).
        foreach ($storageKeys as $key) {
            $disk->delete($key);
        }

        return $tombstone;
    }
}
