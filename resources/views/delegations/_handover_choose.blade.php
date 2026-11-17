{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _handover_choose.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Endgültige Übergabe ohne Austritt (Feature 202, MVP-1648): Personenwahl als Einstieg auf der
     Seite „Vertretungen“ — auch für die Leitung ohne Zugang zur Mitgliederliste. Variablen: $members --}}
<x-modal
    :title="__('delegation.handover.choose_title')"
    :eyebrow="__('delegation.title')"
    icon="move_up"
    tone="primary">

    <div role="note" class="alert alert-info alert-soft text-sm">{{ __('delegation.handover.intro') }}</div>

    @if ($members->isEmpty())
        <x-empty-state icon="group" :title="__('delegation.handover.choose_empty')" compact />
    @else
        <p class="text-sm text-muted">{{ __('delegation.handover.choose_hint') }}</p>
        <ul class="flex flex-col gap-1" data-handover-members>
            @foreach ($members as $member)
                <li>
                    <x-button tone="ghost" size="sm" icon="person" block class="justify-start"
                              :href="route('delegations.handover.dialog', [$member, 'origin' => 'delegations'])"
                              data-entry-modal-trigger>
                        <span class="truncate">{{ $member->name }}</span>
                    </x-button>
                </li>
            @endforeach
        </ul>
    @endif
</x-modal>
