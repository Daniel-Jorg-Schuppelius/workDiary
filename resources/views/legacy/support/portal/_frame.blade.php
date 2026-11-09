{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _frame.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kopf der Kundenseiten: Kunde, Navigation, Meldungen. Erwartet: $customer, $active (orders|create|password) --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-xs uppercase tracking-[0.2em] text-muted">{{ __('legacy_support.portal.title') }}</p>
        <h1 class="font-['Space_Grotesk'] text-2xl font-bold tracking-tight">{{ $customer->kunde }}</h1>
    </div>
    <nav class="flex flex-wrap items-center gap-2" aria-label="{{ __('legacy_support.portal.title') }}">
        <x-icon-btn icon="list_alt" size="sm" :tone="$active === 'orders' ? 'primary' : 'ghost'" :href="route('legacy.portal.orders.index')" show-label>{{ __('legacy_support.portal.my_orders') }}</x-icon-btn>
        @if ((bool) config('app.legacy_write_enabled'))
            <x-icon-btn icon="add" size="sm" :tone="$active === 'create' ? 'primary' : 'ghost'" :href="route('legacy.portal.orders.create')" show-label>{{ __('legacy_support.portal.new_order') }}</x-icon-btn>
            <x-icon-btn icon="lock" size="sm" :tone="$active === 'password' ? 'primary' : 'ghost'" :href="route('legacy.portal.password.edit')" show-label>{{ __('legacy_support.portal.change_password') }}</x-icon-btn>
        @endif
    </nav>
</div>

@if (session('success'))
    <div class="mb-4 alert alert-success text-sm" role="status">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 alert alert-error text-sm" role="alert">{{ session('error') }}</div>
@endif
@unless ((bool) config('app.legacy_write_enabled'))
    <div class="mb-4 alert alert-info text-sm">{{ __('legacy_support.portal.read_only') }}</div>
@endunless
