<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TidyProtocolPdfsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Protocol;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Protocol\ProtocolEventType;
use App\Models\Attachments\Attachment;
use App\Models\Platform\Organization;
use App\Models\Protocol\{Protocol, ProtocolEvent};
use App\Services\Protocol\ProtocolPdfRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Altbestand der Protokoll-PDFs (MVP-1627): Vor MVP-1572 lag jedes erzeugte PDF
 * nur unter `protocols/{org}` — auch Entwürfe, und ohne Eintrag im
 * Löschregister. Der Befehl überführt je abgeschlossenem Protokoll den Nachweis
 * (die zuletzt protokollierte Fassung) in einen Anhang und löscht übrige
 * Dateien. Was ein Anhang referenziert, bleibt immer liegen. Idempotent.
 */
class TidyProtocolPdfsCommand extends Command {
    use IteratesOrganizations;

    private const string PATTERN = '/^(\d+)_r\d+_[0-9a-f]{8}(?:_s[0-9a-f]{6})?\.pdf$/';

    protected $signature = 'protocols:tidy-pdfs {--dry-run : Nur anzeigen, nichts ändern} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Überführt alte Protokoll-PDFs in Anhänge und entfernt nicht referenzierte Fassungen.';

    public function handle(): int {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(ProtocolPdfRenderer::DISK);
        $totals = ['kept' => 0, 'registered' => 0, 'deleted' => 0, 'unknown' => 0];

        $failures = $this->forEachOrganization(function (Organization $organization) use ($disk, $dryRun, &$totals): void {
            $byProtocol = [];
            foreach ($disk->allFiles('protocols/' . $organization->id) as $file) {
                if (preg_match(self::PATTERN, basename($file), $m) !== 1) {
                    $totals['unknown']++;

                    continue;
                }
                $byProtocol[(int) $m[1]][] = $file;
            }

            foreach ($byProtocol as $protocolId => $files) {
                $protocol = Protocol::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->find($protocolId);
                $referenced = Attachment::query()->withoutGlobalScopes()
                    ->where('disk', ProtocolPdfRenderer::DISK)
                    ->whereIn('path', $files)
                    ->pluck('path')
                    ->all();
                $evidence = $protocol instanceof Protocol && $protocol->status->isImmutable()
                    && ! $protocol->attachments()->where('meta_type', Protocol::META_PDF)->exists()
                        ? $this->evidenceFile($protocol, array_values(array_diff($files, $referenced)))
                        : null;

                foreach ($files as $file) {
                    if (in_array($file, $referenced, true)) {
                        $totals['kept']++;
                    } elseif ($file === $evidence) {
                        $totals['registered']++;
                        $this->line(($dryRun ? '[Probelauf] ' : '') . 'Anhang: ' . $file, null, 'v');
                        if (! $dryRun) {
                            $protocol->attachments()->create([
                                'organization_id' => $protocol->organization_id,
                                'user_id' => null,
                                'disk' => ProtocolPdfRenderer::DISK,
                                'path' => $file,
                                'original_name' => sprintf('%s v%d.pdf', $protocol->type->label(), $protocol->revision),
                                'mime' => 'application/pdf',
                                'size' => $disk->size($file),
                                'meta_type' => Protocol::META_PDF,
                            ]);
                        }
                    } else {
                        $totals['deleted']++;
                        $this->line(($dryRun ? '[Probelauf] ' : '') . 'Entfernt: ' . $file, null, 'v');
                        if (! $dryRun) {
                            $disk->delete($file);
                        }
                    }
                }
            }
        });

        $this->info(sprintf(
            '%sProtokoll-PDFs: %d referenziert, %d als Anhang übernommen, %d entfernt, %d unbekannte Dateien unverändert.',
            $dryRun ? '[Probelauf] ' : '',
            $totals['kept'],
            $totals['registered'],
            $totals['deleted'],
            $totals['unknown'],
        ));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Der Nachweis ist die zuletzt im Journal protokollierte Fassung; fehlt sie,
     * die jüngste Datei des Protokolls.
     *
     * @param  list<string>  $candidates
     */
    private function evidenceFile(Protocol $protocol, array $candidates): ?string {
        if ($candidates === []) {
            return null;
        }
        $rendered = ProtocolEvent::query()->withoutGlobalScopes()
            ->where('protocol_id', $protocol->id)
            ->where('event', ProtocolEventType::PdfRendered)
            ->orderByDesc('id')
            ->get(['payload'])
            ->map(static fn (ProtocolEvent $event): string => (string) ($event->payload['path'] ?? ''))
            ->first(static fn (string $path): bool => in_array($path, $candidates, true));
        if (is_string($rendered)) {
            return $rendered;
        }

        $disk = Storage::disk(ProtocolPdfRenderer::DISK);
        usort($candidates, static fn (string $a, string $b): int => $disk->lastModified($b) <=> $disk->lastModified($a));

        return $candidates[0];
    }
}
