{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _open_issues_table.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Tabelle der offenen Punkte im Drilldown-PDF ($issues); $showAsset blendet die Asset-Spalte ein.
--}}
<table>
    <thead>
        <tr>
            <th>ID</th>
            @if ($showAsset ?? false)
                <th>AssetID</th>
            @endif
            <th>Titel</th>
            <th>Status</th>
            <th>Severity</th>
            <th>{{ __('Fällig') }}</th>
            <th>Zugewiesen</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($issues as $issue)
            <tr>
                <td>{{ $issue->id }}</td>
                @if ($showAsset ?? false)
                    <td>{{ $issue->subject_id }}</td>
                @endif
                <td>{{ $issue->title }}</td>
                <td>{{ $issue->status->label() }}</td>
                <td>{{ $issue->severity->label() }}</td>
                <td>{{ $issue->due_at?->format('Y-m-d') ?? '' }}</td>
                <td>{{ $issue->assignee?->name ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
