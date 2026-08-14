{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : database.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Datenbank') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Wählen Sie den Treiber und geben Sie die Verbindungsdaten an. Anschließend werden die Migrationen ausgeführt.') }}
</p>

<form method="POST" action="{{ route('install.database.store') }}" class="space-y-4">
    @csrf

    <fieldset class="fieldset">
        <label class="fieldset-label" for="driver">{{ __('Treiber') }}</label>
        <select name="driver" id="driver" class="select select-sm select-bordered w-full">
            @foreach ($drivers as $d)
                <option value="{{ $d }}" @selected(old('driver', $values['driver']) === $d)>{{ $d }}</option>
            @endforeach
        </select>
    </fieldset>

    <fieldset data-driver-group="sqlite" class="fieldset">
        <label class="fieldset-label" for="database_sqlite">{{ __('SQLite-Dateipfad') }}</label>
        <input type="text" name="database_sqlite" id="database_sqlite" value="{{ old('driver', $values['driver']) === 'sqlite' ? old('database', $values['database_sqlite']) : $values['database_sqlite'] }}"
               class="input input-sm input-bordered w-full">
        <p class="fieldset-label">{{ __('Standard: database/database.sqlite (wird bei Bedarf angelegt).') }}</p>
    </fieldset>

    <div data-driver-group="server" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <fieldset class="fieldset">
                <label class="fieldset-label" for="host">{{ __('Host') }}</label>
                <input type="text" name="host" id="host" value="{{ old('host', $values['host']) }}"
                       class="input input-sm input-bordered w-full">
            </fieldset>
            <fieldset class="fieldset">
                <label class="fieldset-label" for="port">{{ __('Port') }}</label>
                <input type="number" name="port" id="port" value="{{ old('port', $values['port']) }}"
                       class="input input-sm input-bordered w-full">
            </fieldset>
        </div>
        <fieldset class="fieldset">
            <label class="fieldset-label" for="database_server">{{ __('Datenbankname') }}</label>
            <input type="text" name="database_server" id="database_server" value="{{ old('driver', $values['driver']) !== 'sqlite' ? old('database', $values['database_server']) : $values['database_server'] }}"
                   class="input input-sm input-bordered w-full" placeholder="workdiary">
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <fieldset class="fieldset">
                <label class="fieldset-label" for="username">{{ __('Benutzer') }}</label>
                <input type="text" name="username" id="username" value="{{ old('username', $values['username']) }}"
                       class="input input-sm input-bordered w-full">
            </fieldset>
            <fieldset class="fieldset">
                <label class="fieldset-label" for="password">{{ __('Passwort') }}</label>
                <input type="password" name="password" id="password" value=""
                       class="input input-sm input-bordered w-full" autocomplete="new-password">
            </fieldset>
        </div>
    </div>

    {{-- Wird per JS aus dem aktiven Treiber-Block befüllt, damit nur ein
         "database"-Feld an den Controller geht. --}}
    <input type="hidden" name="database" id="database" value="{{ old('database', $values['driver'] === 'sqlite' ? $values['database_sqlite'] : $values['database_server']) }}">

    <fieldset class="fieldset">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="fresh" value="1" class="checkbox checkbox-sm" @checked(old('fresh'))>
            <span class="fieldset-label">{{ __('Datenbank vor der Migration leeren (alle vorhandenen Tabellen werden verworfen)') }}</span>
        </label>
        <p class="fieldset-label text-warning">{{ __('Nur aktivieren, wenn die Datenbank leer sein soll oder eine frühere Migration abgebrochen wurde.') }}</p>
    </fieldset>

    <div class="card-actions justify-between pt-2">
        <x-button href="{{ route('install.application') }}" tone="ghost" size="sm">{{ __('Zurück') }}</x-button>
        <x-button type="submit" tone="primary" size="sm" iconTrailing="arrow_forward">{{ __('Verbinden & migrieren') }}</x-button>
    </div>
</form>

<script @cspNonce>
    (function () {
        const driver = document.getElementById('driver');
        const sqliteGroup = document.querySelector('[data-driver-group="sqlite"]');
        const serverGroup = document.querySelector('[data-driver-group="server"]');
        const hiddenDb = document.getElementById('database');
        const sqliteDb = document.getElementById('database_sqlite');
        const serverDb = document.getElementById('database_server');

        function sync() {
            const isSqlite = driver.value === 'sqlite';
            sqliteGroup.style.display = isSqlite ? '' : 'none';
            serverGroup.style.display = isSqlite ? 'none' : '';
            hiddenDb.value = isSqlite ? sqliteDb.value : serverDb.value;
        }

        driver.addEventListener('change', sync);
        sqliteDb.addEventListener('input', () => { hiddenDb.value = sqliteDb.value; });
        serverDb.addEventListener('input', () => { hiddenDb.value = serverDb.value; });
        document.querySelector('form').addEventListener('submit', sync);
        sync();
    })();
</script>
@endsection
