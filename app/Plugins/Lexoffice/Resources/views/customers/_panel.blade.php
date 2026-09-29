{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lexoffice-Panel der Kundenakte (Slot `customer-show.panels`, MVP-1038).
  Erwartet: $customer, $contactRef (?ExternalReference), $voucherRefs.
--}}
@can('update', $customer)
<x-card class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
            <x-icon name="sync" class="text-muted" /> {{ __('Lexoffice') }}
        </h2>
        @if ($contactRef)
            <x-status-badge tone="success">{{ __('Kontakt verknüpft') }} · {{ Str::limit($contactRef->external_id, 8, '…') }}</x-status-badge>
        @else
            <x-status-badge tone="ghost">{{ __('Noch nicht verknüpft') }}</x-status-badge>
        @endif
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        <form method="POST" action="{{ route('customers.lexoffice.contact', $customer) }}"
              class="flex h-full flex-col gap-3 rounded-box border border-base-300 bg-base-200/40 p-3">
            @csrf
            <div class="flex items-center gap-2 text-sm font-semibold">
                <x-icon name="contacts" class="text-muted" /> {{ __('Kontakt') }}
            </div>
            <p class="text-sm text-base-content/70">
                {{ __('Kunde als Kontakt in Lexoffice anlegen oder aktualisieren.') }}
            </p>
            <div class="mt-auto pt-1">
                <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>
                    {{ $contactRef ? __('Kontakt aktualisieren') : __('Kontakt anlegen') }}
                </x-icon-btn>
            </div>
        </form>

        <form method="POST" action="{{ route('customers.lexoffice.time-export', $customer) }}"
              class="flex h-full flex-col gap-3 rounded-box border border-base-300 bg-base-200/40 p-3">
            @csrf
            <div class="flex items-center gap-2 text-sm font-semibold">
                <x-icon name="receipt_long" class="text-muted" /> {{ __('Zeiten als Beleg') }}
            </div>
            <p class="text-sm text-base-content/70">
                {{ __('Abrechenbare, noch nicht übertragene Zeiten als Beleg übertragen.') }}
            </p>
            <x-date-range
                :from="now()->startOfMonth()->toDateString()"
                :to="now()->endOfMonth()->toDateString()"
                :required="true"
            />
            <div class="mt-auto pt-1">
                <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('Zeiten übertragen') }}</x-icon-btn>
            </div>
        </form>
    </div>

    @if ($voucherRefs->isNotEmpty())
        <div class="border-t border-base-300 pt-3">
            <h3 class="mb-2 text-sm font-semibold">{{ __('Letzte Belege') }}</h3>
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($voucherRefs as $ref)
                    <li class="flex items-center justify-between gap-2 py-1.5">
                        <code class="text-xs text-base-content/80">{{ $ref->external_id }}</code>
                        <span class="text-xs text-muted">{{ optional($ref->synced_at)->fdatetime() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-card>
@endcan
