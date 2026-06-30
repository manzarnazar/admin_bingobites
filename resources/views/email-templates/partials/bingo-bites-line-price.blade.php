@php
    $fontSize = $font_size ?? '14px';
    $grey = '#666666';
@endphp
@if ($item['show_price_strikethrough'] ?? false)
    <div style="font-size:{{ $fontSize }};color:{{ $grey }};text-decoration:line-through;line-height:{{ $lh ?? '1.15' }};">
        {{ $item['display_original_total_formatted'] }}
    </div>
@endif
<div style="font-size:{{ $fontSize }};line-height:{{ $lh ?? '1.15' }};">
    @if ($item['show_free_label'] ?? false)
        <strong>{{ $mode === 'pdf' ? translate('FREE') : 'FREE' }}</strong>
    @endif
    {{ $item['display_total_formatted'] ?? \App\CentralLogics\Helpers::set_symbol($item['display_total'] ?? 0) }}
</div>
