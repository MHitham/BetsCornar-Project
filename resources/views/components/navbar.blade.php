<header id="topbar">
    <div class="topbar-leading">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>

        <div class="topbar-title-wrap">
            <span class="topbar-kicker">
                <i class="bi bi-heart-pulse"></i>
                {{ __('messages.app_name') }}
            </span>
            <h1 class="page-title">@yield('page-title', __('messages.app_name'))</h1>
        </div>
    </div>

    <div class="topbar-actions">
        <div class="topbar-user-chip">
            <span class="topbar-user-icon">
                <i class="bi bi-person-circle"></i>
            </span>
            <div class="topbar-user-meta">
                <span class="topbar-user-label">المستخدم الحالي</span>
                <strong class="topbar-user-name">{{ auth()->user()->name }}</strong>
            </div>
        </div>

        <div class="topbar-shortcuts">
            <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>{{ __('messages.new_visit') }}
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-lightning-fill me-1"></i>{{ __('messages.quick_sale') }}
            </a>

            {{-- تم الإضافة: زر تسجيل الخروج لجميع المستخدمين --}}
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm" title="تسجيل خروج">
                    <i class="bi bi-box-arrow-left me-1"></i>
                    <span class="d-none d-sm-inline">خروج</span>
                </button>
            </form>
        </div>
    </div>
</header>
