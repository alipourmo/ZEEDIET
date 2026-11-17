@props(['status' => 'scheduled'])

{{-- Status chip colours follow spec section 11. --}}
<span {{ $attributes->class(['badge', 'badge--'.$status]) }}>{{ $slot }}</span>
