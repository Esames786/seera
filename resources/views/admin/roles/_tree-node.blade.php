@php
    $icons = [
        'SUPER_ADMIN' => '👑', 'FINANCE_MANAGER' => '💰', 'HR_MANAGER' => '👷',
        'PROJECT_MANAGER' => '🏗️', 'SITE_SUPERVISOR' => '📍', 'MECHANIC' => '🔧',
        'SITE_WORKER' => '👷', 'INVENTORY_MANAGER' => '📦',
    ];
    $children = $childrenByParent->get($node->id, collect());
@endphp

<li>
    <a class="tree-node" href="{{ route('admin.roles.hierarchy', ['role' => $node->id]) }}"
       @if(($selectedRole?->id) === $node->id) style="border-color:var(--blue);background:#eef6ff" @endif>
        {{ $icons[$node->code] ?? '🛡️' }} {{ $node->name }}
    </a>
    @if ($children->isNotEmpty())
        <ul>
            @foreach ($children as $child)
                @include('admin.roles._tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
