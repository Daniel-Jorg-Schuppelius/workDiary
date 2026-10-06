{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : import.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Clockify-Import (MVP-134): Detailed-Report-CSV hochladen oder per API importieren.
--}}

@extends('layouts.app')
@section('title', __('Clockify-Import'))
@section('nav-title', __('Clockify-Import'))

@section('content')
@include('plugins._time_import', [
    'routePrefix' => 'admin.clockify',
    'texts' => [
        'title' => __('Clockify-Import'),
        'subtitle' => __('Zeiteinträge aus einem Clockify-Detailed-Report übernehmen.'),
        'csv_hint' => __('Clockify → Reports → Detailed → Export → CSV. Clients/Projekte werden über Namen bzw. gemerkte Zuordnungen gematcht; nicht Zuordenbares landet in der Zuordnungs-Inbox. Für Free-Konten (30 API-Requests/h) ist CSV der empfohlene Weg.'),
        'api_title' => __('Direkt aus der Clockify-API importieren'),
        'api_hint' => __('Holt Zeiteinträge aller Benutzer über die Reports-API (X-Api-Key). Ohne Zeitraum werden die letzten :days Tage abgefragt; bereits importierte Einträge werden übersprungen.', ['days' => $syncWindowDays]),
        'api_missing' => __('Kein API-Key hinterlegt. API-Key (und optional Workspace-ID) in den Plugin-Einstellungen konfigurieren.'),
        'export_title' => __('Zeiten nach Clockify übertragen'),
        'export_hint' => __('Überträgt in workDiary erfasste Zeiten gemappter Projekte nach Clockify (z. B. Fernwartungssitzungen). Angelegt wird für den Inhaber des API-Keys; bereits übertragene oder aus Clockify importierte Einträge werden übersprungen, die Einträge bleiben lokal abrechenbar.'),
        'export_confirm' => __('Übertragung jetzt ausführen? Es werden Zeiteinträge in Clockify angelegt.'),
        'export_action' => __('Nach Clockify übertragen'),
    ],
])
@endsection
