@extends('layouts.install')

@section('install-content')
<h2 class="card-title mb-2">{{ __('Datenbank') }}</h2>
<p class="mb-4 text-sm text-base-content/70">
    {{ __('Wählen Sie den Treiber und geben Sie die Verbindungsdaten an. Anschließend werden die Migrationen ausgeführt.') }}
</p>

<form method="POST" action="{{ route('install.database.store') }}" class="space-y-4">
    @csrf

    <div>
        <label class="label" for="driver"><span class="label-text">{{ __('Treiber') }}</span></label>
        <select name="driver" id="driver" class="select select-sm select-bordered w-full">
            @foreach ($drivers as $d)
                <option value="{{ $d }}" @selected(old('driver', $values['driver']) === $d)>{{ $d }}</option>
            @endforeach
        </select>
    </div>

    <div data-driver-group="sqlite" class="space-y-1">
        <label class="label" for="database_sqlite"><span class="label-text">{{ __('SQLite-Dateipfad') }}</span></label>
        <input type="text" name="database_sqlite" id="database_sqlite" value="{{ old('database', $values['database']) }}"
               class="input input-sm input-bordered w-full">
        <p class="text-xs text-base-content/50">{{ __('Standard: database/database.sqlite (wird bei Bedarf angelegt).') }}</p>
    </div>

    <div data-driver-group="server" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="host"><span class="label-text">{{ __('Host') }}</span></label>
                <input type="text" name="host" id="host" value="{{ old('host', $values['host']) }}"
                       class="input input-sm input-bordered w-full">
            </div>
            <div>
                <label class="label" for="port"><span class="label-text">{{ __('Port') }}</span></label>
                <input type="number" name="port" id="port" value="{{ old('port', $values['port']) }}"
                       class="input input-sm input-bordered w-full">
            </div>
        </div>
        <div>
            <label class="label" for="database_server"><span class="label-text">{{ __('Datenbankname') }}</span></label>
            <input type="text" name="database_server" id="database_server" value="{{ old('database', $values['database']) }}"
                   class="input input-sm input-bordered w-full">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="username"><span class="label-text">{{ __('Benutzer') }}</span></label>
                <input type="text" name="username" id="username" value="{{ old('username', $values['username']) }}"
                       class="input input-sm input-bordered w-full">
            </div>
            <div>
                <label class="label" for="password"><span class="label-text">{{ __('Passwort') }}</span></label>
                <input type="password" name="password" id="password" value=""
                       class="input input-sm input-bordered w-full" autocomplete="new-password">
            </div>
        </div>
    </div>

    {{-- Wird per JS aus dem aktiven Treiber-Block befüllt, damit nur ein
         "database"-Feld an den Controller geht. --}}
    <input type="hidden" name="database" id="database" value="{{ old('database', $values['database']) }}">

    <div class="card-actions justify-between pt-2">
        <a href="{{ route('install.application') }}" class="btn btn-sm btn-ghost">{{ __('Zurück') }}</a>
        <button type="submit" class="btn btn-sm btn-primary">
            {{ __('Verbinden & migrieren') }}
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </button>
    </div>
</form>

<script>
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
