@props(['route', 'filters', 'col'])
@php
    $active = ($filters['sort'] ?? '') === $col;
    $nextDir = $active && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
    $query = array_merge(
        collect($filters)->except(['sort', 'dir'])->filter(fn ($v) => $v !== '')->all(),
        ['sort' => $col, 'dir' => $nextDir]
    );
@endphp
<th>
    <a href="{{ route($route, $query) }}" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
        {{ $slot }}
        @if ($active)
            <i class="fas fa-sort-{{ $filters['dir'] === 'asc' ? 'up' : 'down' }} small"></i>
        @else
            <i class="fas fa-sort text-muted small"></i>
        @endif
    </a>
</th>
