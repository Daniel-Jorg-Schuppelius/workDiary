{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : accounting-led-notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Hinweis im Kunden- und Lieferantenformular (MVP-1628): Führt die Buchhaltung die
     Stammdaten, überschreibt ihr nächster Abgleich lokale Änderungen. Hinweis, keine Sperre. --}}
@php
    $ledOrganization = \App\Support\OrganizationContext::current();
    $ledOwner = $ledOrganization !== null
        ? app(\App\Services\Integration\DataOwnershipResolver::class)->ownerFor($ledOrganization, \App\Enums\Integration\DataDomain::Customers)
        : \App\Services\Integration\DataOwnershipResolver::NATIVE;
    $ledProgram = match ($ledOwner) {
        \App\Services\Integration\DataOwnershipResolver::NATIVE => null,
        \App\Services\Integration\DataOwnershipResolver::ACCOUNTING => __('integration.ownership.led_by_accounting'),
        default => app(\App\Plugins\PluginManager::class)->find($ledOwner)?->name() ?? $ledOwner,
    };
@endphp
@if ($ledProgram !== null)
    <div role="status" class="alert alert-warning alert-soft text-sm" data-accounting-led>
        <x-icon name="sync_lock" />
        <span>{{ __('integration.ownership.led_notice', ['program' => $ledProgram]) }}</span>
    </div>
@endif
