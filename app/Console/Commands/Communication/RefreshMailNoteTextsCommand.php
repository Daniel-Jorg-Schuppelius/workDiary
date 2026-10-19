<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RefreshMailNoteTextsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Communication;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, MailTextRefreshOutcome};
use App\Models\Communication\CommunicationNote;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\InvoiceInquiry;
use App\Services\Communication\Mail\MailNoteTextRefresher;
use App\Services\Mail\MailIntakeService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Texte eingegangener E-Mail-Notizen aus dem Postfach erneuern (Befund
 * 2026-10-19: Bestand ohne Zeilenstruktur). Einmaliger Lauf nach dem
 * Deployment; jede Notiz liest ihre Mail erneut über die Message-ID.
 */
class RefreshMailNoteTextsCommand extends Command {
    protected $signature = 'communication-notes:refresh-mail-text
        {--org= : nur diese Organisation (ID)}
        {--since= : nur Notizen mit Zeitpunkt ab diesem Datum}
        {--dry-run : nur zählen, nichts schreiben}';

    protected $description = 'Liest eingegangene E-Mail-Notizen erneut aus dem Postfach und setzt ihren Text neu (Zeilenstruktur, Entities)';

    public function handle(MailNoteTextRefresher $refresher): int {
        $dryRun = (bool) $this->option('dry-run');
        $alias = (new CommunicationNote)->getMorphClass();

        $query = CommunicationNote::query()->withoutGlobalScopes()
            ->where('type', CommunicationNoteType::Email->value)
            ->where('direction', CommunicationDirection::Inbound->value)
            ->where(function ($q) use ($alias): void {
                $q->whereIn('id', ExternalReference::query()->withoutGlobalScopes()
                    ->where('plugin_id', MailIntakeService::PLUGIN_ID)
                    ->where('external_type', MailIntakeService::EXTERNAL_TYPE)
                    ->where('referenceable_type', $alias)
                    ->select('referenceable_id'))
                    ->orWhereIn('id', InvoiceInquiry::query()->withoutGlobalScopes()->whereNotNull('communication_note_id')->select('communication_note_id'));
            })
            ->orderBy('id');
        if ($this->option('org') !== null) {
            $query->where('organization_id', (int) $this->option('org'));
        }
        if ($this->option('since') !== null) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse((string) $this->option('since'))->startOfDay());
        }

        $counts = array_fill_keys(array_map(static fn (MailTextRefreshOutcome $o): string => $o->value, MailTextRefreshOutcome::cases()), 0);
        $failed = 0;
        foreach ($query->cursor() as $note) {
            try {
                $outcome = $refresher->refresh($note, $dryRun);
                $counts[$outcome->value]++;
                if ($outcome === MailTextRefreshOutcome::Refreshed) {
                    $this->components->twoColumnDetail('#' . $note->getKey() . ' ' . $note->subject, $dryRun ? 'würde erneuert' : 'erneuert');
                }
            } catch (Throwable $e) {
                $failed++;
                $this->components->twoColumnDetail('#' . $note->getKey() . ' ' . $note->subject, 'Fehler: ' . $e->getMessage());
            }
        }

        $this->components->info(sprintf(
            '%s: %d erneuert, %d unverändert, %d nicht im Postfach, %d ohne Mail/Postfach, %d Fehler.',
            $dryRun ? 'Probelauf' : 'Erneuert',
            $counts[MailTextRefreshOutcome::Refreshed->value],
            $counts[MailTextRefreshOutcome::Unchanged->value],
            $counts[MailTextRefreshOutcome::NotFound->value],
            $counts[MailTextRefreshOutcome::NotApplicable->value],
            $failed,
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
