{{--
  Created on   : Mon Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import-summary.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Gemeinsame Vorschau-/Ergebnistabelle des Workspace-Imports (Ordner & API).
     MVP-509: Einbenutzer-Modus und ungelöste Benutzer sind deutlich sichtbar. --}}
@if (($summary['user_mode'] ?? null) === \App\Plugins\Toggl\TogglExportImporter::USER_SINGLE)
    <div class="alert alert-info mb-3 text-sm">
        {{ $summary['dry_run']
            ? __('Einbenutzer-Modus: Alle Zeiten würden :name zugeordnet.', ['name' => $summary['single_user_name'] ?? __('dem Standard-Benutzer')])
            : __('Einbenutzer-Modus: Alle Zeiten wurden :name zugeordnet.', ['name' => $summary['single_user_name'] ?? __('dem Standard-Benutzer')]) }}
    </div>
@endif

@if ((int) ($summary['totals']['entries_unresolved_user'] ?? 0) > 0)
    <div class="alert alert-warning mb-3 text-sm">
        <div>
            <p class="font-semibold">
                {{ __(':n Einträge ohne zuordenbaren Benutzer — nicht gebucht.', ['n' => (int) $summary['totals']['entries_unresolved_user']]) }}
            </p>
            <ul class="mt-1 list-inside list-disc">
                @foreach (($summary['totals']['unresolved_emails'] ?? []) as $email => $count)
                    <li>{{ $email !== '' ? $email : __('ohne E-Mail-Signal') }} ({{ $count }})</li>
                @endforeach
            </ul>
            <p class="mt-1">
                {{ __('Zuordnung oben im Formular pflegen (oder „fehlende Benutzer neu anlegen" wählen) und den Import erneut ausführen — bereits gebuchte Zeiten werden nicht doppelt importiert.') }}
            </p>
        </div>
    </div>
@endif

<div class="overflow-x-auto">
    <table class="table table-sm">
        <thead>
            <tr>
                <th>{{ __('Workspace') }}</th>
                <th>{{ __('Modus') }}</th>
                <th class="text-right">{{ __('Kunden (neu/wiederv.)') }}</th>
                <th class="text-right">{{ __('Fremdkunden (neu/wiederv.)') }}</th>
                <th class="text-right">{{ __('Projekte (neu/wiederv.)') }}</th>
                <th class="text-right">{{ __('Benutzer neu') }}</th>
                <th class="text-right">{{ __('Zeiten (gebucht/übersprungen)') }}</th>
                <th class="text-right">{{ __('Ohne Benutzer') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['workspaces'] as $w)
                <tr>
                    <td>{{ $w['workspace'] }}@isset($w['customer'])<span class="text-base-content/50"> → {{ $w['customer'] }}</span>@endisset</td>
                    <td>{{ $w['mode'] }}</td>
                    <td class="text-right">{{ $w['customers_created'] }} / {{ $w['customers_reused'] }}</td>
                    <td class="text-right">{{ $w['foreign_customers_created'] }} / {{ $w['foreign_customers_reused'] }}</td>
                    <td class="text-right">{{ $w['projects_created'] }} / {{ $w['projects_reused'] }}</td>
                    <td class="text-right">{{ $w['users_created'] }}</td>
                    <td class="text-right">{{ $w['entries_created'] }} / {{ $w['entries_skipped'] }}</td>
                    <td class="text-right">{{ $w['entries_unresolved_user'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-semibold">
                <td colspan="2">{{ __('Summe') }}</td>
                <td class="text-right">{{ $summary['totals']['customers_created'] }} / {{ $summary['totals']['customers_reused'] }}</td>
                <td class="text-right">{{ $summary['totals']['foreign_customers_created'] }} / {{ $summary['totals']['foreign_customers_reused'] }}</td>
                <td class="text-right">{{ $summary['totals']['projects_created'] }} / {{ $summary['totals']['projects_reused'] }}</td>
                <td class="text-right">{{ $summary['totals']['users_created'] }}</td>
                <td class="text-right">{{ $summary['totals']['entries_created'] }} / {{ $summary['totals']['entries_skipped'] }}</td>
                <td class="text-right">{{ $summary['totals']['entries_unresolved_user'] ?? 0 }}</td>
            </tr>
        </tfoot>
    </table>
</div>
