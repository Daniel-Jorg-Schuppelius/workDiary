<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractTemplateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Contract;

use App\Enums\Contract\{ContractKind, ContractObligationKind};
use App\Models\Contract\{Contract, ContractObligation, ContractTemplate};
use App\Models\Platform\User;
use Carbon\CarbonImmutable;

/**
 * Vertragsvorlagen (Feature 079, MVP-893): aus einem Vertrag speichern,
 * Anlegedialog vorbelegen, Pflichten beim Anlegen relativ zum Beginn setzen.
 */
final class ContractTemplateService {
    /** Felder, die eine Vorlage in den Anlegedialog übernimmt. */
    public const PRESET_FIELDS = ['title', 'term_kind', 'min_term_months', 'auto_renew', 'renew_period_months', 'notice_period_days', 'value_period', 'indexation_method'];

    public function __construct(private readonly ContractService $contracts) {}

    public function fromContract(Contract $contract, string $name, User $actor): ContractTemplate {
        $start = CarbonImmutable::parse($contract->starts_on);
        // Die Kündigungsfrist-Erinnerung erzeugt der Vertrag selbst; sie gehört nicht in die Vorlage.
        $obligations = $contract->obligations()
            ->where('kind', '!=', ContractObligationKind::NoticeDeadline->value)
            ->orderBy('due_on')
            ->get()
            ->map(static fn (ContractObligation $o): array => [
                'kind' => $o->kind->value,
                'title' => $o->title,
                'offset_months' => max(0, (int) $start->diffInMonths(CarbonImmutable::parse($o->due_on))),
                'warn_days_before' => $o->warn_days_before,
                'recurring' => $o->recurring,
                'recurrence_months' => $o->recurrence_months,
            ])->values()->all();

        return ContractTemplate::query()->create([
            'organization_id' => $contract->organization_id,
            'name' => $name,
            'kind' => $contract->kind->value,
            'title' => $contract->title,
            'term_kind' => $contract->term_kind->value,
            'min_term_months' => $contract->min_term_months,
            'auto_renew' => $contract->auto_renew,
            'renew_period_months' => $contract->renew_period_months,
            'notice_period_days' => $contract->notice_period_days,
            'value_period' => $contract->value_period,
            'indexation_method' => $contract->indexation_method->value,
            'obligations' => $obligations,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /** @return array<string, mixed> Vorbelegung des Anlegedialogs */
    public function preset(ContractTemplate $template): array {
        $preset = ['kind' => $template->kind->value, 'template_id' => $template->sqid];
        foreach (self::PRESET_FIELDS as $field) {
            $value = $template->getAttribute($field);
            $preset[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $preset;
    }

    /** Pflichten der Vorlage am neuen Vertrag, fällig ab Vertragsbeginn. */
    public function applyObligations(ContractTemplate $template, Contract $contract): int {
        $start = CarbonImmutable::parse($contract->starts_on);
        $count = 0;
        foreach ($template->obligations as $spec) {
            $kind = ContractObligationKind::tryFrom((string) ($spec['kind'] ?? '')) ?? ContractObligationKind::Other;
            $title = trim((string) ($spec['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $this->contracts->addObligation($contract, [
                'kind' => $kind->value,
                'title' => $title,
                'due_on' => $start->addMonths(max(0, (int) ($spec['offset_months'] ?? 0)))->toDateString(),
                'warn_days_before' => (int) ($spec['warn_days_before'] ?? 30),
                'recurring' => (bool) ($spec['recurring'] ?? false),
                'recurrence_months' => isset($spec['recurrence_months']) ? (int) $spec['recurrence_months'] : null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Vorlagen zur Auswahl im Anlegedialog (bei AVV/NDA-Beschränkung nur deren Arten).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ContractTemplate>
     */
    public function choices(bool $agreementOnly): \Illuminate\Database\Eloquent\Collection {
        $kinds = $agreementOnly ? array_map(static fn (ContractKind $k): string => $k->value, ContractKind::signingKinds()) : null;

        return ContractTemplate::query()
            ->where('is_active', true)
            ->when($kinds !== null, fn ($q) => $q->whereIn('kind', $kinds))
            ->orderBy('name')
            ->get();
    }
}
