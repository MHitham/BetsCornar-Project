<aside id="sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-3">
        <div class="brand-icon">
            <i class="bi bi-heart-pulse-fill"></i>
        </div>
        <div>
            
            <div class="brand-eyebrow">{{ \App\Models\Setting::get('clinic_name', 'عيادة بيطرية') }}</div>
            <div class="brand-text">{{ __('messages.app_name') }}</div>
            <div class="brand-sub">لوحة تشغيل العيادة البيطرية</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        
        @role('admin')
            <div class="nav-label">الرئيسية</div>
            <a href="{{ route('dashboard') }}"
               class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> {{ __('messages.nav_dashboard') }}
            </a>

            <div class="nav-label mt-2">الوحدات</div>
            <a href="{{ route('customers.index') }}"
               class="sidebar-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i> {{ __('messages.nav_customers') }}
            </a>
            <a href="{{ route('invoices.index') }}"
               class="sidebar-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> {{ __('messages.nav_invoices') }}
            </a>
            <a href="{{ route('products.index') }}"
               class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam-fill"></i> {{ __('messages.nav_products') }}
            </a>
            <a href="{{ route('vaccine-batches.index') }}"
               class="sidebar-link {{ request()->routeIs('vaccine-batches.*') ? 'active' : '' }}">
                <i class="bi bi-capsule-pill"></i> {{ __('messages.nav_vaccine_batches') }}
            </a>
            <a href="{{ route('vaccinations.index') }}"
               class="sidebar-link {{ request()->routeIs('vaccinations.*') ? 'active' : '' }}">
                <i class="bi bi-shield-plus"></i> {{ __('messages.nav_vaccinations') }}
            </a>
            
            <a href="{{ route('expenses.index') }}"
               class="sidebar-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                <i class="bi bi-wallet2"></i> {{ __('expenses.title') }}
            </a>
            

            <a href="{{ route('reports.index') }}"
               class="sidebar-link {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line-fill"></i> {{ __('reports.title') }}
            </a>

            
            <div class="nav-label mt-2">المشتريات</div>
            <a href="{{ route('suppliers.index') }}"
               class="sidebar-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                <i class="bi bi-truck"></i> الموردين
            </a>
            <a href="{{ route('purchases.index') }}"
               class="sidebar-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
                <i class="bi bi-cart-check-fill"></i> فواتير الشراء
            </a>
            
            <a href="{{ route('users.index') }}"
               class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="bi bi-person-badge-fill"></i> المستخدمين
            </a>
            
            <a href="{{ route('settings.index') }}"
               class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="bi bi-sliders"></i> إعدادات النظام
            </a>
            
            <a href="{{ route('backup.index') }}"
               class="sidebar-link {{ request()->is('settings/backup*') ? 'active' : '' }}">
                <i class="bi bi-gear"></i> النسخ الاحتياطي
            </a>
        @endrole

        
        @role('employee')
            <div class="nav-label">القائمة</div>
            <a href="{{ route('invoices.index') }}"
               class="sidebar-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i> الفواتير
            </a>
            <a href="{{ route('customers.create') }}"
               class="sidebar-link {{ request()->routeIs('customers.create') ? 'active' : '' }}">
                <i class="bi bi-plus-circle-fill"></i> زيارة جديدة
            </a>
            <a href="{{ route('invoices.create') }}"
               class="sidebar-link {{ request()->routeIs('invoices.create') ? 'active' : '' }}">
                <i class="bi bi-lightning-fill"></i> بيع سريع
            </a>
        @endrole
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer__title">نظام {{ \App\Models\Setting::get('clinic_name', 'عيادة بيطرية') }}</div>
        <div class="sidebar-footer__meta">&copy; {{ date('Y') }}</div>
    </div>
</aside>

<div id="sidebar-overlay" onclick="closeSidebar()"></div>
