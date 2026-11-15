@props(['person', 'size' => 40, 'radius' => null])

@php
    $path = $person->profile_picture;
    $hasPhoto = $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path);

    $initials = mb_strtoupper(
        mb_substr((string) $person->first_name, 0, 1) . mb_substr((string) $person->last_name, 0, 1)
    ) ?: '?';

    // Rangi tulivu isiyobadilika kwa kila mtu (kulingana na NIN/ID).
    $tints = [
        ['#dcfce7', '#15803d'],
        ['#dbeafe', '#1d4ed8'],
        ['#fef3c7', '#b45309'],
        ['#fce7f3', '#be185d'],
        ['#ede9fe', '#6d28d9'],
        ['#ccfbf1', '#0f766e'],
    ];
    [$bg, $fg] = $tints[crc32((string) ($person->nin ?: $person->id)) % count($tints)];

    $radius ??= (int) round($size * 0.28);
@endphp

<span {{ $attributes->merge(['class' => 'avatar-x']) }}
      style="width:{{ $size }}px;height:{{ $size }}px;min-width:{{ $size }}px;border-radius:{{ $radius }}px;background:{{ $bg }};color:{{ $fg }};font-size:{{ max(11, (int) round($size * 0.36)) }}px;display:inline-grid;place-items:center;overflow:hidden;font-weight:800;letter-spacing:.3px;flex-shrink:0;"
      title="{{ $person->full_name }}">
    @if ($hasPhoto)
        <img src="{{ asset('storage/' . $path) }}" alt="{{ $person->full_name }}" loading="lazy"
             style="width:100%;height:100%;object-fit:cover;display:block;">
    @else
        {{ $initials }}
    @endif
</span>
