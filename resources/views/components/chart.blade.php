@props([
    'type' => 'bars',
    'labels' => [],
    'series' => [],   // [ ['name' => 'Gross', 'data' => [..], 'color' => '#3b82f6'], ... ]
    'height' => 220,
])

@php
    $palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#64748b'];
    $series = collect($series)->map(function ($s, $i) use ($palette) {
        return [
            'name' => $s['name'] ?? 'Series '.($i + 1),
            'data' => array_map('floatval', $s['data'] ?? []),
            'color' => $s['color'] ?? $palette[$i % count($palette)],
        ];
    })->all();

    $w = 720;
    $h = (int) $height;
    $padL = 44; $padR = 12; $padT = 12; $padB = 26;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $allValues = collect($series)->flatMap(fn ($s) => $s['data'])->push(0);
    $max = max(1, (float) $allValues->max());
    $n = max(1, count($labels));
@endphp

<div {{ $attributes->merge(['class' => 'w-full overflow-x-auto']) }}>
    @if ($type === 'donut')
        @php
            $slices = $series[0]['data'] ?? [];
            $total = array_sum($slices) ?: 1;
            $angle = -90; $cx = 90; $cy = 90; $r = 78; $ir = 46;
        @endphp
        <div class="flex flex-wrap items-center gap-6">
            <svg viewBox="0 0 180 180" class="h-44 w-44 shrink-0">
                @foreach ($slices as $i => $val)
                    @php
                        $frac = $val / $total;
                        $a0 = deg2rad($angle); $angle += $frac * 360; $a1 = deg2rad($angle);
                        $large = $frac > 0.5 ? 1 : 0;
                        $x0 = $cx + $r * cos($a0); $y0 = $cy + $r * sin($a0);
                        $x1 = $cx + $r * cos($a1); $y1 = $cy + $r * sin($a1);
                        $xi1 = $cx + $ir * cos($a1); $yi1 = $cy + $ir * sin($a1);
                        $xi0 = $cx + $ir * cos($a0); $yi0 = $cy + $ir * sin($a0);
                        $color = $palette[$i % count($palette)];
                    @endphp
                    @if ($val > 0)
                        <path d="M {{ $x0 }} {{ $y0 }} A {{ $r }} {{ $r }} 0 {{ $large }} 1 {{ $x1 }} {{ $y1 }} L {{ $xi1 }} {{ $yi1 }} A {{ $ir }} {{ $ir }} 0 {{ $large }} 0 {{ $xi0 }} {{ $yi0 }} Z" fill="{{ $color }}" />
                    @endif
                @endforeach
            </svg>
            <ul class="flex flex-col gap-1 text-sm">
                @foreach ($labels as $i => $label)
                    <li class="flex items-center gap-2">
                        <span class="inline-block size-3 rounded-sm" style="background: {{ $palette[$i % count($palette)] }}"></span>
                        <span class="text-zinc-600 dark:text-zinc-300">{{ $label }}</span>
                        <span class="ml-auto font-medium">{{ number_format($slices[$i] ?? 0) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full" style="min-width: 520px" preserveAspectRatio="none">
            {{-- gridlines --}}
            @foreach ([0, 0.25, 0.5, 0.75, 1] as $g)
                @php $y = $padT + $plotH - ($g * $plotH); @endphp
                <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $w - $padR }}" y2="{{ $y }}" stroke="currentColor" stroke-opacity="0.1" />
                <text x="{{ $padL - 6 }}" y="{{ $y + 3 }}" text-anchor="end" font-size="9" fill="currentColor" fill-opacity="0.5">{{ number_format($max * $g) }}</text>
            @endforeach

            @if ($type === 'line')
                @foreach ($series as $s)
                    @php
                        $pts = [];
                        foreach ($s['data'] as $i => $v) {
                            $x = $padL + ($n <= 1 ? $plotW / 2 : $i * ($plotW / ($n - 1)));
                            $y = $padT + $plotH - ($v / $max * $plotH);
                            $pts[] = round($x, 1).','.round($y, 1);
                        }
                    @endphp
                    <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2" />
                @endforeach
            @else
                @php $groupW = $plotW / $n; $barW = $groupW / (count($series) + 0.5); @endphp
                @foreach ($labels as $i => $label)
                    @foreach ($series as $si => $s)
                        @php
                            $v = $s['data'][$i] ?? 0;
                            $bh = $v / $max * $plotH;
                            $x = $padL + $i * $groupW + $si * $barW + $barW * 0.25;
                            $y = $padT + $plotH - $bh;
                        @endphp
                        <rect x="{{ round($x, 1) }}" y="{{ round($y, 1) }}" width="{{ round($barW * 0.9, 1) }}" height="{{ round(max(0, $bh), 1) }}" fill="{{ $s['color'] }}" rx="1" />
                    @endforeach
                @endforeach
            @endif

            {{-- x labels (every Nth to avoid crowding) --}}
            @php $step = (int) ceil($n / 8); @endphp
            @foreach ($labels as $i => $label)
                @if ($i % $step === 0)
                    <text x="{{ round($padL + ($n <= 1 ? $plotW / 2 : $i * ($plotW / max(1, $n - ($type === 'line' ? 1 : 0)))) + ($type === 'line' ? 0 : $groupW / 2), 1) }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="9" fill="currentColor" fill-opacity="0.55">{{ $label }}</text>
                @endif
            @endforeach
        </svg>

        @if (count($series) > 1 || ($series[0]['name'] ?? '') !== '')
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-zinc-500">
                @foreach ($series as $s)
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block size-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }}
                    </span>
                @endforeach
            </div>
        @endif
    @endif
</div>
