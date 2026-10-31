<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TemplateExecutions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Models\Platform\Organization;
use App\Models\Reselling\ResalePeriod;
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use Carbon\CarbonImmutable;

/**
 * Ausführungsstand je Serienvorlage aus dem Belegspiegel (MVP-1441): jede
 * Rechnung kennt die Vorlage, aus der sie entstand. Ist die nächste Ausführung
 * bekannt, gilt die Rechnung im Fenster um „nächste minus Intervall“ als die der
 * letzten Ausführung; sonst (pausierte Vorlage) die jüngste. Das Datum der
 * letzten Ausführung liefert Lexoffice trotz Doku nicht mit.
 */
final class TemplateExecutions {
    public const LOOKBACK_MONTHS = 15;

    private const WINDOW_DAYS = 14;

    public function __construct(private readonly InvoiceMirror $mirror) {}

    /**
     * @param  iterable<PlanTemplate>  $templates
     * @return array<string, TemplateExecution> Vorlagenschlüssel → Stand
     */
    public function statesFor(Organization $organization, iterable $templates): array {
        /** @var array<string, PlanTemplate> $wanted */
        $wanted = [];
        foreach ($templates as $template) {
            $wanted[$template->key] = $template;
        }
        if ($wanted === []) {
            return [];
        }
        $since = ResalePeriod::today()->subMonths(self::LOOKBACK_MONTHS);
        /** @var array<string, array<string, array{number: string|null, date: CarbonImmutable|null, paid: bool}>> $vouchers Vorlage → Beleg → Stand */
        $vouchers = [];
        foreach ($this->mirror->linesFor($organization, null, $since, null, false) as $line) {
            /** @var MirrorLine $line */
            if ($line->planKey === null || ! isset($wanted[$line->planKey]) || $line->isCreditNote
                || in_array($line->voucherStatus, [MirrorLine::STATUS_VOIDED, MirrorLine::STATUS_DRAFT], true)) {
                continue;
            }
            $vouchers[$line->planKey][$line->voucherKey] ??= ['number' => $line->voucherNumber, 'date' => $line->voucherDate, 'paid' => $line->voucherStatus === MirrorLine::STATUS_PAID];
        }
        $states = [];
        foreach ($wanted as $key => $template) {
            $expected = $template->nextExecutionOn !== null && $template->intervalMonths() > 0 ? $template->nextExecutionOn->subMonths($template->intervalMonths()) : null;
            $best = null;
            $bestDistance = null;
            foreach ($vouchers[$key] ?? [] as $voucher) {
                if ($voucher['date'] === null) {
                    continue;
                }
                if ($expected !== null) {
                    $distance = abs($voucher['date']->diffInDays($expected));
                    if ($distance > self::WINDOW_DAYS) {
                        continue;
                    }
                    if ($best === null || $bestDistance === null || $distance < $bestDistance) {
                        $best = $voucher;
                        $bestDistance = $distance;
                    }
                } elseif ($best === null || $voucher['date']->greaterThan($best['date'])) {
                    $best = $voucher;
                }
            }
            $states[$key] = $best === null
                ? new TemplateExecution(TemplateExecution::MISSING, null, null, $expected)
                : new TemplateExecution($best['paid'] ? TemplateExecution::PAID : TemplateExecution::OPEN, $best['number'], $best['date'], $expected);
        }

        return $states;
    }
}
