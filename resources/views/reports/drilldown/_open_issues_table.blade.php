{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _open_issues_table.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Tabelle der offenen Punkte eines Drilldowns ($issues, paginiert); $showAsset blendet die Asset-Spalte ein.
--}}
<x-card>
    @if ($issues->isEmpty())
        <x-empty-state icon="error_outline" :title="__('Keine offenen Punkte für diesen Drilldown gefunden.')" />
    @else
        <x-table bare table-sort="client">
            <x-slot:head>
                <tr>
                    @if ($showAsset ?? false)
                        <x-table.th sort type="number">{{ __('Asset') }}</x-table.th>
                    @endif
                    <x-table.th sort type="string">{{ __('Titel') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Severity') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('Fällig') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Zugewiesen') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($issues as $issue)
                <tr>
                    @if ($showAsset ?? false)
                        <td>#{{ $issue->subject_id }}</td>
                    @endif
                    <td class="font-medium">{{ $issue->title }}</td>
                    <td><x-status-badge tone="ghost" outline>{{ $issue->status->label() }}</x-status-badge></td>
                    <td><x-status-badge tone="ghost" outline>{{ $issue->severity->label() }}</x-status-badge></td>
                    <td>{{ $issue->due_at?->fdate() ?? '—' }}</td>
                    <td>{{ $issue->assignee?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$issues" standing />
    @endif
</x-card>
