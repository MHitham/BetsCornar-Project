<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول</title>

    {{-- Bootstrap 5 RTL --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">

    {{-- Google Fonts: Tajawal --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap"
        rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/layout.css'])
</head>

<body class="auth-page">
    <div class="auth-shell">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-xl-10">
                    <div class="auth-card">
                        <div class="row g-0">
                            <div class="col-lg-5 auth-card__aside d-flex flex-column justify-content-between">
                                <div>
                                    <span class="auth-brand-badge">
                                        <i class="bi bi-heart-pulse-fill"></i>
                                        BetsCornar
                                    </span>
                                    <h1 class="auth-brand-title">عيادة بيتس كورنار البيطرية</h1>
                                    <p class="auth-brand-text">
                                        واجهة تشغيل واضحة وآمنة لإدارة الزيارات، التطعيمات، المخزون، والفواتير داخل العيادة اليومية.
                                    </p>
                                </div>

                                <div class="auth-feature-list">
                                    <div class="auth-feature">
                                        <span class="auth-feature__icon"><i class="bi bi-shield-check"></i></span>
                                        <span>إدارة دقيقة للبيانات الطبية والزيارات</span>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature__icon"><i class="bi bi-clipboard2-pulse"></i></span>
                                        <span>متابعة التطعيمات والتنبيهات اليومية</span>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature__icon"><i class="bi bi-receipt-cutoff"></i></span>
                                        <span>تنفيذ أسرع للفواتير والبيع السريع</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-7 bg-white">
                                <div class="auth-card__form">
                                    <div class="auth-form-header">
                                        <h3 class="fw-bold">تسجيل الدخول</h3>
                                        <p>أدخل بيانات الحساب للمتابعة إلى لوحة النظام.</p>
                                    </div>

                                    @if ($errors->any())
                                        <div class="alert alert-danger app-alert mb-4">
                                            <div class="app-alert__row">
                                                <span class="app-alert__icon">
                                                    <i class="bi bi-shield-exclamation"></i>
                                                </span>
                                                <div class="flex-grow-1">
                                                    <div class="app-alert__title">حدثت أخطاء</div>
                                                    <ul class="mb-0 mt-2 ps-3">
                                                        @foreach ($errors->all() as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('login') }}">
                                        @csrf

                                        <div class="mb-3">
                                            <label for="email" class="form-label">البريد الإلكتروني</label>
                                            <input id="email" name="email" type="email" class="form-control"
                                                value="{{ old('email') }}" required autocomplete="email" autofocus>
                                        </div>

                                        <div class="mb-3">
                                            <label for="password" class="form-label">كلمة المرور</label>
                                            <input id="password" name="password" type="password" class="form-control" required
                                                autocomplete="current-password">
                                        </div>

                                        <div class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
                                                {{ old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="remember">
                                                تذكرني
                                            </label>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100 py-3">
                                            <i class="bi bi-box-arrow-in-left me-2"></i>
                                            دخول
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center text-muted mt-3 small">
                        جميع الحقوق محفوظة &copy; {{ date('Y') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bootstrap 5 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
