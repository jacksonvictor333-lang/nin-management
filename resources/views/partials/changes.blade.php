{{-- Orodha ya mabadiliko (zamani → mpya) kwa kumbukumbu ya "person.updated". --}}
@php $changes = $log->properties['changes'] ?? []; @endphp
@if ($changes)
    <ul class="chg-list">
        @foreach (array_slice($changes, 0, $limit ?? 12) as $c)
            <li>
                <span class="chg-f">{{ ucfirst($c['label']) }}</span>
                @if ($c['old'] === null && $c['new'] === null)
                    <span class="chg-n">changed</span>
                @else
                    <span class="chg-o">{{ $c['old'] ?? 'empty' }}</span>
                    <i class="bi bi-arrow-right"></i>
                    <span class="chg-n">{{ $c['new'] ?? 'empty' }}</span>
                @endif
            </li>
        @endforeach
        @if (count($changes) > ($limit ?? 12))
            <li class="chg-more">+ {{ count($changes) - ($limit ?? 12) }} more</li>
        @endif
    </ul>
    @once
        <style>
            .chg-list { list-style: none; margin: 6px 0 2px; padding: 8px 10px; border-radius: 10px; background: #f8fafc; border: 1px solid #eef2f7; display: grid; gap: 4px; }
            .chg-list li { font-size: 12px; display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; line-height: 1.45; }
            .chg-list .chg-f { color: #64748b; min-width: 96px; font-weight: 600; }
            .chg-list .chg-o { color: #991b1b; text-decoration: line-through; text-decoration-color: rgba(153,27,27,.4); word-break: break-word; }
            .chg-list .chg-n { color: #166534; font-weight: 600; word-break: break-word; }
            .chg-list i { font-size: 10px; color: #94a3b8; }
            .chg-list .chg-more { color: #94a3b8; }
        </style>
    @endonce
@endif
