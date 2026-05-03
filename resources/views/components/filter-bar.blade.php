@props([
    'action' => null,
    'method' => 'GET',
])

<form
    method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}"
    @if ($action) action="{{ $action }}" @endif
    {{ $attributes->class(['flex-none rounded-box border border-base-300 bg-base-200 p-4']) }}
>
    @if (strtoupper($method) !== 'GET')
        @csrf
    @endif
    <div class="flex flex-wrap items-end gap-4">
        {{ $slot }}
    </div>
</form>
