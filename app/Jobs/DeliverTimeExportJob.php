<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliverTimeExportJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TimeExport\TimeExportStatus;
use App\Mail\TimeExportDeliveryMail;
use App\Models\{TimeExport, TimeExportDeliveryConfig, TimeExportEvent};
use App\Services\TimeExport\{TimeExportService, TimeExportSftpUploader};
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Throwable;

/**
 * Automatische Lieferung eines fertigen Zeit-Exports (A21 · MVP-019).
 *
 * Wird nach {@see TimeExportService::build()} angestoßen, wenn für
 * Organisation × Profil eine {@see TimeExportDeliveryConfig} mit aktivem
 * Kanal existiert. Idempotent je Export UND Kanal: der Liefernachweis in
 * `time_exports.auto_delivery` (wann/wohin, geschrieben genau einmal pro
 * Kanal) verhindert Doppel-Versand bei Queue-Retries; ein teilweiser
 * Fehlschlag (z. B. Mail ok, SFTP down) wiederholt nur den offenen Kanal.
 *
 * Nachweis/Fehlerpfad nach Bestandsmuster (append-only time_export_events):
 * `export.delivered_auto` je Kanal, `export.delivery_failed` je Fehlversuch.
 * Sind alle aktiven Kanäle zugestellt, wechselt der Export via
 * {@see TimeExportService::markDeliveredBySystem()} auf `delivered` (Akteur System).
 * Die Export-Datei selbst wird nie mutiert (GoBD) — versendet werden exakt
 * die gespeicherten, gehashten Bytes.
 */
class DeliverTimeExportJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;
    public int $tries = 4;

    /** @var list<int> Backoff in Sekunden je Wiederholung. */
    public array $backoff = [30, 300, 1800];

    public function __construct(public readonly int $timeExportId) {}

    public function handle(TimeExportSftpUploader $sftpUploader): void {
        // Bewusst ohne Global Scopes: der Queue-Worker hat keine (bzw. nach
        // der Org-Hygiene eine frische) currentOrganization-Bindung.
        $export = TimeExport::query()->withoutGlobalScopes()->find($this->timeExportId);
        if ($export === null || $export->status !== TimeExportStatus::Ready) {
            // Bereits (manuell/automatisch) geliefert, ersetzt oder abgelehnt.
            return;
        }

        $config = TimeExportDeliveryConfig::activeFor((int) $export->organization_id, $export->profile);
        if ($config === null) {
            return;
        }

        $path = (string) ($export->file_path ?? '');
        $disk = Storage::disk((string) config('exports.storage.disk', 'local'));
        if ($path === '' || ! $disk->exists($path)) {
            $this->logEvent($export, 'export.delivery_failed', (string) __('wage_types.delivery.file_missing'), ['path' => $path]);

            return;
        }

        $bytes = (string) $disk->get($path);
        $filename = sprintf('%s-%s.%s', $export->profile, $export->periodLabel(), $export->file_format ?? 'csv');

        /** @var array<string, array<string, mixed>> $evidence */
        $evidence = (array) ($export->auto_delivery ?? []);
        $errors = [];

        // ── Kanal E-Mail ────────────────────────────────────────────────
        $mailWanted = $config->mail_enabled && $config->mailRecipients() !== [];
        if ($mailWanted && ! isset($evidence['mail'])) {
            try {
                Mail::to($config->mailRecipients())->send(new TimeExportDeliveryMail($export, $bytes, $filename));

                $evidence['mail'] = [
                    'at' => CarbonImmutable::now()->toIso8601String(),
                    'to' => $config->mailRecipients(),
                ];
                $this->persistEvidence($export, $evidence);
                $this->logEvent($export, 'export.delivered_auto', null, [
                    'channel' => 'mail',
                    'to' => $config->mailRecipients(),
                ]);
            } catch (Throwable $e) {
                $errors[] = 'mail: ' . $e->getMessage();
                $this->logEvent($export, 'export.delivery_failed', Str::limit($e->getMessage(), 480, '…'), ['channel' => 'mail']);
            }
        }

        // ── Kanal SFTP ──────────────────────────────────────────────────
        $sftpWanted = $config->sftp_enabled && (string) $config->sftp_host !== '' && (string) $config->sftp_username !== '';
        if ($sftpWanted && ! isset($evidence['sftp'])) {
            try {
                $target = $sftpUploader->upload($config, $bytes, $filename);

                $evidence['sftp'] = [
                    'at' => CarbonImmutable::now()->toIso8601String(),
                    'target' => $target,
                ];
                $this->persistEvidence($export, $evidence);
                $this->logEvent($export, 'export.delivered_auto', null, [
                    'channel' => 'sftp',
                    'target' => $target,
                ]);
            } catch (Throwable $e) {
                $errors[] = 'sftp: ' . $e->getMessage();
                $this->logEvent($export, 'export.delivery_failed', Str::limit($e->getMessage(), 480, '…'), ['channel' => 'sftp']);
            }
        }

        if ($errors !== []) {
            // Queue-Retry: bereits gelieferte Kanäle bleiben über den
            // Nachweis geschützt, nur offene Kanäle laufen erneut.
            throw new \RuntimeException('time export delivery failed: ' . implode('; ', $errors));
        }

        // Alle aktiven Kanäle zugestellt → Export als übermittelt markieren.
        $done = (! $mailWanted || isset($evidence['mail'])) && (! $sftpWanted || isset($evidence['sftp']));
        if ($done && $evidence !== []) {
            $export->refresh();
            if ($export->status === TimeExportStatus::Ready) {
                app(TimeExportService::class)->markDeliveredBySystem(
                    $export,
                    (string) __('wage_types.delivery.note_auto', ['channels' => implode(', ', array_keys($evidence))]),
                );
            }
        }
    }

    /** Endgültiger Fehlschlag nach allen Versuchen: Abschluss protokollieren. */
    public function failed(?Throwable $e): void {
        $export = TimeExport::query()->withoutGlobalScopes()->find($this->timeExportId);
        if ($export === null) {
            return;
        }

        $this->logEvent(
            $export,
            'export.delivery_failed',
            (string) __('wage_types.delivery.abandoned'),
            ['final' => true, 'error' => $e !== null ? Str::limit($e->getMessage(), 480, '…') : null],
        );
    }

    /**
     * Liefernachweis am Export-Datensatz festschreiben (Idempotenz-Anker) —
     * nur die auto_delivery-Spalte, Datei/Hash bleiben unangetastet (GoBD).
     *
     * @param  array<string, array<string, mixed>>  $evidence
     */
    private function persistEvidence(TimeExport $export, array $evidence): void {
        $export->forceFill(['auto_delivery' => $evidence])->save();
    }

    /** @param  array<string, mixed>|null  $payload */
    private function logEvent(TimeExport $export, string $event, ?string $note, ?array $payload = null): void {
        TimeExportEvent::query()->create([
            'time_export_id' => $export->id,
            'event' => $event,
            'actor_user_id' => null, // System (Queue), kein menschlicher Akteur
            'note' => $note,
            'payload' => $payload,
        ]);
    }
}
