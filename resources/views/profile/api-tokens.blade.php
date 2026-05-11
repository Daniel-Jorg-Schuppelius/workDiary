@extends('layouts.app')

@section('content')
<div class="mx-auto flex h-[calc(100dvh-11rem)] max-w-3xl flex-col gap-6 overflow-auto p-4">
    <h1 class="text-2xl font-semibold">{{ __('API-Tokens') }}</h1>

    @if (! empty($newToken))
            <div class="space-y-2">
                <p class="font-semibold">{{ __('Neuer Token erstellt') }} ({{ $newTokenName }})</p>
                <p class="text-sm">{{ __('Dieser Token wird nur einmalig angezeigt — bitte sicher speichern:') }}</p>
                <code class="block break-all rounded bg-base-200 p-2 text-sm">{{ $newToken }}</code>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.api-tokens.store') }}" class="card border border-base-300 bg-base-100 p-4">
        @csrf
        <div class="form-control">
            <label class="label" for="name">{{ __('Token-Name') }}</label>
            <input id="name" name="name" type="text" class="input input-bordered" maxlength="64" required>
        </div>
        @error('name')<div class="text-error text-sm">{{ $message }}</div>@enderror
        <div class="card-actions justify-end mt-3">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Erstellen') }}</button>
        </div>
    </form>

    <x-table>
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Erstellt') }}</th>
                        <th>{{ __('Zuletzt benutzt') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td>{{ optional($token->created_at)->format('d.m.Y H:i') }}</td>
                            <td>{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : '—' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('profile.api-tokens.destroy', $token->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-xs text-error" onclick="return confirm('{{ __('Token wirklich widerrufen?') }}')">{{ __('Widerrufen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center opacity-60">{{ __('Keine Einträge.') }}</td></tr>
                    @endforelse
                </tbody>
    </x-table>
</div>
@endsection
