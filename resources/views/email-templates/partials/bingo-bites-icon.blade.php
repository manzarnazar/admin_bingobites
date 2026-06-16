@php
    $iconMode = $mode ?? 'email';
    $iconSize = $size ?? ($iconMode === 'pdf' ? 14 : 16);
    $iconName = $name ?? 'customer';
    $iconPath = $icons[$iconName] ?? null;
    $iconCid = ($icon_cids[$iconName] ?? ('icon_' . $iconName));
@endphp
@if ($iconMode === 'email')
    <img src="cid:{{ $iconCid }}" width="{{ $iconSize }}" height="{{ $iconSize }}" alt="" style="vertical-align:middle;margin-right:6px;border:0;display:inline-block;">
@elseif ($iconPath && is_file($iconPath))
    <img src="{{ $iconPath }}" width="{{ $iconSize }}" height="{{ $iconSize }}" alt="" style="vertical-align:middle;margin-right:6px;">
@endif
