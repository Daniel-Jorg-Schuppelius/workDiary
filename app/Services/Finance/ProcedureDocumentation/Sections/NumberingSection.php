<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberingSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Enums\Numbering\NumberScope;
use App\Models\Organization;
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};

/**
 * Nummernkreise je Organisation: Format (Präfix/Jahr/Stellen/Reset/Start),
 * Nummernhoheit (lokal oder externes Buchhaltungssystem) und die nächste
 * Nummer als Belegbeispiel — Quelle NumberSequenceService/NumberFormat.
 */
final class NumberingSection implements ProcedureSection {
    use FormatsSectionValues;

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly NumberAuthority $authority,
    ) {}

    public function key(): string {
        return 'numbering';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.numbering');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $orgId = (int) $organization->id;
        $rows = [];
        foreach (NumberScope::cases() as $scope) {
            $format = $this->sequences->resolveFormat($orgId, $scope);
            $external = $this->authority->isExternal($orgId, $scope);
            $rows[] = [
                $scope->label(),
                $scope->value,
                (string) __($external ? 'procedure-documentation.numbering.external' : 'procedure-documentation.numbering.local'),
                $this->text($format->prefix),
                $this->yesNo((bool) $format->include_year),
                $this->text($format->padding),
                $this->yesNo((bool) $format->reset_per_year),
                $this->text($format->starts_at),
                $this->sequences->peekNext($orgId, $scope),
            ];
        }

        return [
            'tables' => [
                'formats' => [
                    'title' => (string) __('procedure-documentation.numbering.table'),
                    'columns' => [
                        (string) __('procedure-documentation.numbering.col.scope'),
                        (string) __('procedure-documentation.numbering.col.key'),
                        (string) __('procedure-documentation.numbering.col.authority'),
                        (string) __('procedure-documentation.numbering.col.prefix'),
                        (string) __('procedure-documentation.numbering.col.year'),
                        (string) __('procedure-documentation.numbering.col.padding'),
                        (string) __('procedure-documentation.numbering.col.reset'),
                        (string) __('procedure-documentation.numbering.col.start'),
                        (string) __('procedure-documentation.numbering.col.next'),
                    ],
                    'rows' => $rows,
                ],
            ],
        ];
    }
}
