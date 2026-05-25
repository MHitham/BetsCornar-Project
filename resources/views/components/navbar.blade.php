<header id="topbar">
    <div class="topbar-leading">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>

        <div class="topbar-title-wrap">
            <span class="topbar-kicker">
                <i class="bi bi-heart-pulse"></i>
                {{ \App\Models\Setting::get('clinic_name', 'عيادة بيطرية') }}
            </span>
            <h1 class="page-title">@yield('page-title', \App\Models\Setting::get('clinic_name', 'عيادة بيطرية'))</h1>
        </div>
    </div>

    <div class="topbar-actions">
        
        @role('admin')
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary position-relative"
                    id="notificationBell"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    title="الإشعارات">
                <i class="bi bi-bell-fill"></i>
                <span id="notification-bell-badge"
                      class="position-absolute top-0 start-0 translate-middle
                             badge rounded-pill bg-danger"
                      style="display:none; font-size:0.65rem">
                    0
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow"
                 style="width:320px; max-height:400px; overflow-y:auto">
                <div class="dropdown-header fw-bold border-bottom pb-2">
                    <i class="bi bi-bell me-1"></i> الإشعارات
                </div>
                <div id="notification-dropdown-list">
                    <div class="dropdown-item text-muted text-center py-3">
                        جاري التحميل...
                    </div>
                </div>
            </div>
        </div>
        @endrole

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
