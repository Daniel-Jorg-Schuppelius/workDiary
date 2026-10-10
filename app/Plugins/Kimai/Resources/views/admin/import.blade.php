{{--
  Created on   : Mon Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kimai-Import/-Export (MVP-134): Timesheet-CSV hochladen, API-Import und
  optionale Rückbuchung erfasster Zeiten als Kimai-Timesheets.
--}}

@extends('layouts.app')
@section('title', __('Kimai-Import'))
@section('nav-title', __('Kimai-Import'))

@section('content')
@include('plugins._time_import', [
    'routePrefix' => 'admin.kimai',
    'texts' => [
        'title' => __('Kimai-Import'),
        'subtitle' => __('Zeiteinträge aus einem Kimai-Timesheet-CSV-Export übernehmen.'),
        'csv_hint' => __('Kimai → Zeiten → Export → CSV. Kunden/Projekte werden über Namen bzw. gemerkte Zuordnungen gematcht; nicht Zuordenbares landet in der Zuordnungs-Inbox.'),
        'api_title' => __('Direkt aus der Kimai-API importieren'),
        'api_hint' => __('Holt Timesheets über die Kimai-REST-API (Bearer-Token). Mit hinterlegtem API-Zugang läuft dieser Import stündlich von selbst; hier stoßen Sie ihn sofort an. Ohne Zeitraum werden die letzten :days Tage abgefragt; bereits importierte Einträge werden übersprungen.', ['days' => $syncWindowDays]),
        'api_missing' => __('Kein API-Zugang hinterlegt. Basis-URL und API-Token in den Plugin-Einstellungen konfigurieren.'),
        'export_title' => __('Zeiten nach Kimai zurückbuchen'),
        'export_hint' => __('Bucht in workDiary erfasste, noch nicht exportierte Zeiten gemappter Projekte als Kimai-Timesheets (Tätigkeit aus den Plugin-Einstellungen). Bereits gebuchte und aus Kimai importierte Einträge werden übersprungen.'),
        'export_confirm' => __('Rückbuchung jetzt ausführen? Es werden Timesheets in Kimai angelegt.'),
        'export_action' => __('Nach Kimai exportieren'),
    ],
])
@endsection
