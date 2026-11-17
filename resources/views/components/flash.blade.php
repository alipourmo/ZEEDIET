@php
    $messages = collect(['status', 'success', 'warning', 'error'])
        ->filter(fn (string $type): bool => session()->has($type))
        ->map(fn (string $type): array => ['type' => $type, 'message' => session($type)]);
@endphp

@foreach ($messages as $flash)
    <div {{ $attributes->class(['flash', 'flash--'.$flash['type']]) }}
         role="{{ $flash['type'] === 'error' ? 'alert' : 'status' }}">
        {{ $flash['message'] }}
    </div>
@endforeach
