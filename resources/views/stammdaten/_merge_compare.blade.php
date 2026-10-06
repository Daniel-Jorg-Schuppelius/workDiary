{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _merge_compare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Feldvergleich vor dem Zusammenführen zweier Stammdatensätze. Parameter:
  $source, $target, $routePrefix (customers|suppliers|articles),
  $identityFields, $overridableFields (Feld => Bezeichnung), $subtitle,
  $confirm.
--}}
@php
    // Beträge formatiert statt als Rohwert des Wertobjekts („85.00 EUR").
    $show = static fn (mixed $value): string => $value instanceof \CommonToolkit\ValueObjects\Money ? $value->format() : (string) ($value ?? '');
@endphp
<x-index-page :subtitle="$subtitle"
              :back-route="$routePrefix . '.duplicates.index'" :back-label="__('Zurück')">

    <x-validation-errors class="mb-4" />

    <form method="POST" action="{{ route($routePrefix . '.duplicates.merge') }}"
          data-confirm-dialog
          data-confirm-message="{{ $confirm }}"
          data-confirm-icon="merge" data-confirm-tone="primary" data-confirm-label="{{ __('Zusammenführen') }}">
        @csrf
        <input type="hidden" name="source" value="{{ $source->sqid }}">
        <input type="hidden" name="target" value="{{ $target->sqid }}">

        <x-table>
            <x-slot:head>
                    <tr>
                        <th class="w-44">{{ __('Feld') }}</th>
                        <th>
                            <x-status-badge tone="success">{{ __('Bleibt') }}</x-status-badge>
                            <a href="{{ route($routePrefix . '.show', $target) }}" class="link ml-1">{{ $target->name }}</a>
                        </th>
                        <th>
                            <x-status-badge>{{ __('Wird gelöscht') }}</x-status-badge>
                            <a href="{{ route($routePrefix . '.show', $source) }}" class="link ml-1">{{ $source->name }}</a>
                        </th>
                        <th class="w-40 text-center">{{ __('Wert aus Quelle übernehmen') }}</th>
                    </tr>
            </x-slot:head>
                    @foreach ($identityFields as $field => $label)
                        @php
                            $tv = $show($target->getAttribute($field));
                            $sv = $show($source->getAttribute($field));
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $label }}</td>
                            <td>{{ $tv !== '' ? $tv : '—' }}</td>
                            <td class="{{ $tv !== $sv ? 'text-warning' : 'text-muted' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                            <td class="text-center text-muted">—</td>
                        </tr>
                    @endforeach

                    @foreach ($overridableFields as $field => $label)
                        @php
                            $tv = $show($target->getAttribute($field));
                            $sv = $show($source->getAttribute($field));
                        @endphp
                        @if ($tv !== '' || $sv !== '')
                            <tr>
                                <td class="text-muted">{{ $label }}</td>
                                <td class="{{ $tv === '' ? 'text-muted' : '' }}">{{ $tv !== '' ? $tv : '—' }}</td>
                                <td class="{{ $tv !== $sv ? 'text-warning' : 'text-muted' }}">{{ $sv !== '' ? $sv : '—' }}</td>
                                <td class="text-center">
                                    @if ($sv !== '' && $tv !== $sv)
                                        <input type="checkbox" class="checkbox checkbox-sm"
                                               name="prefer_source[]" value="{{ $field }}">
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
        </x-table>

        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <x-button :href="route($routePrefix . '.duplicates.compare', ['target' => $source->sqid, 'source' => $target->sqid])" tone="outline">{{ __('Richtung tauschen') }}</x-button>
            <x-button type="submit">{{ __('Zusammenführen →') }}</x-button>
        </div>
    </form>
</x-index-page>
