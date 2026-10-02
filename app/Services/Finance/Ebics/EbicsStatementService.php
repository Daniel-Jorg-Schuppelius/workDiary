<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsStatementService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics;

use App\Models\Finance\EbicsConnection;
use App\Models\Platform\User;
use App\Services\Finance\{BankImportException, BankImportService};
use App\Services\Finance\Ebics\Contracts\EbicsGateway;
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Tagesauszüge per EBICS (MVP-124) in den vorhandenen Bankimport: ab dem
 * letzten abgerufenen Tag (höchstens 30 Tage zurück) bis heute. Doppelte
 * Auszüge erkennt der Import am Datei-Abdruck und überspringt sie.
 */
class EbicsStatementService {
    private const MAX_DAYS_BACK = 30;

    public function __construct(
        private readonly EbicsGateway $gateway,
        private readonly BankImportService $import,
        private readonly EbicsConnectionService $connections,
    ) {}

    /** @return array{statements: int, skipped: int} */
    public function fetch(EbicsConnection $connection, ?User $actor = null): array {
        if (! $connection->isActive()) {
            throw new EbicsException('not_active');
        }
        $today = CarbonImmutable::today();
        $from = $connection->statements_until !== null
            ? CarbonImmutable::parse($connection->statements_until)->addDay()
            : $today->subDays(self::MAX_DAYS_BACK);
        $from = $from->max($today->subDays(self::MAX_DAYS_BACK));
        if ($from->greaterThan($today)) {
            $from = $today;
        }

        try {
            $documents = $this->gateway->downloadStatements($connection, $from, $today);
        } catch (Throwable $e) {
            $this->connections->fail($connection, $actor, 'ebics_statements_fetched', $e);

            throw $e instanceof EbicsException ? $e : new EbicsException('failed', null, class_basename($e));
        }

        $counts = ['statements' => 0, 'skipped' => 0];
        $account = $connection->bankAccount;
        foreach ($documents as $index => $content) {
            try {
                $counts['statements'] += count($this->import->importContent($content, sprintf('ebics-camt053-%s-%d.xml', $today->format('Ymd'), $index + 1), (int) $connection->organization_id, $account, $actor));
            } catch (BankImportException) {
                $counts['skipped']++;
            }
        }

        $connection->forceFill(['statements_until' => $today->subDay()->toDateString(), 'last_fetched_at' => now(), 'last_error' => null])->save();
        $connection->record('ebics_statements_fetched', $counts + ['from' => $from->toDateString(), 'to' => $today->toDateString()], $actor);

        return $counts;
    }
}
