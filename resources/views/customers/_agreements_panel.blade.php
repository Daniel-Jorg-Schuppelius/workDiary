{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _agreements_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kundenakte-Bereich „Vereinbarungen" (Feature 157, MVP-822): AVV/NDA des
  Kunden mit Stand der neuesten Fassung. Ohne Modul/Recht liefert der
  Assembler null — dann bleibt der Bereich weg.
  Erwartet: $customer, $agreements (Collection|null).
--}}
@if ($agreements !== null)
    <x-card as="section" id="agreements" :title="__('contract-signing.customer_panel.title')" icon="draw" :count="$agreements->count()" padding="p-0">
        @can('create', \App\Models\Contract\Contract::class)
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                            :href="route('contracts.create', ['customer' => $customer->sqid, 'agreement' => 1])"
                            show-label>{{ __('contract-signing.action.new_agreement') }}</x-icon-btn>
            </x-slot:actions>
        @endcan
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Vertragsart') }}</th>
                    <th>{{ __('Titel') }}</th>
                    <th>{{ __('contract-signing.customer_panel.revision') }}</th>
                    <th>{{ __('contract-signing.field.status') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($agreements as $agreement)
                @php $latest = $agreement->latestSigningRevision; @endphp
                <tr>
                    <td><a href="{{ route('contracts.show', $agreement) }}" class="link font-mono">{{ $agreement->number }}</a></td>
                    <td>{{ $agreement->kind->label() }}</td>
                    <td>{{ $agreement->title }}</td>
                    <td>
                        @if ($latest)
                            {{ $latest->label() }}
                            <x-status-badge size="xs" :tone="$latest->status->tone()" :label="$latest->status->label()" />
                        @else
                            <span class="text-muted">{{ __('contract-signing.customer_panel.no_revision') }}</span>
                        @endif
                    </td>
                    <td><x-status-badge size="sm" outline>{{ $agreement->status->label() }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('contracts.show', $agreement)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon="draw" :colspan="6" :title="__('contract-signing.customer_panel.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
@endif
