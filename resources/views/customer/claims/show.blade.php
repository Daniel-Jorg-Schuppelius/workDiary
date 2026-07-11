@extends('customer.layout')

@section('title', $claim->number)

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ $claim->number }} — {{ $claim->title }}</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <p><span class="badge badge-outline">{{ $claim->status->label() }}</span></p>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <dt class="text-base-content/60">{{ __('Gemeldet am') }}</dt>
                <dd>{{ $claim->reported_at->fdate() }}</dd>
                <dt class="text-base-content/60">{{ __('Rücksendungen') }}</dt>
                <dd>
                    @if ($claim->rmaReturns->isEmpty())
                        —
                    @else
                        @foreach ($claim->rmaReturns as $rma)
                            <span class="font-mono">{{ $rma->rma_number }}</span> ({{ $rma->status->label() }})
                        @endforeach
                    @endif
                </dd>
            </dl>
            @if ($claim->description !== null)
                <p class="whitespace-pre-line text-sm">{{ $claim->description }}</p>
            @endif
        </div>
    </div>

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h2 class="card-title text-base">{{ __('Nachreichung') }}</h2>
            <p class="text-sm text-base-content/60">{{ __('Ergänzende Informationen zu Ihrer Reklamation übermitteln.') }}</p>
            <form method="POST" action="{{ route('customer.claims.note', $claim) }}" class="space-y-2">
                @csrf
                <textarea name="note" rows="3" class="textarea textarea-bordered w-full" required minlength="3" maxlength="2000" placeholder="{{ __('Ihre Nachricht …') }}"></textarea>
                @error('note')<p class="text-sm text-error">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Absenden') }}</button>
            </form>
        </div>
    </div>

    <a class="link" href="{{ route('customer.claims.index') }}">{{ __('Zurück zur Übersicht') }}</a>
</div>
@endsection
