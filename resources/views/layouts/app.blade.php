<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <script>
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>
    <title>@yield('title', 'Dashboard') — ERP Accounting System</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    /* ================================================================
       RESET & BASE
    ================================================================ */
    *, *::before, *::after { box-sizing: border-box; }

    :root {
        --sidebar-w:      260px;
        --topbar-h:        60px;
        --bottomnav-h:     64px;
        --accent:       #2563eb;
        --accent-light: #eff6ff;
        --accent-hover: #1d4ed8;
        --border:       #e2e8f0;
        --bg:           #f8fafc;
        --card:         #ffffff;
        --text:         #0f172a;
        --muted:        #64748b;
        --success:      #16a34a;
        --warning:      #d97706;
        --danger:       #dc2626;
        --sidebar-bg:   #ffffff;
        --radius:       10px;
        --transition:   0.28s cubic-bezier(.4,0,.2,1);
    }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg);
        color: var(--text);
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }

    /* ================================================================
       SIDEBAR REFRESH (MODERN UI/UX TREE-VIEW)
    ================================================================ */
    .sidebar {
        position: fixed; top: 0; left: 0; width: var(--sidebar-w); height: 100vh;
        background: var(--sidebar-bg); border-right: 1px solid var(--border);
        display: flex; flex-direction: column; z-index: 1040;
        transition: transform var(--transition); overflow: hidden;
        box-shadow: 2px 0 12px rgba(0,0,0,0.03);
    }

    .sidebar-inner {
        display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden;
        scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;
        flex-grow: 1; padding-bottom: 30px;
    }
    .sidebar-inner::-webkit-scrollbar { width: 5px; }
    .sidebar-inner::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 5px; }

    /* Brand Logo */
    .sidebar-brand {
        display: flex; align-items: center; gap: 10px; padding: 15px 18px;
        border-bottom: 1px solid var(--border); text-decoration: none;
    }
    .sidebar-brand-icon {
        width: 36px; height: 36px; background: linear-gradient(135deg, #2563eb, #3b82f6);
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        color: #ffffff; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 3px 8px rgba(37,99,235,0.3);
    }
    .sidebar-brand-text { line-height: 1.2; }
    .sidebar-brand-text .name { font-size: 0.9rem; font-weight: 800; color: var(--text); letter-spacing: -0.3px; }
    .sidebar-brand-text .sub { font-size: 0.65rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }

    /* Label Section Kategori */
    .nav-section { padding: 12px 14px 4px; }
    .nav-section-label {
        font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1px; color: #94a3b8; padding: 0 8px; margin-bottom: 8px; margin-top: 10px;
    }
    .nav-sub-label {
        font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #94a3b8; padding: 8px 8px 4px 28px;
    }

    /* LEVEL 1: Menu Utama (Section Toggle) */
    .section-toggle {
        display: flex; align-items: center; padding: 10px 12px;
        color: #334155; font-size: 0.85rem; font-weight: 700;
        border-radius: 10px; margin-bottom: 4px;
        transition: all 0.2s ease; text-decoration: none; cursor: pointer; user-select: none;
        border: 1px solid transparent;
    }
    .section-toggle:hover { background: #f8fafc; color: #0f172a; border-color: #f1f5f9; }
    .section-toggle .section-icon {
        width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
        border-radius: 8px; font-size: 0.9rem; margin-right: 12px; flex-shrink: 0;
        background: #f1f5f9; color: #64748b; transition: all 0.2s ease;
    }
    .section-toggle:hover .section-icon { background: #e2e8f0; color: #3b82f6; }
    .section-toggle.active { color: #1d4ed8; background: #eff6ff; border-color: #dbeafe; }
    .section-toggle.active .section-icon { background: #3b82f6; color: #ffffff; box-shadow: 0 2px 6px rgba(59,130,246,0.3); }
    .section-toggle .section-text { flex: 1; }
    .section-toggle .section-chevron { font-size: 0.7rem; color: #94a3b8; transition: transform 0.3s ease; margin-left: auto; }
    .section-toggle[aria-expanded="true"] .section-chevron { transform: rotate(180deg); color: #3b82f6; }

    /* Wadah Sub-Menu (Garis Vertikal Pohon Utama) */
    .section-collapse { 
        padding-left: 26px;
        position: relative;
        margin-bottom: 8px;
    }
    .section-collapse::before {
        content: ''; position: absolute; top: 0; bottom: 0; left: 26px;
        width: 2px; background-color: #f1f5f9; border-radius: 2px;
    }

    /* LEVEL 2: Sub-menu Links */
    .nav-link {
        display: flex; align-items: center; padding: 8px 12px 8px 24px;
        color: #475569; font-size: 0.82rem; font-weight: 600;
        border-radius: 8px; margin-bottom: 2px; position: relative;
        transition: all 0.2s ease; text-decoration: none;
    }
    .nav-link::before {
        content: ''; position: absolute; left: 0; top: 50%;
        width: 14px; height: 2px; background-color: #f1f5f9; border-radius: 2px;
        transition: all 0.2s ease;
    }
    .nav-link:hover { background: #f8fafc; color: #0f172a; }
    .nav-link:hover::before { background-color: #cbd5e1; width: 18px; }
    .nav-link.active { background: #eff6ff; color: #1d4ed8; font-weight: 700; }
    .nav-link.active::before { background-color: #3b82f6; width: 18px; }
    
    .nav-link .nav-icon {
        width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
        border-radius: 6px; font-size: 0.85rem; margin-right: 10px; flex-shrink: 0;
        color: #94a3b8; transition: all 0.2s ease;
    }
    .nav-link:hover .nav-icon { color: #3b82f6; transform: scale(1.1); }
    .nav-link.active .nav-icon { color: #2563eb; }
    .nav-link .nav-text { flex: 1; }
    .nav-link .nav-chevron { font-size: 0.65rem; color: #94a3b8; transition: transform 0.3s ease; margin-left: auto; }
    .nav-link[aria-expanded="true"] .nav-chevron { transform: rotate(180deg); color: #3b82f6; }

    /* LEVEL 3: Sub-submenu Container */
    .nav-submenu {
        position: relative;
        padding-left: 24px;
    }
    .nav-submenu::before {
        content: ''; position: absolute; top: 0; bottom: 0; left: 24px;
        width: 1px; border-left: 2px dotted #e2e8f0; background: transparent;
    }

    /* LEVEL 3: Links */
    .nav-submenu .nav-link {
        padding: 7px 10px 7px 22px;
        font-size: 0.78rem; font-weight: 500; color: #64748b;
        background: transparent; margin-bottom: 2px;
    }
    .nav-submenu .nav-link::before {
        left: 0; width: 14px; background-color: transparent; border-top: 2px dotted #e2e8f0; border-radius: 0; height: 0;
    }
    .nav-submenu .nav-link:hover { color: #1e293b; background: rgba(241, 245, 249, 0.5); }
    .nav-submenu .nav-link:hover::before { border-top-color: #94a3b8; width: 16px; }
    .nav-submenu .nav-link.active { color: #2563eb; font-weight: 700; background: transparent; }
    .nav-submenu .nav-link.active::before { border-top: 2px solid #3b82f6; width: 16px; }
    
    .nav-submenu .nav-link .nav-icon { display: none; }
    .nav-divider-inner { border: none; border-top: 1px dashed #cbd5e1; margin: 8px 14px 8px 26px; }
    .nav-divider { border: none; border-top: 1px solid var(--border); margin: 4px 10px; }

    /* ================================================================
       OVERLAY (Tablet & HP)
    ================================================================ */
    .sidebar-overlay {
        display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.45);
        z-index: 1039; backdrop-filter: blur(2px); opacity: 0; pointer-events: none;
        transition: opacity var(--transition);
    }
    .sidebar-overlay.show { display: block; opacity: 1; pointer-events: auto; }

    /* ================================================================
       TOP BAR (hanya tampil di Tablet & HP)
    ================================================================ */
    .topbar {
        display: none; position: fixed; top: 0; left: 0; right: 0; height: var(--topbar-h);
        background: var(--card); border-bottom: 1px solid var(--border); z-index: 1030;
        align-items: center; padding: 0 16px; gap: 12px;
    }
    .topbar-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; flex: 1; }
    .topbar-brand .icon {
        width: 32px; height: 32px; background: var(--accent-light); border-radius: 8px;
        display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 0.9rem; flex-shrink: 0;
    }
    .topbar-brand .name { font-size: 0.85rem; font-weight: 700; color: var(--text); }
    .topbar-brand .sub  { font-size: 0.6rem; color: var(--accent); font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; }

    .btn-hamburger {
        width: 38px; height: 38px; border: 1px solid var(--border); border-radius: 9px;
        background: var(--bg); display: flex; align-items: center; justify-content: center;
        cursor: pointer; color: var(--text); font-size: 1.1rem; flex-shrink: 0; transition: background 0.15s;
    }
    .btn-hamburger:hover { background: var(--border); }

    /* ================================================================
       MAIN CONTENT
    ================================================================ */
    .main-content {
        margin-left: var(--sidebar-w); padding: 16px 24px; min-height: 100vh;
        transition: margin var(--transition); width: calc(100vw - var(--sidebar-w));
        max-width: calc(100vw - var(--sidebar-w)); overflow-x: visible;
    }

    /* Compact top header */
    .main-header-compact { margin-bottom: 12px !important; padding-bottom: 8px !important; }
    .main-header-compact .btn { padding: 4px 12px 4px 4px !important; font-size: 0.82rem !important; }
    .main-header-compact .avatar-circle { width: 30px !important; height: 30px !important; font-size: 0.9rem !important; }
    .main-header-compact .user-name { font-size: 0.8rem !important; }
    .main-header-compact .user-role { font-size: 0.65rem !important; }

    /* ================================================================
       BOTTOM NAVIGATION (HP only)
    ================================================================ */
    .bottom-nav {
        display: none; position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottomnav-h);
        background: var(--card); border-top: 1px solid var(--border); z-index: 1045;
        padding: 6px 0 env(safe-area-inset-bottom, 0);
    }
    .bottom-nav-items { display: flex; height: 100%; }
    .bnav-item {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 3px; text-decoration: none; color: var(--muted); font-size: 0.68rem; font-weight: 500;
        padding: 4px; border-radius: 10px; transition: color 0.15s; cursor: pointer;
    }
    .bnav-item i { font-size: 1.25rem; }
    .bnav-item.active { color: var(--accent); }
    .bnav-item.active i { background: var(--accent-light); border-radius: 8px; padding: 4px 10px; }

    /* ================================================================
       RESPONSIVE
    ================================================================ */
    @media (max-width: 992px) {
        .sidebar { transform: translateX(calc(-1 * var(--sidebar-w))); box-shadow: none; }
        .sidebar.open { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,0.12); }
        .topbar { display: flex; }
        .main-content { margin-left: 0; padding: calc(var(--topbar-h) + 20px) 16px 20px; width: 100vw; max-width: 100vw; }
    }
    @media (max-width: 576px) {
        .bottom-nav { display: block; }
        .main-content { padding: calc(var(--topbar-h) + 16px) 12px calc(var(--bottomnav-h) + 16px); }
        .table { font-size: 0.78rem; } .table th, .table td { padding: 8px 6px; }
        .card { border-radius: 10px; } .card-body { padding: 14px; }
        .table .d-none-xs { display: none !important; }
    }

    /* GLOBAL FIXES */
    .main-content .container-fluid, .main-content .container { width: 100%; max-width: 100%; padding-left: 0; padding-right: 0; }
    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; box-shadow: inset 0 0 5px rgba(0,0,0,0.02); }

    /* ================================================================
       THEMES (DARK MODE) OVERRIDES
    ================================================================ */
    [data-bs-theme="dark"] {
        --bg:           #0f172a; 
        --card:         #1e293b; 
        --sidebar-bg:   #1e293b;
        --border:       #334155;
        --text:         #f8fafc;
        --muted:        #94a3b8;
        --accent-light: rgba(59, 130, 246, 0.15); 
    }
    
    /* Konfigurasi Tree-View Dark Mode */
    [data-bs-theme="dark"] .sidebar { box-shadow: 2px 0 10px rgba(0,0,0,0.5); }
    [data-bs-theme="dark"] .section-toggle { color: #cbd5e1; }
    [data-bs-theme="dark"] .section-toggle:hover { background: rgba(255,255,255,0.05); color: #f8fafc; border-color: rgba(255,255,255,0.05); }
    [data-bs-theme="dark"] .section-toggle .section-icon { background: rgba(255,255,255,0.05); color: #94a3b8; }
    [data-bs-theme="dark"] .section-toggle:hover .section-icon { background: rgba(255,255,255,0.1); color: #60a5fa; }
    [data-bs-theme="dark"] .section-toggle.active { color: #60a5fa; background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.2); }
    [data-bs-theme="dark"] .section-toggle.active .section-icon { background: #3b82f6; color: #ffffff; }

    [data-bs-theme="dark"] .section-collapse::before { background-color: #334155; }
    [data-bs-theme="dark"] .nav-link { color: #94a3b8; }
    [data-bs-theme="dark"] .nav-link::before { background-color: #334155; }
    [data-bs-theme="dark"] .nav-link:hover { background: rgba(255,255,255,0.03); color: #f8fafc; }
    [data-bs-theme="dark"] .nav-link:hover::before { background-color: #64748b; }
    [data-bs-theme="dark"] .nav-link.active { background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
    [data-bs-theme="dark"] .nav-link.active::before { background-color: #3b82f6; }

    [data-bs-theme="dark"] .nav-submenu::before { border-left-color: #334155; }
    [data-bs-theme="dark"] .nav-submenu .nav-link::before { border-top-color: #334155; }
    [data-bs-theme="dark"] .nav-submenu .nav-link:hover::before { border-top-color: #64748b; }
    [data-bs-theme="dark"] .nav-submenu .nav-link.active::before { border-top-color: #60a5fa; }
    [data-bs-theme="dark"] .nav-divider-inner { border-top-color: #334155; }

    /* Elemen Umum Dark Mode */
    [data-bs-theme="dark"] .bg-white, [data-bs-theme="dark"] .bg-white td { background-color: var(--card) !important; }
    [data-bs-theme="dark"] .bg-light { background-color: rgba(255,255,255,0.03) !important; }
    [data-bs-theme="dark"] .text-dark { color: var(--text) !important; }
    [data-bs-theme="dark"] .border, [data-bs-theme="dark"] .border-bottom, [data-bs-theme="dark"] .border-top { border-color: var(--border) !important; }
    [data-bs-theme="dark"] .text-muted { color: var(--muted) !important; }
    [data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2, [data-bs-theme="dark"] h3, [data-bs-theme="dark"] h4, [data-bs-theme="dark"] h5, [data-bs-theme="dark"] h6, [data-bs-theme="dark"] .text-black, [data-bs-theme="dark"] b, [data-bs-theme="dark"] strong { color: var(--text) !important; }
    [data-bs-theme="dark"] .table { --bs-table-bg: transparent; --bs-table-color: var(--text); border-color: var(--border); }
    [data-bs-theme="dark"] .table tbody td, [data-bs-theme="dark"] .table tbody th { color: var(--text) !important; border-color: var(--border) !important; }
    [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select { background-color: #0f172a; border-color: #334155; color: #f8fafc; }
    [data-bs-theme="dark"] .dropdown-menu { background-color: var(--card); border-color: var(--border); }
    [data-bs-theme="dark"] .dropdown-item { color: var(--text); }
    [data-bs-theme="dark"] .dropdown-item:hover { background-color: rgba(255,255,255,0.05); }
    </style>
    @stack('styles')
</head>
<body>
@php
    $companyProfile = \App\Models\CompanyProfile::first();
    $hasLogo = isset($companyProfile) && $companyProfile->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($companyProfile->logo);
@endphp

{{-- SIDEBAR OVERLAY --}}
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

{{-- TOP BAR (Tablet & HP) --}}
<div class="topbar" id="topbar">
    <button class="btn-hamburger" id="btnHamburger" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
    <a href="{{ route('dashboard') }}" class="topbar-brand">
        @if($hasLogo)
            <img src="{{ asset('storage/' . $companyProfile->logo) }}" alt="{{ __('erp.logo_alt') }}" style="height: 30px; max-width: 100px; object-fit: contain; margin-right: 8px; border-radius: 4px;">
        @else
            <div class="icon"><i class="fa-solid fa-layer-group"></i></div>
        @endif
        <div><div class="name">{{ $companyProfile->nama_perusahaan ?? 'ERP Accounting' }}</div><div class="sub">{{ __('erp.system_word') }}</div></div>
    </a>
    <button class="btn-hamburger" onclick="window.history.back()"><i class="fa-solid fa-arrow-left"></i></button>
</div>

{{-- SIDEBAR --}}
<aside class="sidebar d-flex flex-column vh-100" id="sidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-brand" onclick="closeSidebar()">
        @if($hasLogo)
            <img src="{{ asset('storage/' . $companyProfile->logo) }}" alt="{{ __('erp.logo_alt') }}" style="height: 35px; max-width: 100px; object-fit: contain; margin-right: 10px; border-radius: 4px;">
        @else
            <div class="sidebar-brand-icon"><i class="fa-solid fa-layer-group"></i></div>
        @endif
        <div class="sidebar-brand-text"><div class="name">{{ $companyProfile->nama_perusahaan ?? 'ERP Accounting' }}</div><div class="sub">{{ __('erp.system_word') }}</div></div>
    </a>

    <div class="sidebar-inner custom-scrollbar">
        {{-- MENU UTAMA (always visible) --}}
        <div class="nav-section">
            <div class="nav-section-label">{{ __('erp.main_menu') }}</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span>
                <span class="nav-text">{{ __('erp.dashboard') }}</span>
            </a>
            @if(auth()->check() && auth()->user()->role === 'ADMIN')
            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-users-gear text-danger"></i></span>
                <span class="nav-text text-danger fw-bold">{{ __('erp.user_management') }}</span>
            </a>
            <a href="{{ route('logs.index') }}" class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                <span class="nav-icon"><i class="fa-solid fa-clock-rotate-left text-warning"></i></span>
                <span class="nav-text text-warning fw-bold">{{ __('erp.system_activity_logs') }}</span>
            </a>
            @endif
        </div>

        {{-- ================================================================
             SECTION: MASTER DATA
        ================================================================ --}}
        <div class="nav-section">
            <a href="#sectionMaster" data-bs-toggle="collapse" class="section-toggle {{ request()->routeIs('product.*') || Request::is('divisi*') || request()->routeIs('budgeting.*') || request()->routeIs('company.*') || request()->routeIs('payment-category.*') ? 'active' : '' }}" aria-expanded="{{ request()->routeIs('product.*') || Request::is('divisi*') || request()->routeIs('budgeting.*') || request()->routeIs('company.*') || request()->routeIs('payment-category.*') ? 'true' : 'false' }}">
                <span class="section-icon"><i class="fa-solid fa-database text-info"></i></span>
                <span class="section-text">{{ __('erp.master_planning') }}</span>
                <i class="fa-solid fa-chevron-down section-chevron"></i>
            </a>
            <div class="collapse section-collapse {{ request()->routeIs('product.*') || Request::is('divisi*') || request()->routeIs('budgeting.*') || request()->routeIs('company.*') || request()->routeIs('payment-category.*') ? 'show' : '' }}" id="sectionMaster">
                <a href="{{ route('company.edit') }}" class="nav-link {{ request()->routeIs('company.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-building text-info"></i></span>
                    <span class="nav-text">{{ __('erp.bc_company_profile') }}</span>
                </a>
                <a href="{{ route('product.index') }}" class="nav-link {{ request()->routeIs('product.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-box-open text-info"></i></span>
                    <span class="nav-text">{{ __('erp.master_product') }}</span>
                </a>
                <a href="{{ route('divisi.index') }}" class="nav-link {{ Request::is('divisi*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-sitemap text-secondary"></i></span>
                    <span class="nav-text">{{ __('erp.master_division') }}</span>
                </a>
                <a href="{{ route('budgeting.index') }}" class="nav-link {{ request()->routeIs('budgeting.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-chart-pie text-primary"></i></span>
                    <span class="nav-text">{{ __('erp.budgeting') }}</span>
                </a>
                <a href="{{ route('payment-category.index') }}" class="nav-link {{ request()->routeIs('payment-category.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-tags text-success"></i></span>
                    <span class="nav-text">{{ __('erp.payment_category') }}</span>
                </a>
            </div>
        </div>

        {{-- ================================================================
             SECTION: WAREHOUSE
        ================================================================ --}}
        <div class="nav-section">
            <a href="#sectionWarehouse" data-bs-toggle="collapse" class="section-toggle {{ request()->routeIs('warehouse.*') ? 'active' : '' }}" aria-expanded="{{ request()->routeIs('warehouse.*') ? 'true' : 'false' }}">
                <span class="section-icon"><i class="fa-solid fa-warehouse text-secondary"></i></span>
                <span class="section-text">{{ __('erp.warehouse_gudang') }}</span>
                <i class="fa-solid fa-chevron-down section-chevron"></i>
            </a>
            <div class="collapse section-collapse {{ request()->routeIs('warehouse.*') ? 'show' : '' }}" id="sectionWarehouse">
                <a href="{{ route('warehouse.process-orders') }}" class="nav-link {{ request()->routeIs('warehouse.process-orders') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-boxes-packing text-primary"></i></span>
                    <span class="nav-text">{{ __('erp.bc_process_orders') }}</span>
                </a>
                <a href="{{ route('warehouse.inbound') }}" class="nav-link {{ request()->routeIs('warehouse.inbound') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-arrow-right-to-bracket text-success"></i></span>
                    <span class="nav-text">{{ __('erp.bc_goods_in') }}</span>
                </a>
                <a href="{{ route('warehouse.outbound') }}" class="nav-link {{ request()->routeIs('warehouse.outbound') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-arrow-right-from-bracket text-danger"></i></span>
                    <span class="nav-text">{{ __('erp.bc_goods_out') }}</span>
                </a>
            </div>
        </div>

        {{-- ================================================================
             SECTION: INFLOW (SALES)
        ================================================================ --}}
        <div class="nav-section">
            <a href="#sectionInflow" data-bs-toggle="collapse" class="section-toggle {{ request()->routeIs('so.*') || request()->routeIs('invoice.*') || request()->routeIs('sales-returns.*') || request()->routeIs('reports.ar_dp') || request()->routeIs('reports.ar_subledger') ? 'active' : '' }}" aria-expanded="{{ request()->routeIs('so.*') || request()->routeIs('invoice.*') || request()->routeIs('sales-returns.*') || request()->routeIs('reports.ar_dp') || request()->routeIs('reports.ar_subledger') ? 'true' : 'false' }}">
                <span class="section-icon"><i class="fa-solid fa-arrow-trend-up text-success"></i></span>
                <span class="section-text">{{ __('erp.sales_inflow') }}</span>
                <i class="fa-solid fa-chevron-down section-chevron"></i>
            </a>
            <div class="collapse section-collapse {{ request()->routeIs('so.*') || request()->routeIs('invoice.*') || request()->routeIs('sales-returns.*') || request()->routeIs('reports.ar_dp') || request()->routeIs('reports.ar_subledger') ? 'show' : '' }}" id="sectionInflow">
                <a href="{{ route('so.index') }}" class="nav-link {{ request()->routeIs('so.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-file-invoice-dollar text-success"></i></span>
                    <span class="nav-text">{{ __('erp.sales_order') }}</span>
                </a>
                <a href="{{ route('invoice.index') }}" class="nav-link {{ request()->routeIs('invoice.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-file-invoice-dollar text-success"></i></span>
                    <span class="nav-text">{{ __('erp.sales_invoice') }}</span>
                </a>
                <a href="{{ route('sales-returns.index') }}" class="nav-link {{ request()->routeIs('sales-returns.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-arrow-rotate-left text-danger"></i></span>
                    <span class="nav-text">{{ __('erp.sales_return') }}</span>
                </a>

                <a href="#menuArPiutang" data-bs-toggle="collapse" class="nav-link {{ request()->routeIs('reports.ar_dp') || request()->routeIs('reports.ar_subledger') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-file-invoice-dollar text-warning"></i></span>
                    <span class="nav-text">{{ __('erp.ar_dp') }}</span>
                    <i class="fa-solid fa-chevron-down nav-chevron"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('reports.ar_dp') || request()->routeIs('reports.ar_subledger') ? 'show' : '' }}" id="menuArPiutang">
                    <a href="{{ route('reports.ar_dp') }}" class="nav-link {{ request()->routeIs('reports.ar_dp') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-file-invoice-dollar text-warning"></i></span>
                        <span class="nav-text">{{ __('erp.ar_dp') }}</span>
                    </a>
                    <a href="{{ route('reports.ar_subledger') }}" class="nav-link {{ request()->routeIs('reports.ar_subledger') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-receipt text-warning"></i></span>
                        <span class="nav-text">{{ __('erp.ar_subledger') }}</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ================================================================
             SECTION: OUTFLOW (PURCHASE)
        ================================================================ --}}
        <div class="nav-section">
            <a href="#sectionOutflow" data-bs-toggle="collapse" class="section-toggle {{ request()->routeIs('po.*') || request()->routeIs('payment.*') || request()->routeIs('purchase-bills.*') || request()->routeIs('purchase-returns.*') || request()->routeIs('reports.ap_dp') || request()->routeIs('reports.ap_subledger') ? 'active' : '' }}" aria-expanded="{{ request()->routeIs('po.*') || request()->routeIs('payment.*') || request()->routeIs('purchase-bills.*') || request()->routeIs('purchase-returns.*') || request()->routeIs('reports.ap_dp') || request()->routeIs('reports.ap_subledger') ? 'true' : 'false' }}">
                <span class="section-icon"><i class="fa-solid fa-arrow-trend-down text-danger"></i></span>
                <span class="section-text">{{ __('erp.expense_outflow') }}</span>
                <i class="fa-solid fa-chevron-down section-chevron"></i>
            </a>
            <div class="collapse section-collapse {{ request()->routeIs('po.*') || request()->routeIs('payment.*') || request()->routeIs('purchase-bills.*') || request()->routeIs('purchase-returns.*') || request()->routeIs('reports.ap_dp') || request()->routeIs('reports.ap_subledger') ? 'show' : '' }}" id="sectionOutflow">
                <a href="{{ route('po.index') }}" class="nav-link {{ request()->routeIs('po.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-cart-shopping text-warning"></i></span>
                    <span class="nav-text">{{ __('erp.purchase_order') }}</span>
                </a>
                <a href="{{ route('payment.index') }}" class="nav-link {{ request()->routeIs('payment.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-money-check-dollar text-danger"></i></span>
                    <span class="nav-text">{{ __('erp.payment_plan') }}</span>
                </a>
                <a href="{{ route('purchase-bills.index') }}" class="nav-link {{ request()->routeIs('purchase-bills.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-file-invoice text-warning"></i></span>
                    <span class="nav-text">{{ __('erp.purchase_bill') }}</span>
                </a>
                <a href="{{ route('purchase-returns.index') }}" class="nav-link {{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-arrow-rotate-right text-warning"></i></span>
                    <span class="nav-text">{{ __('erp.purchase_return') }}</span>
                </a>

                <a href="#menuApHutang" data-bs-toggle="collapse" class="nav-link {{ request()->routeIs('reports.ap_dp') || request()->routeIs('reports.ap_subledger') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-file-invoice text-danger"></i></span>
                    <span class="nav-text">{{ __('erp.ap_dp') }}</span>
                    <i class="fa-solid fa-chevron-down nav-chevron"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('reports.ap_dp') || request()->routeIs('reports.ap_subledger') ? 'show' : '' }}" id="menuApHutang">
                    <a href="{{ route('reports.ap_dp') }}" class="nav-link {{ request()->routeIs('reports.ap_dp') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-file-invoice text-danger"></i></span>
                        <span class="nav-text">{{ __('erp.ap_dp') }}</span>
                    </a>
                    <a href="{{ route('reports.ap_subledger') }}" class="nav-link {{ request()->routeIs('reports.ap_subledger') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-receipt text-danger"></i></span>
                        <span class="nav-text">{{ __('erp.ap_subledger') }}</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ================================================================
             SECTION: MANUFAKTUR (Anthrilo Integration)
        ================================================================ --}}
        <div class="nav-section">
            <a href="#sectionManufaktur" data-bs-toggle="collapse" class="section-toggle {{ request()->routeIs('mfg.*') ? 'active' : '' }}" aria-expanded="{{ request()->routeIs('mfg.*') ? 'true' : 'false' }}">
                <span class="section-icon"><i class="fa-solid fa-industry text-primary"></i></span>
                <span class="section-text">{{ __('erp.mfg_module') }}</span>
                <i class="fa-solid fa-chevron-down section-chevron"></i>
            </a>
            <div class="collapse section-collapse {{ request()->routeIs('mfg.*') ? 'show' : '' }}" id="sectionManufaktur">
                <a href="{{ route('mfg.work-orders.index') }}" class="nav-link {{ request()->routeIs('mfg.work-orders.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-clipboard-list text-primary"></i></span>
                    <span class="nav-text">{{ __('erp.spk_work_order_label') }}</span>
                </a>
                <a href="{{ route('mfg.material-receipts.index') }}" class="nav-link {{ request()->routeIs('mfg.material-receipts.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-truck-loading text-warning"></i></span>
                    <span class="nav-text">{{ __('erp.bc_material_receipt_mrn') }}</span>
                </a>
                <a href="{{ route('mfg.reports.hpp') }}" class="nav-link {{ request()->routeIs('mfg.reports.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line text-success"></i></span>
                    <span class="nav-text">{{ __('erp.mfg_report_hpp') }}</span>
                </a>

                <a href="#menuMfgMaster" data-bs-toggle="collapse" class="nav-link {{ request()->routeIs('mfg.yarns.*') || request()->routeIs('mfg.fabrics.*') || request()->routeIs('mfg.suppliers.*') || request()->routeIs('mfg.processes.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-database text-secondary"></i></span>
                    <span class="nav-text">{{ __('erp.mfg_master_data') }}</span>
                    <i class="fa-solid fa-chevron-down nav-chevron"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('mfg.yarns.*') || request()->routeIs('mfg.fabrics.*') || request()->routeIs('mfg.suppliers.*') || request()->routeIs('mfg.processes.*') ? 'show' : '' }}" id="menuMfgMaster">
                    <a href="{{ route('mfg.yarns.index') }}" class="nav-link {{ request()->routeIs('mfg.yarns.*') ? 'active' : '' }}">
                        <span class="nav-text">{{ __('erp.master_yarn_menu') }}</span>
                    </a>
                    <a href="{{ route('mfg.fabrics.index') }}" class="nav-link {{ request()->routeIs('mfg.fabrics.*') ? 'active' : '' }}">
                        <span class="nav-text">{{ __('erp.master_fabric_menu') }}</span>
                    </a>
                    <a href="{{ route('mfg.suppliers.index') }}" class="nav-link {{ request()->routeIs('mfg.suppliers.*') ? 'active' : '' }}">
                        <span class="nav-text">{{ __('erp.mfg_master_supplier') }}</span>
                    </a>
                    <a href="{{ route('mfg.processes.index') }}" class="nav-link {{ request()->routeIs('mfg.processes.*') ? 'active' : '' }}">
                        <span class="nav-text">{{ __('erp.mfg_master_process') }}</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- ================================================================
             SECTION: AKUNTANSI
        ================================================================ --}}
        <div class="nav-section">
            <a href="#sectionAkuntansi" data-bs-toggle="collapse" class="section-toggle {{ request()->routeIs('account.*') || request()->routeIs('helper.*') || request()->routeIs('tax.*') || request()->routeIs('jurnal.*') || request()->routeIs('aset.*') || request()->routeIs('buku-besar.*') || request()->routeIs('laba-rugi.*') || request()->routeIs('neraca.*') || request()->routeIs('arus-kas.*') || request()->routeIs('reports.cogs') || request()->routeIs('reports.tags') ? 'active' : '' }}" aria-expanded="{{ request()->routeIs('account.*') || request()->routeIs('helper.*') || request()->routeIs('tax.*') || request()->routeIs('jurnal.*') || request()->routeIs('aset.*') || request()->routeIs('buku-besar.*') || request()->routeIs('laba-rugi.*') || request()->routeIs('neraca.*') || request()->routeIs('arus-kas.*') || request()->routeIs('reports.cogs') || request()->routeIs('reports.tags') ? 'true' : 'false' }}">
                <span class="section-icon"><i class="fa-solid fa-calculator text-primary"></i></span>
                <span class="section-text">{{ __('erp.accounting') }}</span>
                <i class="fa-solid fa-chevron-down section-chevron"></i>
            </a>
            <div class="collapse section-collapse {{ request()->routeIs('account.*') || request()->routeIs('helper.*') || request()->routeIs('tax.*') || request()->routeIs('jurnal.*') || request()->routeIs('aset.*') || request()->routeIs('buku-besar.*') || request()->routeIs('laba-rugi.*') || request()->routeIs('neraca.*') || request()->routeIs('arus-kas.*') || request()->routeIs('reports.cogs') || request()->routeIs('reports.tags') ? 'show' : '' }}" id="sectionAkuntansi">
                <div class="nav-sub-label">{{ __('erp.master_data') }}</div>
                <a href="{{ route('account.index') }}" class="nav-link {{ request()->routeIs('account.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-list-check"></i></span>
                    <span class="nav-text">{{ __('erp.coa') }}</span>
                </a>
                <a href="{{ route('helper.index') }}" class="nav-link {{ request()->routeIs('helper.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-tags"></i></span>
                    <span class="nav-text">{{ __('erp.helper_code') }}</span>
                </a>
                <a href="{{ route('tax.index') }}" class="nav-link {{ request()->routeIs('tax.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-percent"></i></span>
                    <span class="nav-text">{{ __('erp.tax_master') }}</span>
                </a>

                <hr class="nav-divider-inner">

                <div class="nav-sub-label">{{ __('erp.transaction') }}</div>
                <a href="{{ route('jurnal.index') }}" class="nav-link {{ request()->routeIs('jurnal.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                    <span class="nav-text">{{ __('erp.general_journal') }}</span>
                </a>

                <a href="#menuAset" data-bs-toggle="collapse" class="nav-link {{ request()->routeIs('aset.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                    <span class="nav-text">{{ __('erp.fixed_asset') }}</span>
                    <i class="fa-solid fa-chevron-down nav-chevron"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('aset.*') ? 'show' : '' }}" id="menuAset">
                    <a href="{{ route('aset.index') }}" class="nav-link {{ request()->routeIs('aset.index') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-file-import"></i></span>
                        <span class="nav-text">{{ __('erp.new_asset') }}</span>
                    </a>
                    <a href="{{ route('aset.list') }}" class="nav-link {{ request()->routeIs('aset.list') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-table-list"></i></span>
                        <span class="nav-text">{{ __('erp.depreciation_list') }}</span>
                    </a>
                </div>

                <hr class="nav-divider-inner">

                <div class="nav-sub-label">{{ __('erp.reports') }}</div>
                <a href="{{ route('buku-besar.index') }}" class="nav-link {{ request()->routeIs('buku-besar.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    <span class="nav-text">{{ __('erp.general_ledger') }}</span>
                </a>

                <a href="{{ route('reconciliation.index') }}" class="nav-link {{ request()->routeIs('reconciliation.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-arrows-left-right text-info"></i></span>
                    <span class="nav-text">{{ __('erp.jubelio_reconciliation_menu') }}</span>
                </a>

                <a href="#menuLabaRugi" data-bs-toggle="collapse" class="nav-link {{ request()->routeIs('laba-rugi.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span class="nav-text">{{ __('erp.profit_loss') }}</span>
                    <i class="fa-solid fa-chevron-down nav-chevron"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('laba-rugi.*') ? 'show' : '' }}" id="menuLabaRugi">
                    <a href="{{ route('laba-rugi.index', ['tab' => 'bulanan']) }}" class="nav-link {{ request()->routeIs('laba-rugi.index') && request('tab') != 'periode' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span><span class="nav-text">{{ __('erp.monthly_matrix') }}</span>
                    </a>
                    <a href="{{ route('laba-rugi.index', ['tab' => 'periode']) }}" class="nav-link {{ request('tab') == 'periode' && request()->routeIs('laba-rugi.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-calendar-week"></i></span><span class="nav-text">{{ __('erp.period_report') }}</span>
                    </a>
                </div>

                <a href="#menuNeraca" data-bs-toggle="collapse" class="nav-link {{ request()->routeIs('neraca.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-scale-balanced"></i></span>
                    <span class="nav-text">{{ __('erp.balance_sheet') }}</span>
                    <i class="fa-solid fa-chevron-down nav-chevron"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('neraca.*') ? 'show' : '' }}" id="menuNeraca">
                    <a href="{{ route('neraca.index', ['tab' => 'bulanan']) }}" class="nav-link {{ request()->routeIs('neraca.index') && request('tab') != 'periode' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span><span class="nav-text">{{ __('erp.monthly_matrix') }}</span>
                    </a>
                    <a href="{{ route('neraca.index', ['tab' => 'periode']) }}" class="nav-link {{ request('tab') == 'periode' && request()->routeIs('neraca.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-calendar-week"></i></span><span class="nav-text">{{ __('erp.period_report') }}</span>
                    </a>
                </div>

                <a href="#menuArusKas" data-bs-toggle="collapse" class="nav-link {{ request()->routeIs('arus-kas.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-water-ladder"></i></span>
                    <span class="nav-text">{{ __('erp.cash_flow') }}</span>
                    <i class="fa-solid fa-chevron-down nav-chevron"></i>
                </a>
                <div class="collapse nav-submenu {{ request()->routeIs('arus-kas.*') ? 'show' : '' }}" id="menuArusKas">
                    <a href="{{ route('arus-kas.index', ['tab' => 'direct']) }}" class="nav-link {{ request('tab', 'direct') == 'direct' && request()->routeIs('arus-kas.*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-money-bill-transfer"></i></span><span class="nav-text">{{ __('erp.direct_method') }}</span>
                    </a>
                    <a href="{{ route('arus-kas.index', ['tab' => 'indirect']) }}" class="nav-link {{ request('tab') == 'indirect' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fa-solid fa-calculator"></i></span><span class="nav-text">{{ __('erp.indirect_method') }}</span>
                    </a>
                </div>

                <a href="{{ route('reports.cogs') }}" class="nav-link {{ request()->routeIs('reports.cogs') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-boxes-stacked text-primary"></i></span><span class="nav-text">{{ __('erp.cogs_chronology') }}</span>
                </a>
                <a href="{{ route('reports.tags') }}" class="nav-link {{ request()->routeIs('reports.tags') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fa-solid fa-tags text-success"></i></span><span class="nav-text">{{ __('erp.tag_report') }}</span>
                </a>
            </div>
        </div>
    </div>
</aside>

{{-- ================================================================
     MAIN CONTENT
================================================================ --}}
<main class="main-content" id="mainContent">
    
    {{-- TOP HEADER (PROFILE & SETTINGS) --}}
    <header class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom d-print-none main-header-compact">
        <div class="d-flex align-items-center gap-2" style="font-size:0.85rem;">
            @yield('top_bar_left')
        </div>
        <div class="dropdown">
            <button class="btn border-0 shadow-sm dropdown-toggle d-flex align-items-center gap-1 bg-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 10px; padding: 4px 10px 4px 4px; font-size:0.82rem;">
                {{-- AMAN DARI ERROR NULL --}}
                <div class="bg-primary text-white d-flex justify-content-center align-items-center fw-bold avatar-circle" style="width: 30px; height: 30px; border-radius: 50%; font-size: 0.9rem;">
                    {{ strtoupper(substr(auth()->check() ? auth()->user()->name : 'A', 0, 1)) }}
                </div>
                <div class="text-start d-none d-sm-block me-1">
                    <div class="fw-bold text-dark user-name" style="font-size: 0.8rem; line-height: 1.1;">{{ auth()->check() ? auth()->user()->name : 'Administrator' }}</div>
                    <small class="text-muted user-role" style="font-size: 0.65rem;">{{ auth()->check() ? auth()->user()->role : 'System' }}</small>
                </div>
            </button>
            
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" style="width: 250px; border-radius: 12px;">
                <li class="px-3 py-2">
                    <small class="text-muted d-block mb-2 fw-bold"><i class="fa-solid fa-language me-2"></i>{{ __('erp.select_language') ?? 'Pilih Bahasa' }}</small>
                    <div class="d-flex gap-2">
                        <a href="{{ route('lang.switch', 'id') }}" class="btn btn-sm w-100 fw-bold {{ app()->getLocale() == 'id' ? 'btn-primary' : 'btn-light text-dark' }}">🇮🇩 ID</a>
                        <a href="{{ route('lang.switch', 'en') }}" class="btn btn-sm w-100 fw-bold {{ app()->getLocale() == 'en' ? 'btn-primary' : 'btn-light text-dark' }}">🇬🇧 EN</a>
                        <a href="{{ route('lang.switch', 'zh_CN') }}" class="btn btn-sm w-100 fw-bold {{ app()->getLocale() == 'zh_CN' ? 'btn-primary' : 'btn-light text-dark' }}">🇨🇳 中文</a>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item d-flex align-items-center gap-3 py-2 fw-medium" id="theme-toggle">
                        <i class="fa-solid fa-moon text-secondary" id="theme-icon" style="width: 16px; text-align: center;"></i> 
                        <span id="theme-text">{{ __('erp.dark_mode') ?? 'Mode Gelap' }}</span>
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-3 py-2 text-danger fw-bold">
                            <i class="fa-solid fa-right-from-bracket" style="width: 16px; text-align: center;"></i> 
                            {{ __('erp.logout') ?? 'Keluar' }}
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    @yield('content')
</main>

{{-- BOTTOM NAVIGATION (HP only) --}}
<nav class="bottom-nav" id="bottomNav">
    <div class="bottom-nav-items">
        <a href="{{ route('dashboard') }}" class="bnav-item {{ request()->routeIs('dashboard*') ? 'active' : '' }}"><i class="fa-solid fa-gauge-high"></i><span>{{ __('erp.home') }}</span></a>
        <a href="{{ route('jurnal.index') }}" class="bnav-item {{ request()->routeIs('jurnal.*') ? 'active' : '' }}"><i class="fa-solid fa-book"></i><span>{{ __('erp.journal') }}</span></a>
        <a href="{{ route('payment.index') }}" class="bnav-item {{ request()->routeIs('payment.*') ? 'active' : '' }}"><i class="fa-solid fa-money-check-dollar"></i><span>{{ __('erp.payment') }}</span></a>
        <a href="{{ route('laba-rugi.index') }}" class="bnav-item {{ request()->routeIs('laba-rugi.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-line"></i><span>{{ __('erp.report') }}</span></a>
        <a href="#" class="bnav-item" onclick="toggleSidebar(); return false;"><i class="fa-solid fa-grip"></i><span>{{ __('erp.all_menu') }}</span></a>
    </div>
</nav>

{{-- SCRIPTS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
{{-- MODAL GLOBAL HISTORY LOG (AJAX) --}}
<div class="modal fade" id="globalLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-light border-bottom-0">
                <h6 class="modal-title fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i> Jejak Aktivitas (<span id="logKeywordTitle" class="text-secondary"></span>)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('erp.close_btn') }}"></button>
            </div>
            <div class="modal-body p-4" id="globalLogContent">
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi global yang bisa dipanggil dari seluruh tombol di semua menu
function showEntityLog(keyword) {
    document.getElementById('logKeywordTitle').innerText = keyword;
    document.getElementById('globalLogContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="small mt-2 text-muted fw-bold">{{ __('erp.loading_tx_history') }}</div></div>';
    
    var logModal = new bootstrap.Modal(document.getElementById('globalLogModal'));
    logModal.show();

    fetch("{{ route('logs.entity') }}?keyword=" + encodeURIComponent(keyword))
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('globalLogContent').innerHTML = data.html;
            } else {
                document.getElementById('globalLogContent').innerHTML = '<div class="alert alert-danger fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i> ' + data.message + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('globalLogContent').innerHTML = '<div class="alert alert-danger fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i> {{ __('erp.failed_fetch_check_connection') }}</div>';
        });
}
</script>

<script>
(function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var isOpen = false;

    function openSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        isOpen = true;
    }

    function closeSidebar() {
        if (!sidebar || !overlay) return;
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        isOpen = false;
    }

    function toggleSidebar() { isOpen ? closeSidebar() : openSidebar(); }

    window.openSidebar = openSidebar;
    window.closeSidebar = closeSidebar;
    window.toggleSidebar = toggleSidebar;

    window.addEventListener('resize', function () { if (window.innerWidth > 992) closeSidebar(); });

    var touchStartX = 0;
    document.addEventListener('touchstart', function (e) { touchStartX = e.touches[0].clientX; }, { passive: true });
    document.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - touchStartX;
        if (touchStartX < 30 && dx > 60) openSidebar();
        if (dx < -60 && isOpen) closeSidebar();
    }, { passive: true });

    // Section collapse persistence (save/restore state to localStorage)
    var SECTION_STORAGE_KEY = 'sidebar_sections';

    function getSectionStates() {
        try { return JSON.parse(localStorage.getItem(SECTION_STORAGE_KEY)) || {}; } catch(e) { return {}; }
    }

    function saveSectionState(id, expanded) {
        var states = getSectionStates();
        states[id] = expanded;
        localStorage.setItem(SECTION_STORAGE_KEY, JSON.stringify(states));
    }

    // Restore section states from localStorage
    (function restoreSections() {
        var states = getSectionStates();
        document.querySelectorAll('.section-toggle[data-bs-toggle="collapse"]').forEach(function (el) {
            var targetId = el.getAttribute('href');
            if (!targetId) return;
            var id = targetId.replace('#', '');
            var target = document.querySelector(targetId);
            if (!target) return;
            // If saved state exists, apply it (overrides server-side show)
            if (states[id] !== undefined) {
                if (states[id]) {
                    target.classList.add('show');
                    el.setAttribute('aria-expanded', 'true');
                } else {
                    target.classList.remove('show');
                    el.setAttribute('aria-expanded', 'false');
                }
            }
        });
    })();

    // Listen for collapse events and save state
    document.querySelectorAll('.section-toggle[data-bs-toggle="collapse"]').forEach(function (el) {
        var targetId = el.getAttribute('href');
        var target = targetId ? document.querySelector(targetId) : null;
        if (!target) return;
        var id = targetId.replace('#', '');
        target.addEventListener('show.bs.collapse', function () {
            el.setAttribute('aria-expanded', 'true');
            saveSectionState(id, true);
        });
        target.addEventListener('hide.bs.collapse', function () {
            el.setAttribute('aria-expanded', 'false');
            saveSectionState(id, false);
        });
    });

    // Also handle existing sub-menu collapses (AR/AP, Aset, LabaRugi, Neraca, ArusKas)
    document.querySelectorAll('.nav-link[data-bs-toggle="collapse"]').forEach(function (el) {
        var targetId = el.getAttribute('href');
        var target = targetId ? document.querySelector(targetId) : null;
        if (!target) return;
        target.addEventListener('show.bs.collapse', function () { el.setAttribute('aria-expanded', 'true'); });
        target.addEventListener('hide.bs.collapse', function () { el.setAttribute('aria-expanded', 'false'); });
    });
})();

document.addEventListener('DOMContentLoaded', function() {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    const htmlElement = document.documentElement;

    let currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') {
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
        if(themeText) themeText.innerText = 'Mode Terang';
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            let activeTheme = htmlElement.getAttribute('data-bs-theme');
            let newTheme = activeTheme === 'dark' ? 'light' : 'dark';
            htmlElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            if (newTheme === 'dark') {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
                if(themeText) themeText.innerText = 'Mode Terang';
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
                if(themeText) themeText.innerText = 'Mode Gelap';
            }
        });
    }
});
</script>
@stack('scripts')
</body>
</html>
