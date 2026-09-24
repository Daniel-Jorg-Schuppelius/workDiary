<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxCallReportMailHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fritzbox;

use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Plugins\Fritzbox\Sources\FritzboxCsvParser;
use App\Services\Mail\Contracts\MailIntakeHandler;
use App\Services\Mail\ParsedMessage;

/**
 * FRITZ!Box-Telefonberichte aus der Push-Mail: CSV-taugliche Anhänge werden
 * inhaltsbasiert erkannt (der MIME-Typ der Push-Mails ist unzuverlässig) und
 * in den Anruflisten-Import gereicht — vor Ticket und Inbox. Erneut
 * zugestellte Berichte deduplizieren über die Call-Keys des Imports.
 */
final class FritzboxCallReportMailHandler implements MailIntakeHandler {
    public function __construct(private readonly FritzboxImportService $import) {}

    public function priority(): int {
        return 30;
    }

    public function handle(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string {
        if (! $connection->callreport_intake || $message->attachments === []) {
            return null;
        }
        $config = FritzboxConfig::resolve($organization->id);
        if (! $config['enabled']) {
            return null;
        }

        $processed = 0;
        $duplicates = 0;
        foreach ($message->attachments as $attachment) {
            $isCandidate = str_contains($attachment->mime, 'csv')
                || str_contains($attachment->mime, 'text/plain')
                || str_contains($attachment->mime, 'octet-stream')
                || str_ends_with(strtolower($attachment->filename), '.csv');
            if (! $isCandidate || ! FritzboxCsvParser::looksLikeCallReport($attachment->content)) {
                continue;
            }

            try {
                $result = $this->import->importFromCsv($organization, $attachment->content, $config);
            } catch (\RuntimeException) {
                continue; // doch keine lesbare Anrufliste / kein buchbarer Benutzer → andere Handler
            }

            $result['created'] + $result['linked'] + $result['pending'] > 0 ? $processed++ : $duplicates++;
        }

        return $processed > 0 ? 'callreport' : ($duplicates > 0 ? 'skipped' : null);
    }
}
