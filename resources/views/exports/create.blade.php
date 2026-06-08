@extends('layouts.app')

@section('title', __('Export erstellen'))
@section('nav-title', __('Export erstellen'))

@section('content')
<x-index-page :subtitle="__('Genehmigte Monatsfreigaben in einen Lohnabrechnungs-Export zusammenfassen.')">
    @if (session('error'))
        <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
    @endif

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('exports.store') }}" class="space-y-4">
                @csrf

                @if ($errors->any())
                    <div role="alert" class="alert alert-error">
                        <ul class="list-disc list-inside text-sm">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text">{{ __('Jahr') }}</span>
                        <input type="number" name="year" min="2000" max="2999"
                               value="{{ old('year', $defaultYear) }}"
                               class="input input-sm input-bordered" required />
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Monat') }}</span>
                        <select name="month" class="select select-sm select-bordered" required>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) old('month', $defaultMonth) === $m)>
                                    {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                </option>
                            @endfor
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Profil') }}</span>
                        <select name="profile" class="select select-sm select-bordered" required>
                            @foreach ($profiles as $key => $label)
                                <option value="{{ $key }}" @selected(old('profile') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text">{{ __('Scope') }}</span>
                        <select name="scope" class="select select-sm select-bordered" id="scope-select" required>
                            <option value="organization" @selected(old('scope', 'organization') === 'organization')>{{ __('Gesamte Organisation') }}</option>
                            <option value="user" @selected(old('scope') === 'user')>{{ __('Einzelne Person') }}</option>
                        </select>
                    </label>
                    <label class="form-control sm:col-span-2" id="scope-user-wrapper" style="display:none">
                        <span class="label-text">{{ __('Mitarbeiter:in') }}</span>
                        <select name="scope_user_id" class="select select-sm select-bordered">
                            <option value="">{{ __('— bitte wählen —') }}</option>
                            @foreach ($teamUsers as $u)
                                <option value="{{ $u->sqid }}" @selected((string) old('scope_user_id') === $u->sqid)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('exports.index') }}" class="btn btn-sm btn-ghost">{{ __('Abbrechen') }}</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="material-symbols-outlined text-base">play_arrow</span>
                        {{ __('Erstellen') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-index-page>

<script @cspNonce>
    (function () {
        const scope = document.getElementById('scope-select');
        const wrapper = document.getElementById('scope-user-wrapper');
        const sync = () => { wrapper.style.display = scope.value === 'user' ? '' : 'none'; };
        scope.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
