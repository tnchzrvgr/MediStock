{{-- top header used by every page --}}
<div class="topbar">
    <div class="topbar-left">
        <button type="button" class="menu-btn" onclick="toggleMenu()">&#9776;</button>
        <div class="title-block">
            <h1>{{ $page_title }}</h1>
            <p>{{ $page_subtitle }}</p>
        </div>
    </div>

    <div class="topbar-right">
        @if (isset($show_search) && $show_search)
            <form method="GET" action="{{ $search_action }}" class="search-form">
                @if (isset($search_filter))
                    <input type="hidden" name="filter" value="{{ $search_filter }}">
                @endif
                <input type="text" name="q" class="search-box" placeholder="Search medicines, orders..." value="{{ $search_value }}">
            </form>
            <a href="{{ route('alerts') }}" class="bell-btn"><span class="bell-dot"></span></a>
        @endif

        <div class="user-box">
            <span class="avatar"></span>
            <div class="user-info">
                <span class="user-name">{{ session('full_name') }}</span>
                <span class="user-role">{{ session('role') }}</span>
            </div>
        </div>
        <a href="{{ route('logout') }}" class="logout-link">Logout</a>
    </div>
</div>
