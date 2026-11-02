# DIRECTORY_STRUCTURE.md — Layout Project ERP Marvel-Dard

> Last Updated: 2026-11-02  
> Root: `D:\xampp\htdocs\app-akuntansi-portfolio-master`

---

## STRUKTUR TINGGI (ROOT)

```
app-akuntansi-portfolio-master/
├── app/                         # Logika aplikasi Laravel
│   ├── Models/                  # Eloquent models
│   ├── Modules/                 # Modul domain (PaymentPlan, Purchasing, dll)
│   │   ├── PaymentPlan/
│   │   │   ├── Services/        # ApAllocationService, PaymentPlanService
│   │   │   ├── Domain/          # RuleViolation, state machine
│   │   │   └── ...
│   │   ├── Purchasing/
│   │   ├── Manufacturing/
│   │   └── ...
│   ├── Support/
│   │   └── PostingService.php   # Satu ledger, semua posting via sini
│   ├── Filament/                # Panel admin Filament
│   ├── Providers/
│   └── ...
├── database/
│   ├── migrations/              # Semua migrasi (snake_case, timestamp)
│   ├── factories/
│   └── seeders/
├── lang/
│   ├── en/
│   ├── id/
│   └── zh_CN/
│       └── ap_payment.php
├── public/
│   ├── build/                   # Build output (Vite) — jangan di-commit
│   ├── css/
│   ├── fonts/
│   └── js/
├── resources/
│   ├── views/                   # Blade templates
│   ├── lang/
│   └── ...
├── routes/
│   ├── web.php
│   └── api.php
├── tests/
│   ├── Feature/                 # Feature tests (ApAllocation, dll)
│   ├── Integration/             # MySQL integration tests
│   └── Unit/
├── ERP-MEMORY/                  # ← Source of truth untuk AI (version-controlled)
│   ├── AGENTS.md
│   ├── PROJECT_STATE.md
│   ├── INVARIANTS.md
│   └── docs/
│       ├── ARCHITECTURE.md
│       ├── DATABASE_RELATIONSHIPS.md
│       ├── ACCOUNTING_RULES.md
│       ├── PAYMENT_PLAN_V33.md
│       ├── PURCHASING_AP_RULES.md
│       ├── INVENTORY_RULES.md
│       ├── MANUFACTURING_RULES.md
│       ├── FILAMENT_UI_RULES.md
│       ├── TESTING_PROTOCOL.md
│       ├── BUG_LOG.md
│       ├── GIT_WORKFLOW.md
│       ├── SETUP_INSTALLATION.md
│       ├── API_CONTRACTS.md
│       ├── DIRECTORY_STRUCTURE.md
│       ├── CHANGE_DECISIONS.md
│       ├── IMPLEMENTATION_ROADMAP.md
│       ├── MENU_MAP.md
│       ├── MASTER_DATA_CROSSWALK.md
│       ├── FILAMENT_LIVEWIRE_ADOPTION.md
│       ├── CUTOVER_ROLLBACK.md
│       ├── TEST_EVIDENCE.md
│       ├── SALES_AR_RULES.md
│       └── EXIM_CUSTOMS_RULES.md
├── .gitignore
├── composer.json
├── artisan
└── ...
```

---

## FILE YANG DI-GITIGNORE (JANGAN DI-COMMIT)

| Path | Alasan |
|---|---|
| `vendor/` | Dependency PHP (Composer) |
| `node_modules/` | Dependency JS (npm/yarn) |
| `.env` | Environment variables lokal (sensitif) |
| `public/storage` | Symlink storage |
| `public/hot` | Vite HMR |
| `storage/framework/*` | Cache, session, view, testing data |
| `bootstrap/cache/*` | Cache bootstrap |
| `composer.lock` | Dihindari per kebijakan repo |
| `*.log`, `*.sqlite` | Log dan database lokal |
| `public/build/`, `public/css/`, `public/fonts/`, `public/js/` | Build output Vite |

---

## FILE TEMPORARY (BELUM DI-COMMIT, PERLU DIBERSIHKAN)

| File | Status | Catatan |
|---|---|---|
| `ApAllocationService_fixed.php` | ?? untracked | Debug script, hapus atau pindah ke arsip |
| `inspect_service.php` | ?? untracked | Debug script, hapus atau pindah ke arsip |
| `PROJECT_MEMORY.md` | ?? untracked | Template generik, pertimbangkan hapus atau archive |
| `ERP-MEMORY/` | ✅ committed | Source of truth AI |

_Last Updated: 2026-11-02_