<a class="calendar-block calendar-block--{{ $block['status'] }}" href="{{ $block['url'] }}">
    <span class="calendar-block__time">{{ $block['time'] }}</span>
    <span class="calendar-block__client">{{ $block['client'] }}</span>
    @if ($block['service'] !== '')
        <span class="calendar-block__service">{{ $block['service'] }}</span>
    @endif
    <span class="calendar-block__status">{{ $block['label'] }}</span>
</a>
