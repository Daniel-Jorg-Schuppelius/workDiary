{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _second_person.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vier-Augen-Leiste eines Schritts (MVP-028, MVP-1301): Stand, Anfordern, Gegenzeichnen, Widerrufen. --}}
@php
    /** @var \App\Models\Procedure\ProcedureStepRun $sr */
    $viewerId = auth()->id();
    [$spTone, $spLabel] = match (true) {
        $sr->second_person_signed_at !== null => ['success', __('procedure.secondPerson.status.signed', ['name' => $sr->secondPerson?->name ?? '—'])],
        $sr->second_person_user_id !== null => ['info', __('procedure.secondPerson.status.assigned', ['name' => $sr->secondPerson?->name ?? '—'])],
        $sr->second_person_requested_at !== null => ['warning', __('procedure.secondPerson.status.requested', ['name' => $sr->secondPersonRequester?->name ?? '—'])],
        default => ['neutral', __('procedure.secondPerson.status.open')],
    };
@endphp
<x-card padding="px-3 py-2" class="mt-3 space-y-2" data-second-person>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-icon name="group" class="text-muted" />
        <span class="font-medium">{{ __('procedure.secondPerson.title') }}</span>
        <x-status-badge :tone="$spTone" size="xs">{{ $spLabel }}</x-status-badge>
    </div>

    @if ($runActive && ! $isFinal)
        <div class="flex flex-wrap gap-2">
            @if ($sr->second_person_requested_at === null)
                @can('requestSecondPerson', $run)
                    <form method="POST" action="{{ route('procedure-runs.steps.second-person.request', [$run, $sr]) }}">
                        @csrf
                        <x-button type="submit" tone="secondary" size="sm" icon="person_add">{{ __('procedure.run.requestSecondPerson') }}</x-button>
                    </form>
                @endcan
            @endif
            @if ($sr->second_person_signed_at === null && $sr->second_person_requester_user_id !== $viewerId)
                @can('signSecondPerson', $run)
                    <form method="POST" action="{{ route('procedure-runs.steps.second-person', [$run, $sr]) }}">
                        @csrf
                        <x-button type="submit" tone="secondary" size="sm" icon="how_to_reg">{{ __('procedure.run.signSecondPerson') }}</x-button>
                    </form>
                @endcan
            @endif
        </div>
    @endif

    @if ($runActive && $sr->second_person_user_id !== null)
        @can('revokeSecondPerson', $run)
            <details class="text-xs">
                <summary class="cursor-pointer text-muted">{{ __('procedure.run.revokeSecondPerson') }}</summary>
                <form method="POST" action="{{ route('procedure-runs.steps.second-person.revoke', [$run, $sr]) }}" class="mt-2 space-y-2">
                    @csrf
                    <textarea aria-label="{{ __('procedure.run.revokeReason') }}" name="reason" rows="2" minlength="5" maxlength="2000" required
                              class="textarea textarea-bordered textarea-sm w-full"
                              placeholder="{{ __('procedure.run.revokeReason') }}"></textarea>
                    <x-button type="submit" tone="warning" size="xs">{{ __('procedure.run.revokeSecondPersonConfirm') }}</x-button>
                </form>
            </details>
        @endcan
    @endif
</x-card>
