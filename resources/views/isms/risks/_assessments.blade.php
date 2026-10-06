{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assessments.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bewertungshistorie eines Risikos (046-D). Eigenes Partial: eine zweite
  Tabelle im Quelltext der Liste hielte das Gate R5 für deren Ende.
  Variablen: $risk
--}}
<x-table size="xs">
    <x-slot:head>
        <tr>
            <th>{{ __('isms.field.risk_no') }}</th>
            <th>{{ __('isms.field.assessment_kind') }}</th>
            <th>{{ __('isms.field.score') }}</th>
            <th>{{ __('isms.field.rationale') }}</th>
            <th>{{ __('isms.field.status') }}</th>
            <th>{{ __('isms.field.approved_by') }}</th>
            <th>{{ __('isms.field.valid_until') }}</th>
            <th></th>
        </tr>
    </x-slot:head>
    @foreach ($risk->assessments as $assessment)
        <tr id="isms-assessment-{{ $assessment->id }}">
            <td class="font-mono">{{ $assessment->displayNo() }}</td>
            <td><x-status-badge :tone="$assessment->kind->tone()" outline>{{ $assessment->kind->label() }}</x-status-badge></td>
            <td class="whitespace-nowrap">
                {{ $assessment->likelihood }}×{{ $assessment->impact }} =
                <x-status-badge :tone="\App\Models\Isms\IsmsRisk::scoreTone($assessment->score)">{{ $assessment->score }}</x-status-badge>
            </td>
            <td class="max-w-60">{{ $assessment->rationale !== null ? \Illuminate\Support\Str::limit($assessment->rationale, 80) : '—' }}</td>
            <td><x-status-badge :tone="$assessment->status->tone()">{{ $assessment->status->label() }}</x-status-badge></td>
            <td class="whitespace-nowrap">
                @if ($assessment->isApproved())
                    {{ optional($assessment->approvedBy)->name ?? '—' }} · {{ $assessment->approved_at?->fdate() }}
                @else
                    —
                @endif
            </td>
            <td class="whitespace-nowrap">
                @if ($assessment->valid_until !== null)
                    {{ $assessment->valid_until->fdate() }}
                    @if ($assessment->isReviewOverdue())
                        <x-status-badge tone="warning">{{ __('isms.assessment.review_overdue') }}</x-status-badge>
                    @endif
                @else
                    —
                @endif
            </td>
            <td class="text-right">
                @can('update', $risk)
                    @unless ($assessment->isApproved())
                        <span class="flex justify-end gap-1">
                            <x-action-form :action="route('isms.risks.assessments.approve', $assessment)"
                                  data-confirm-title="{{ __('isms.action.approve_assessment') }}"
                                  :confirm="__('isms.confirm_approve_assessment')"
                                  confirm-icon="task_alt"
                                  confirm-tone="primary"
                                  :confirm-label="__('isms.action.approve_assessment')">
                                <x-icon-btn icon="task_alt" tone="primary" size="xs" type="submit"
                                            :label="__('isms.action.approve_assessment')" />
                            </x-action-form>
                            <x-action-form :action="route('isms.risks.assessments.destroy', $assessment)" method="DELETE"
                                  data-confirm-title="{{ __('isms.action.delete') }}"
                                  :confirm="__('isms.confirm_delete_assessment')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('isms.action.delete')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit"
                                            :label="__('isms.action.delete')" />
                            </x-action-form>
                        </span>
                    @endunless
                @endcan
            </td>
        </tr>
    @endforeach
</x-table>
