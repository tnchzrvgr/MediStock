@php
// the menu (key => text and link)
$menu_items = array(
    "dashboard"     => array("Dashboard", route('dashboard')),
    "inventory"     => array("Inventory", route('inventory')),
    "sales"         => array("Sales", route('sales')),
    "alerts"        => array("Alerts & Reorder", route('alerts'))
);

$system_items = array();

// only the Owner / Admin sees these pages
if (is_admin()) {
    $menu_items = array(
        "dashboard"     => array("Dashboard", route('dashboard')),
        "inventory"     => array("Inventory", route('inventory')),
        "sales"         => array("Sales", route('sales')),
        "analytics"     => array("Analytics", route('analytics')),
        "alerts"        => array("Alerts & Reorder", route('alerts')),
        "reports"       => array("Reports", route('reports')),
        "suppliers"     => array("Suppliers", route('suppliers'))
    );

    $system_items = array(
        "users"  => array("User Management", route('users')),
        "backup" => array("Backup & Security", route('backup')),
        "settings" => array("Settings", route('settings'))
    );
}
@endphp
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="{{ asset('img/medistock-icon.svg') }}" class="logo-circle" alt="MediStock logo">
        <span class="logo-text">MediStock</span>
    </div>

    <p class="sidebar-label sidebar-label-first">MENU</p>
    @foreach ($menu_items as $key => $item)
        <a href="{{ $item[1] }}" class="nav-item {{ $current_page == $key ? 'active' : '' }}">
            <span class="nav-dot"></span>{{ $item[0] }}
        </a>
    @endforeach

    @if (count($system_items) > 0)
        <p class="sidebar-label sidebar-label-system">SYSTEM</p>
        @foreach ($system_items as $key => $item)
            <a href="{{ $item[1] }}" class="nav-item {{ $current_page == $key ? 'active' : '' }}">
                <span class="nav-dot"></span>{{ $item[0] }}
            </a>
        @endforeach
    @endif
</div>
