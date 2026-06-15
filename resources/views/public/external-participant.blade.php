{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : external-participant.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Öffentliche, login-freie Read-Only-Seite für externe Beteiligte (Feature
  033). Zeigt datensparsam nur Titel/Status/Datum des Subjects sowie die per
  abilities erlaubten Aktionen. Keine internen Notizen oder vertraulichen
  Felder.
  Variablen: $token, $participant (ExternalParticipant), $subject, $context
--}}
@php
    use App\Enums\ExternalParticipant\ExternalAbility;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ __('external.public.title') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-3xl p-4 space-y-4">
    <div class="rounded-box bg-base-100 p-4 shadow">
        <div class="mb-1 flex items-center gap-2 text-xs text-base-content/60">
            <span class="badge badge-outline badge-sm">{{ $participant->party->label() }}</span>
            <span>{{ __('external.public.hello', ['name' => $participant->name]) }}</span>
        </div>
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ $context['title'] }}</h1>
        @if ($context['meta'])
            <div class="mt-1 text-sm text-base-content/70">{{ $context['meta'] }}</div>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($context['summary'])
        <div class="rounded-box bg-base-100 p-4 shadow">
            <p class="whitespace-pre-line text-sm">{{ $context['summary'] }}</p>
        </div>
    @endif

    <div class="rounded-box bg-base-100/60 p-3 text-xs text-base-content/60">
        {{ __('external.public.expires_note', ['date' => $participant->expires_at->fdatetime()]) }}
    </div>

    @if($participant->can(ExternalAbility::Comment))
        <form method="POST" action="{{ route('external.comment', ['token' => $token]) }}" class="rounded-box bg-base-100 p-4 shadow space-y-3">
            @csrf
            <h2 class="text-sm font-semibold">{{ __('external.public.comment_heading') }}</h2>
            <textarea name="body" class="textarea textarea-bordered w-full" rows="3" required minlength="2" maxlength="2000"
                      placeholder="{{ __('external.public.comment_placeholder') }}">{{ old('body') }}</textarea>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('external.public.comment_submit') }}</button>
            </div>
        </form>
    @endif

    @if($participant->can(ExternalAbility::Upload))
        <form method="POST" action="{{ route('external.upload', ['token' => $token]) }}" enctype="multipart/form-data"
              class="rounded-box bg-base-100 p-4 shadow space-y-3">
            @csrf
            <h2 class="text-sm font-semibold">{{ __('external.public.upload_heading') }}</h2>
            <input type="file" name="file" required class="file-input file-input-bordered w-full"
                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
            <p class="text-xs text-base-content/60">{{ __('external.public.upload_hint') }}</p>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('external.public.upload_submit') }}</button>
            </div>
        </form>
    @endif

    @if($participant->can(ExternalAbility::Confirm))
        <form method="POST" action="{{ route('external.confirm', ['token' => $token]) }}" class="rounded-box bg-base-100 p-4 shadow space-y-3">
            @csrf
            <h2 class="text-sm font-semibold">{{ __('external.public.confirm_heading') }}</h2>
            <textarea name="note" class="textarea textarea-bordered w-full" rows="2" maxlength="500"
                      placeholder="{{ __('external.public.confirm_note_placeholder') }}">{{ old('note') }}</textarea>
            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" name="accept" value="1" class="checkbox" required>
                <span class="label-text">{{ __('external.public.confirm_accept') }}</span>
            </label>
            <div class="flex justify-end">
                <button type="submit" class="btn btn-success btn-sm">{{ __('external.public.confirm_submit') }}</button>
            </div>
        </form>
    @endif

    @unless($participant->can(ExternalAbility::Comment) || $participant->can(ExternalAbility::Upload) || $participant->can(ExternalAbility::Confirm))
        <div class="rounded-box bg-base-100 p-4 text-sm text-base-content/70 shadow">
            {{ __('external.public.view_only') }}
        </div>
    @endunless
</main>
</body>
</html>
