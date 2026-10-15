{{--
  Created on   : Mon Jun 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_actions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aktions-Buttons der Diary-Detailansicht (Seite: inline im Kopf; Dialog: Modal-Footer). Erwartet: $diary --}}
{{-- Kaufmännischer Abschluss (Feature 165, MVP-1194): Angebot oder Rechnung aus
     dem Auftrag heraus — vorhandene Dialoge, Kunde und Projekt vorbelegt. --}}
@if ($diary->customer_id !== null && ! $diary->is_archived)
    @php $_billingPrefill = ['customer_id' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $diary->customer_id), 'project_id' => $diary->project_id !== null ? \App\Support\Sqid::encode(\App\Models\Project\Project::class, $diary->project_id) : null]; @endphp
    @if (app(\App\Services\Navigation\NavGate::class)->allows('quotes.create'))
        @can('create', \App\Models\Sales\Quote::class)
            <x-icon-btn icon="request_quote" tone="outline" size="sm" data-entry-modal-trigger
                        :href="route('quotes.create', array_filter($_billingPrefill))"
                        show-label>{{ __('Angebot') }}</x-icon-btn>
        @endcan
    @endif
    @if (app(\App\Services\Navigation\NavGate::class)->allows('invoices.create'))
        @can('create', \App\Models\Invoicing\Invoice::class)
            <x-icon-btn icon="receipt_long" tone="outline" size="sm" data-entry-modal-trigger
                        :href="route('invoices.create', array_filter($_billingPrefill))"
                        show-label>{{ __('Rechnung') }}</x-icon-btn>
        @endcan
    @endif
@endif
@can('archive', $diary)
    @if ($diary->is_archived)
        <x-action-form :action="route('diary.restore', $diary)">
            <x-icon-btn icon="restore" tone="outline" size="sm" type="submit" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
        </x-action-form>
    @else
        <x-action-form :action="route('diary.archive', $diary)">
            <x-icon-btn icon="archive" tone="outline" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
        </x-action-form>
    @endif
@endcan
@can('delete', $diary)
    <x-action-form :action="route('diary.destroy', $diary)" method="DELETE"
        data-confirm-title="{{ __('Eintrag löschen') }}"
        :confirm="__('Der Eintrag wird dauerhaft gelöscht. Möchten Sie fortfahren?')"
        :confirm-label="__('Löschen')">
        <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
    </x-action-form>
@endcan
@can('update', $diary)
    <x-icon-btn icon="edit" tone="primary" size="sm"
                data-entry-modal-trigger
                :href="route('diary.edit', $diary)"
                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
@endcan
