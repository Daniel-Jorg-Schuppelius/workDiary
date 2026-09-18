<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingExportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Enums\Whistleblowing\AttachmentScanStatus;
use App\Models\User;
use App\Models\Whistleblowing\WhistleblowingCase;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File;
use CommonToolkit\Helper\FileSystem\FileTypes\ZipFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Autorisierter Export einer einzelnen Fallakte (Abschnitt 17). Erzeugt
 * synchron ein ZIP in einer temporaeren Datei (kein persistentes Artefakt, kein
 * Klartext in Queue-Payloads) und protokolliert ein vollstaendiges Export-Event.
 * Der Exportgrund wird als interne Notiz abgelegt, nicht in die Event-Metadaten.
 *
 * Hinweis: Der Controller streamt die Datei und loescht sie danach
 * (deleteFileAfterSend). Persistierte/versendete Exporte sollten zusaetzlich als
 * passwortgeschuetztes ZIP ausgeliefert werden (Ops, Abschnitt 17).
 */
class WhistleblowingExportService {
    public function __construct(
        private readonly WhistleblowingEventService $events,
        private readonly WhistleblowingMessageService $messages,
        private readonly WhistleblowingAttachmentService $attachments,
    ) {}

    /**
     * @return array{path: string, filename: string}
     */
    public function export(WhistleblowingCase $case, string $reason, User $actor): array {
        $this->messages->addInternalNote($case, 'Export-Grund: ' . $reason, $actor);

        $entries = [];
        $entries[] = ['archiveName' => 'manifest.json', 'content' => $this->json([
            'case_number' => $case->getAttribute('case_number'),
            'public_id' => $case->getAttribute('public_id'),
            'status' => $case->getAttribute('status'),
            'category' => $case->getAttribute('category'),
            'priority' => $case->getAttribute('priority'),
            'occurred_from' => (string) $case->getAttribute('occurred_from'),
            'occurred_to' => (string) $case->getAttribute('occurred_to'),
            'exported_at' => Carbon::now()->toIso8601String(),
            'exported_by' => $actor->getKey(),
            'export_reason' => $reason,
        ])];

        $entries[] = ['archiveName' => 'content.json', 'content' => $this->json([
            'subject' => $case->subject_ciphertext,
            'description' => $case->description_ciphertext,
            'contact' => $case->contact_ciphertext,
        ])];

        $entries[] = ['archiveName' => 'messages.json', 'content' => $this->json(
            $case->messages()->orderBy('sent_at')->get()->map(fn($m) => [
                'author' => $m->author_type->value,
                'visibility' => $m->visibility->value,
                'sent_at' => (string) $m->sent_at,
                'body' => $m->body_ciphertext,
            ])->all()
        )];

        // Nur freigegebene Anhaenge beilegen; Originalnamen entschluesselt.
        $disk = Storage::disk((string) config('whistleblowing.disk', 'whistleblowing'));
        foreach ($case->attachments()->get() as $attachment) {
            if ($attachment->scan_status !== AttachmentScanStatus::Clean) {
                continue;
            }
            if ($disk->exists($attachment->storage_key)) {
                // Verschluesselte Anhaenge (Sicherheitsaudit 2026-09-13) muessen fuer
                // das Paket entschluesselt werden; der Dienst kennt beide Formen.
                $attachment->setRelation('case', $case);
                $entries[] = [
                    'archiveName' => 'files/' . basename((string) $attachment->storage_key),
                    'content' => $this->attachments->contents($attachment),
                ];
            }
        }

        // Toolkit prueft das Schliessen des Archivs (vorher ungeprueft) und raeumt bei Fehlern auf.
        $path = File::createTemp('', 'wbexport_', 'zip');
        try {
            ZipFile::createFromEntries($entries, $path);
        } catch (Throwable $e) {
            File::delete($path);

            throw $e;
        }

        $this->events->record($case, WhistleblowingEventService::CASE_EXPORTED, $actor);

        return [
            'path' => $path,
            'filename' => 'hinweisgeber-fall-' . $case->getAttribute('case_number') . '.zip',
        ];
    }

    private function json(mixed $data): string {
        return JsonHelper::encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
