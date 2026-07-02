@php
    $user = auth()->user();
@endphp

<aside class="sidebar">
    <div class="sidebar-header">
        <a class="brand" href="{{ route('admin.dashboard') }}">
            <span class="logo-icon">S</span><span>{{ config('app.name') }} ERP</span>
        </a>
        <div class="small" style="margin-top:5px;color:#94a3b8">Admin Web Portal</div>
    </div>

    @if ($user)
        <div class="user">
            <div class="avatar">{{ $user->initials() }}</div>
            <div>
                <div style="color:#fff;font-weight:800">{{ $user->name }}</div>
                <div class="small" style="color:#94a3b8">{{ $user->primaryRole()?->name ?? 'User' }}</div>
            </div>
        </div>
    @endif

    <div class="nav-title">Main</div>
    <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span>📊</span><span>Dashboard</span></a>

    <div class="nav-title">Administration</div>
    <a class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span>👥</span><span>Users</span></a>
    <a class="nav-item {{ request()->routeIs('admin.roles.index', 'admin.roles.create', 'admin.roles.show', 'admin.roles.edit') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}"><span>🛡️</span><span>Roles</span></a>
    <a class="nav-item {{ request()->routeIs('admin.roles.permission-matrix') ? 'active' : '' }}" href="{{ route('admin.roles.permission-matrix') }}"><span>✅</span><span>Permission Matrix</span></a>
    <a class="nav-item {{ request()->routeIs('admin.roles.hierarchy') ? 'active' : '' }}" href="{{ route('admin.roles.hierarchy') }}"><span>🌳</span><span>Role Hierarchy</span></a>
    <a class="nav-item {{ request()->routeIs('admin.roles.approval-workflows.*') ? 'active' : '' }}" href="{{ route('admin.roles.approval-workflows.index') }}"><span>🔁</span><span>Approval Workflows</span></a>
    <a class="nav-item {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}"><span>🧾</span><span>Activity Logs</span></a>

    <div class="nav-title">Master Setup</div>
    <a class="nav-item {{ request()->routeIs('admin.master.company-profile') ? 'active' : '' }}" href="{{ route('admin.master.company-profile') }}"><span>🏢</span><span>Company Profile</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.branches.*') ? 'active' : '' }}" href="{{ route('admin.master.branches.index') }}"><span>🏬</span><span>Branches</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.departments.*') ? 'active' : '' }}" href="{{ route('admin.master.departments.index') }}"><span>🗂️</span><span>Departments</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.designations.*') ? 'active' : '' }}" href="{{ route('admin.master.designations.index') }}"><span>🪪</span><span>Designations</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.projects.*') ? 'active' : '' }}" href="{{ route('admin.master.projects.index') }}"><span>🏗️</span><span>Projects</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.sites.*') ? 'active' : '' }}" href="{{ route('admin.master.sites.index') }}"><span>📍</span><span>Sites / Geo-Fence</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.warehouses.*') ? 'active' : '' }}" href="{{ route('admin.master.warehouses.index') }}"><span>🏭</span><span>Warehouses</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.expense-categories.*') ? 'active' : '' }}" href="{{ route('admin.master.expense-categories.index') }}"><span>🏷️</span><span>Expense Categories</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.suppliers.*') ? 'active' : '' }}" href="{{ route('admin.master.suppliers.index') }}"><span>🚚</span><span>Suppliers</span></a>
    <a class="nav-item {{ request()->routeIs('admin.master.customers.*') ? 'active' : '' }}" href="{{ route('admin.master.customers.index') }}"><span>🤝</span><span>Customers</span></a>

    <div class="nav-title">HR &amp; Payroll</div>
    @foreach ([
        'employees' => ['👷', 'Employees'],
        'attendance' => ['🕒', 'Attendance'],
        'shifts' => ['🔄', 'Shifts'],
        'leaves' => ['🌴', 'Leaves'],
        'payroll' => ['💵', 'Payroll'],
        'documents-iqama' => ['🪪', 'Documents / IQAMA'],
    ] as $slug => [$icon, $label])
        <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === $slug ? 'active' : '' }}" href="{{ route('admin.coming-soon', $slug) }}"><span>{{ $icon }}</span><span>{{ $label }}</span><span class="soon">Soon</span></a>
    @endforeach

    <div class="nav-title">Accounting</div>
    @foreach ([
        'chart-of-accounts' => ['📒', 'Chart of Accounts'],
        'journal-entries' => ['📓', 'Journal Entries'],
        'general-ledger' => ['📚', 'General Ledger'],
        'payables' => ['📤', 'Payables'],
        'receivables' => ['📥', 'Receivables'],
        'vat-management' => ['🧮', 'VAT Management'],
        'financial-reports' => ['📈', 'Financial Reports'],
    ] as $slug => [$icon, $label])
        <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === $slug ? 'active' : '' }}" href="{{ route('admin.coming-soon', $slug) }}"><span>{{ $icon }}</span><span>{{ $label }}</span><span class="soon">Soon</span></a>
    @endforeach

    <div class="nav-title">Projects</div>
    @foreach ([
        'project-dashboard' => ['📊', 'Project Dashboard'],
        'project-budget' => ['💰', 'Project Budget'],
        'project-costing' => ['🧾', 'Project Costing'],
        'site-expenses' => ['🧰', 'Site Expenses'],
        'budget-vs-actual' => ['⚖️', 'Budget vs Actual'],
    ] as $slug => [$icon, $label])
        <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === $slug ? 'active' : '' }}" href="{{ route('admin.coming-soon', $slug) }}"><span>{{ $icon }}</span><span>{{ $label }}</span><span class="soon">Soon</span></a>
    @endforeach

    <div class="nav-title">Inventory</div>
    @foreach ([
        'materials' => ['📦', 'Materials'],
        'stock-in' => ['📩', 'Stock In'],
        'stock-out' => ['📨', 'Stock Out'],
        'stock-transfers' => ['🔀', 'Stock Transfers'],
        'low-stock-alerts' => ['🚨', 'Low Stock Alerts'],
    ] as $slug => [$icon, $label])
        <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === $slug ? 'active' : '' }}" href="{{ route('admin.coming-soon', $slug) }}"><span>{{ $icon }}</span><span>{{ $label }}</span><span class="soon">Soon</span></a>
    @endforeach

    <div class="nav-title">Equipment &amp; Vehicles</div>
    @foreach ([
        'equipment' => ['🚜', 'Equipment'],
        'vehicles' => ['🚛', 'Vehicles'],
        'gps-tracking' => ['🛰️', 'GPS Tracking'],
        'maintenance' => ['🔧', 'Maintenance'],
        'fuel-tracking' => ['⛽', 'Fuel Tracking'],
    ] as $slug => [$icon, $label])
        <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === $slug ? 'active' : '' }}" href="{{ route('admin.coming-soon', $slug) }}"><span>{{ $icon }}</span><span>{{ $label }}</span><span class="soon">Soon</span></a>
    @endforeach

    <div class="nav-title">ZATCA E-Invoicing</div>
    @foreach ([
        'zatca-invoices' => ['🧾', 'Invoices'],
        'zatca-qr-code' => ['🔳', 'QR Code'],
        'zatca-xml' => ['🗎', 'XML'],
        'zatca-clearance' => ['✔️', 'Clearance Status'],
    ] as $slug => [$icon, $label])
        <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === $slug ? 'active' : '' }}" href="{{ route('admin.coming-soon', $slug) }}"><span>{{ $icon }}</span><span>{{ $label }}</span><span class="soon">Soon</span></a>
    @endforeach

    <div class="nav-title">System</div>
    <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === 'reports' ? 'active' : '' }}" href="{{ route('admin.coming-soon', 'reports') }}"><span>📈</span><span>Reports</span><span class="soon">Soon</span></a>
    <a class="nav-item {{ request()->routeIs('admin.coming-soon') && request()->route('module') === 'settings' ? 'active' : '' }}" href="{{ route('admin.coming-soon', 'settings') }}"><span>⚙️</span><span>Settings</span><span class="soon">Soon</span></a>

    <div style="height:24px"></div>
</aside>
