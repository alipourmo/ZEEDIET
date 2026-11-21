<nav class="tabs" aria-label="{{ __('catalog.nav') }}">
    <a class="tabs__link" href="{{ route('admin.services.index') }}" @if ($active === 'services') aria-current="page" @endif>{{ __('catalog.services') }}</a>
    <a class="tabs__link" href="{{ route('admin.devices.index') }}" @if ($active === 'devices') aria-current="page" @endif>{{ __('catalog.devices') }}</a>
    <a class="tabs__link" href="{{ route('admin.rooms.index') }}" @if ($active === 'rooms') aria-current="page" @endif>{{ __('catalog.rooms') }}</a>
</nav>
