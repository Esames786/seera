<li>
    <span class="tree-node">
        <span class="badge {{ $node->account_type === 'asset' || $node->account_type === 'expense' ? 'blue' : 'purple' }}">{{ $node->account_code }}</span>
        <a href="{{ route('admin.accounting.chart-of-accounts.show', $node) }}">{{ $node->account_name }}</a>
        <span class="small">{{ ucfirst($node->account_type) }} · {{ ucfirst($node->normal_balance) }}</span>
        @if ($node->vat_applicable)<span class="badge yellow">VAT</span>@endif
        @if ($node->status !== 'active')<x-admin.status-badge :status="$node->status"/>@endif
    </span>

    @if ($node->children->isNotEmpty())
        <ul>
            @foreach ($node->children as $child)
                @include('admin.accounting.chart-of-accounts._tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
