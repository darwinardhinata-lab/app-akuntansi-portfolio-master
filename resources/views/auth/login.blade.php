<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('erp.login_page_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 50%, #e0e7ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .login-header {
            padding: 32px 32px 20px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .login-icon {
            width: 56px; height: 56px;
            background: #eff6ff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            font-size: 1.4rem;
            margin-bottom: 16px;
        }
        .login-body { padding: 28px 32px 32px; }
        .form-label { font-size: 0.82rem; font-weight: 600; color: #475569; }
        .btn-login {
            background: #2563eb;
            border: none;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
        }
        .btn-login:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-icon"><i class="fa-solid fa-layer-group"></i></div>
            <h1 class="h4 fw-bold mb-1" style="color:#0f172a;">{{ __('erp.erp_accounting_brand') }}</h1>
            <p class="text-muted small mb-0">{{ __('erp.login_to_continue') }}</p>
        </div>

        <div class="login-body">
            @if ($errors->any())
                <div class="alert alert-danger py-2 small" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('erp.email_label') }}</label>
                    <input type="email" name="email" id="email" class="form-control"
                           value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('erp.password_indo') }}</label>
                    <input type="password" name="password" id="password" class="form-control"
                           required autocomplete="current-password">
                </div>
                <div class="mb-4 form-check">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
                    <label class="form-check-label small text-muted" for="remember">{{ __('erp.remember_me') }}</label>
                </div>
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk
                </button>
            </form>
        </div>
    </div>
</body>
</html>
