{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _horses_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Reiter–Pferd-Zuordnung je Reitstunde (MVP-854): angemeldete Reiter mit Pferd, eigenem Pferd, Hinweisen und
  Einsatzminuten. Variablen: $event, $riderRows, $horseOptions, $canParticipants, $isCancelled.
--}}
<x-card :title="__('club.horses.card.event')" icon="bedroom_baby" :count="$riderRows->count()">
    <p class="mb-2 text-xs text-muted">{{ __('club.horses.hint.event') }}</p>
    <x-table :bare="true" size="sm">
        <x-slot:head>
            <tr>
                <th>{{ __('club.horses.field.rider') }}</th>
                <th>{{ __('club.horses.field.horse') }}</th>
                <th>{{ __('club.horses.field.use_minutes') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($riderRows as $row)
            @php
                $member = $row['member'];
                $assignment = $row['assignment'];
                $horse = $row['horse'];
            @endphp
            <tr class="align-top">
                <td class="text-sm">
                    <a href="{{ route('club.members.show', $member) }}" class="link link-hover font-medium">{{ $member->fullName() }}</a>
                    @foreach ($row['warnings'] as $warning)
                        <span class="block text-xs text-warning"><x-icon name="warning" /> {{ $warning }}</span>
                    @endforeach
                    @if ($assignment?->override_note)<span class="block text-xs text-muted">{{ __('club.horses.label.override', ['note' => $assignment->override_note]) }}</span>@endif
                </td>
                <td>
                    @if ($canParticipants && ! $isCancelled)
                        <form method="POST" action="{{ route('club.events.horses.assign', $event) }}" class="flex flex-wrap items-center gap-1" data-entry-form>
                            @csrf
                            <input type="hidden" name="club_member_id" value="{{ $member->sqid }}">
                            <select name="club_horse_id" class="select select-bordered select-xs w-40" aria-label="{{ __('club.horses.field.horse') }}">
                                <option value="">–</option>
                                @foreach ($horseOptions as $option)
                                    <option value="{{ $option->sqid }}" @selected($horse?->id === $option->id)>{{ $option->name }}@if ($option->isPrivate()) ({{ $option->kind->label() }})@endif</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="own_horse" value="1" class="checkbox checkbox-xs" @checked($assignment?->own_horse)> {{ __('club.horses.label.own_horse') }}</label>
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="override" value="1" class="checkbox checkbox-xs"> {{ __('club.horses.field.override') }}</label>
                            <input type="text" name="override_note" maxlength="255" class="input input-bordered input-xs w-36" placeholder="{{ __('club.horses.field.override_note') }}" aria-label="{{ __('club.horses.field.override_note') }}">
                            <x-icon-btn type="submit" icon="save" tone="outline" size="xs" :label="__('club.action.save')" />
                        </form>
                    @else
                        <span class="text-sm">{{ $horse?->name ?? ($assignment?->own_horse ? __('club.horses.label.own_horse') : '–') }}</span>
                    @endif
                </td>
                <td class="text-sm">
                    @foreach ($row['uses'] as $use)
                        <span class="badge badge-ghost badge-sm tabular-nums">{{ $use->horse?->name ?? '' }} {{ $use->minutes }} min</span>
                    @endforeach
                    @if ($canParticipants && $horse)
                        <form method="POST" action="{{ route('club.events.horses.use', $event) }}" class="mt-1 flex items-center gap-1" data-entry-form>
                            @csrf
                            <input type="hidden" name="club_member_id" value="{{ $member->sqid }}">
                            <input type="hidden" name="club_horse_id" value="{{ $horse->sqid }}">
                            <input type="number" name="minutes" min="0" max="1440" class="input input-bordered input-xs w-20" value="{{ $row['uses']->firstWhere('club_horse_id', $horse->id)?->minutes }}" aria-label="{{ __('club.horses.field.use_minutes') }}">
                            <x-icon-btn type="submit" icon="timer" tone="ghost" size="xs" :label="__('club.horses.action.record_use')" />
                        </form>
                    @endif
                </td>
                <td class="text-right">
                    @if ($canParticipants && ! $isCancelled && $assignment)
                        <x-action-form :action="route('club.events.horses.unassign', [$event, $assignment])" method="DELETE">
                            <x-icon-btn type="submit" icon="close" tone="ghost" size="xs" :label="__('club.horses.action.unassign')" />
                        </x-action-form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="bedroom_baby" :colspan="4" :title="__('club.horses.empty.event')" compact />
        @endforelse
    </x-table>
</x-card>
