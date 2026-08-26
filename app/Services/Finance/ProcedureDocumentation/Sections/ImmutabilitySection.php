<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImmutabilitySection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Models\Organization;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};
use App\Support\Gobd\GobdLockRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Festschreibung und Unveränderbarkeit: die festschreibungspflichtigen
 * Modelle mit ihrem Mechanismus ({@see GobdLockRegistry}) und die Hash-Ketten
 * (config/audit.php) samt Einträgen und — beim Veröffentlichen — dem
 * vollständig nachgerechneten `audit:verify`-Ergebnis.
 */
final class ImmutabilitySection implements ProcedureSection {
    use FormatsSectionValues;

    public function __construct(private readonly AuditChainVerifier $verifier) {}

    public function key(): string {
        return 'immutability';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.immutability');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $guarded = [];
        foreach (GobdLockRegistry::MODELS as $name => $model) {
            $guarded[] = [$name, $model['table'], GobdLockRegistry::mechanismLabel($model['mechanism'])];
        }

        $chains = [];
        foreach ($this->verifier->chains() as $table => $modelClass) {
            $count = (int) DB::table($table)->count();
            if ($context->verifyChains) {
                $result = $this->verifier->verify($table, $modelClass);
                $check = $result['ok']
                    ? (string) __('procedure-documentation.immutability.verified_ok', ['count' => $result['checked']])
                    : (string) __('procedure-documentation.immutability.verified_failed', [
                        'id' => (string) $result['failed_id'],
                        'reason' => (string) __('procedure-documentation.immutability.reason.' . (string) $result['reason']),
                    ]);
            } else {
                $check = (string) __('procedure-documentation.immutability.not_verified');
            }
            $chains[] = [$table, class_basename($modelClass), (string) $count, $check];
        }

        return [
            'fields' => [
                'verified_at' => $this->field('procedure-documentation.immutability.verified_at', $context->verifyChains ? now()->format('d.m.Y H:i') : null),
                'command' => $this->field('procedure-documentation.immutability.command', 'php artisan audit:verify'),
            ],
            'tables' => [
                'guarded' => [
                    'title' => (string) __('procedure-documentation.immutability.table.guarded'),
                    'columns' => [(string) __('procedure-documentation.immutability.col.model'), (string) __('procedure-documentation.immutability.col.table'), (string) __('procedure-documentation.immutability.col.mechanism')],
                    'rows' => $guarded,
                ],
                'chains' => [
                    'title' => (string) __('procedure-documentation.immutability.table.chains'),
                    'columns' => [(string) __('procedure-documentation.immutability.col.chain'), (string) __('procedure-documentation.immutability.col.model'), (string) __('procedure-documentation.immutability.col.entries'), (string) __('procedure-documentation.immutability.col.check')],
                    'rows' => $chains,
                ],
            ],
        ];
    }
}
