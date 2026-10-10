{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _protocols_table.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Tabelle der Defektprotokolle im Drilldown-PDF ($protocols).
--}}
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>{{ __('Titel') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Typ') }}</th>
            <th>{{ __('Zeitpunkt') }}</th>
            <th>{{ __('Erstellt von') }}</th>
            <th>{{ __('Auftrag') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($protocols as $protocol)
            <tr>
                <td>{{ $protocol->id }}</td>
                <td>{{ $protocol->title }}</td>
                <td>{{ $protocol->status->label() }}</td>
                <td>{{ $protocol->type->label() }}</td>
                <td>{{ $protocol->occurred_at->orgTz()->format('Y-m-d H:i') }}</td>
                <td>{{ $protocol->creator?->name ?? '' }}</td>
                <td>{{ $protocol->subject?->title ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
