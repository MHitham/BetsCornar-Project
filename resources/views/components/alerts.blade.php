@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4 app-alert" role="alert">
        <div class="app-alert__row">
            <span class="app-alert__icon">
                <i class="bi bi-check-circle-fill"></i>
            </span>
            <div class="flex-grow-1">
                <div class="app-alert__title">تمت العملية بنجاح</div>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show mb-4 app-alert" role="alert">
        <div class="app-alert__row">
            <span class="app-alert__icon">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </span>
            <div class="flex-grow-1">
                <div class="app-alert__title">تنبيه</div>
                <div>{{ session('warning') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4 app-alert" role="alert">
        <div class="app-alert__row">
            <span class="app-alert__icon">
                <i class="bi bi-exclamation-octagon-fill"></i>
            </span>
            <div class="flex-grow-1">
                <div class="app-alert__title">حدث خطأ</div>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger mb-4 app-alert" role="alert">
        <div class="app-alert__row">
            <span class="app-alert__icon">
                <i class="bi bi-shield-exclamation"></i>
            </span>
            <div class="flex-grow-1">
                <div class="app-alert__title">{{ __('messages.validation_errors') }}</div>
                <ul class="mb-0 mt-2 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
