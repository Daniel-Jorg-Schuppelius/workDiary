{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _origin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Herkunft aus einem Kundeneingang (MVP-1075) am Angebot oder Zielvorgang. Erwartet: $subject (Model) --}}
@php
    $originIntake = $subject instanceof \App\Models\Sales\Quote
        ? \App\Models\Customer\CustomerIntake::query()->where('quote_id', $subject->getKey())->first()
        : \App\Models\Customer\CustomerIntake::query()->where('target_type', $subject->getMorphClass())->where('target_id', $subject->getKey())->first();
@endphp
@if ($originIntake !== null)
    <div role="note" class="alert text-sm">
        <x-icon name="move_to_inbox" />
        <span>
            {{ __('customer_intake.origin.text') }}
            @can('view', $originIntake)
                <a class="link font-mono" href="{{ route('customer-intakes.show', $originIntake) }}">{{ $originIntake->number }}</a>
            @else
                <span class="font-mono">{{ $originIntake->number }}</span>
            @endcan
            — {{ $originIntake->subject }}
        </span>
    </div>
@endif
