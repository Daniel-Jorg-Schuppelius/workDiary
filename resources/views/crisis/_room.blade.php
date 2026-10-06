{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _room.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Krisenraum (MVP-963): Anwesenheit und Lagekarte. Erwartet: $case, $roomMarkers, $roomPresent, $roomPoints, $canManage --}}
<x-card :title="__('crisis.room.title')">
    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm" x-data="crisisPresence" data-url="{{ route('crisis.room.heartbeat', $case) }}" data-present='@json($roomPresent)'>
        <x-icon name="groups" />
        <span class="font-medium">{{ __('crisis.room.present') }}:</span>
        <template x-for="person in people" :key="person.name">
            <span class="wd-badge badge-success" x-text="person.name"></span>
        </template>
        <span class="text-muted" x-show="isEmpty">{{ __('crisis.room.nobody') }}</span>
    </div>
    @if ($roomMarkers !== [])
        <x-map :markers="$roomMarkers" height="320px" />
    @else
        <x-empty-state icon="map" :title="__('crisis.room.no_markers')" compact />
    @endif
    @if ($canManage)
        <form method="POST" action="{{ route('crisis.room.points.store', $case) }}" class="mt-3 grid gap-2 sm:grid-cols-5" data-entry-form>
            @csrf
            <x-input-field name="label" :label="__('crisis.room.field.label')" required />
            <x-select-field name="kind" :label="__('crisis.room.field.kind')" required>
                @foreach (\App\Models\Crisis\CrisisMapPoint::KINDS as $kind)
                    <option value="{{ $kind }}">{{ __('crisis.room.kind.' . $kind) }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="lat" type="number" step="0.0000001" :label="__('crisis.room.field.lat')" required />
            <x-input-field name="lng" type="number" step="0.0000001" :label="__('crisis.room.field.lng')" required />
            <div class="flex items-end"><x-button type="submit" tone="plain">{{ __('crisis.room.add_point') }}</x-button></div>
        </form>
    @endif
    @if ($roomPoints->isNotEmpty())
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($roomPoints as $point)
                <li class="flex items-center justify-between gap-2">
                    <span><span class="wd-badge badge-ghost">{{ __('crisis.room.kind.' . $point->kind) }}</span> {{ $point->label }}</span>
                    @if ($canManage)
                        <form method="POST" action="{{ route('crisis.room.points.destroy', $point) }}">
                            @csrf
                            @method('DELETE')
                            <x-icon-btn icon="delete" size="xs" type="submit" :title="__('Entfernen')" />
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
