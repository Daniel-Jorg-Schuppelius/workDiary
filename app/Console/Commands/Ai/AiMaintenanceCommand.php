<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiMaintenanceCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Ai;

use App\Enums\Ai\AiConnectionStatus;
use App\Models\Ai\{AiProviderConnection, AiTextSuggestion};
use App\Models\{Invoice, InvoiceItem, QuoteItem};
use App\Services\Ai\AiConnectionTester;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * KI-Betriebslauf (Feature 025, MVP-411):
 * 1. Health-Check aller aktiven Provider-Verbindungen (Preflight;
 *    wiederholte Fehler führen über HasConnectionHealth zum
 *    Auto-Disable, der ExpiryScanner meldet gestörte Verbindungen).
 * 2. Vorschlags-Hygiene (Feature 084): offene Vorschläge verfallen,
 *    wenn der Beleg den Entwurf verlassen hat oder sie veraltet sind;
 *    entschiedene Vorschläge werden nach Aufbewahrungsfrist gelöscht
 *    (Betriebsdaten, kein Beleg).
 */
class AiMaintenanceCommand extends Command {
    protected $signature = 'ai:maintenance';

    protected $description = 'Prüft KI-Provider-Verbindungen (Preflight/Health) und bereinigt Textvorschläge';

    public function handle(AiConnectionTester $tester): int {
        $checked = 0;
        $failing = 0;

        AiProviderConnection::query()
            ->withoutGlobalScopes()
            ->where('status', AiConnectionStatus::Active)
            ->orderBy('id')
            ->each(function (AiProviderConnection $connection) use ($tester, &$checked, &$failing): void {
                $checked++;
                if (! $tester->test($connection)) {
                    $failing++;
                }
            });

        $expired = $this->expireStaleSuggestions();
        $deleted = $this->deleteDecidedSuggestions();

        $this->info(sprintf(
            'KI-Verbindungen geprüft: %d (%d gestört) · Vorschläge verfallen: %d, bereinigt: %d',
            $checked,
            $failing,
            $expired,
            $deleted,
        ));

        return self::SUCCESS;
    }

    /** Offene Vorschläge ohne Entwurfs-Beleg (oder älter als 30 Tage) verfallen. */
    private function expireStaleSuggestions(): int {
        $expired = 0;

        AiTextSuggestion::query()
            ->withoutGlobalScopes()
            ->where('status', AiTextSuggestion::STATUS_PROPOSED)
            ->orderBy('id')
            ->each(function (AiTextSuggestion $suggestion) use (&$expired): void {
                $subject = $suggestion->subject;

                $stillDraft = match (true) {
                    $subject instanceof InvoiceItem => $subject->invoice?->status === Invoice::STATUS_DRAFT,
                    $subject instanceof QuoteItem => $subject->quote?->status === 'draft',
                    default => false,
                };

                if ($stillDraft && $suggestion->created_at?->gt(Carbon::now()->subDays(30))) {
                    return;
                }

                $suggestion->forceFill(['status' => AiTextSuggestion::STATUS_EXPIRED])->save();
                $expired++;
            });

        return $expired;
    }

    private function deleteDecidedSuggestions(): int {
        $retentionDays = max(1, (int) config('ai.suggestion_retention_days', 30));

        return AiTextSuggestion::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [
                AiTextSuggestion::STATUS_ACCEPTED,
                AiTextSuggestion::STATUS_EDITED,
                AiTextSuggestion::STATUS_REJECTED,
                AiTextSuggestion::STATUS_EXPIRED,
            ])
            ->where('updated_at', '<', Carbon::now()->subDays($retentionDays))
            ->delete();
    }
}
