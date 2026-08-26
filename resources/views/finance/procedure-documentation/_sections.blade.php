{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _sections.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Generierter Systemteil (Builder-Payload) für die Bildschirmansicht:
  Felder als Detail-Grid, Tabellen als x-table (bare, xs), Hinweise als Liste.
  Variablen: $payload
--}}
<x-card :title="__('procedure-documentation.generated.title')" icon="settings_suggest">
    <div class="space-y-6">
        @foreach ($payload['sections'] ?? [] as $section)
            <section>
                <h3 class="font-['Space_Grotesk'] text-base font-semibold">{{ $section['title'] }}</h3>

                @if (! empty($section['fields']))
                    <div class="mt-2">
                        <x-detail-grid>
                            @foreach ($section['fields'] as $field)
                                <x-detail-grid.row :label="$field['label']" :value="$field['value']" />
                            @endforeach
                        </x-detail-grid>
                    </div>
                @endif

                @foreach ($section['tables'] ?? [] as $table)
                    <p class="mb-1 mt-3 text-sm font-medium">{{ $table['title'] }}</p>
                    <x-table :caption="$table['title']" bare size="xs">
                        <x-slot:head>
                            <tr>
                                @foreach ($table['columns'] as $column)
                                    <th>{{ $column }}</th>
                                @endforeach
                            </tr>
                        </x-slot:head>
                        @forelse ($table['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td class="text-xs">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <x-table.empty :colspan="count($table['columns'])" :title="__('procedure-documentation.generated.no_rows')" compact />
                        @endforelse
                    </x-table>
                @endforeach

                @if (! empty($section['notes']))
                    <ul class="mt-2 ms-5 list-disc text-sm text-muted">
                        @foreach ($section['notes'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
</x-card>
