<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpFeedbackCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\HelpView;
use App\Modules\Attributes\BelongsToModule;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Rückmeldungen „War das hilfreich?“ je Hilfethema (MVP-1623): Die Texte
 * liegen im Repository, ausgewertet wird deshalb plattformweit und anonym —
 * Themen mit den meisten „nein“ zuerst.
 */
#[BelongsToModule('help')]
class HelpFeedbackCommand extends Command {
    protected $signature = 'help:feedback {--days=90 : Zeitraum in Tagen} {--limit=20 : Anzahl der Themen}';

    protected $description = 'Zeigt die Rückmeldungen „War das hilfreich?“ je Hilfethema, wenig hilfreiche zuerst.';

    public function handle(): int {
        $days = max(1, (int) $this->option('days'));
        $rows = HelpView::query()
            ->whereNotNull('was_helpful')
            ->where('created_at', '>=', CarbonImmutable::now()->subDays($days))
            ->groupBy('topic')
            ->selectRaw('topic, SUM(CASE WHEN was_helpful THEN 1 ELSE 0 END) AS helpful, SUM(CASE WHEN was_helpful THEN 0 ELSE 1 END) AS not_helpful')
            ->orderByDesc('not_helpful')
            ->orderBy('topic')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($rows->isEmpty()) {
            $this->info(sprintf('Keine Rückmeldungen in den letzten %d Tagen.', $days));

            return self::SUCCESS;
        }

        $this->table(['Thema', 'hilfreich', 'nicht hilfreich'], $rows->map(static fn ($row): array => [
            (string) $row->getAttribute('topic'),
            (int) $row->getAttribute('helpful'),
            (int) $row->getAttribute('not_helpful'),
        ])->all());

        return self::SUCCESS;
    }
}
