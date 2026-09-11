# Deliverable — Localization Audit & Idempotency Fix (11 Sep 2026)

## Isi folder

```
docs/deliverables/2026-09-11-localization-audit/
├── README.md                        (file ini)
├── LAPORAN-ANALISA.md               laporan audit lengkap (lokalisasi + bug)
├── AGENT-PROMPT-localization.md     prompt kerja untuk agent remediasi i18n
└── fixes/
    ├── idempotency-guard-fixes.patch          diff siap `git apply`
    └── patched-files-fallback/                fallback kalau patch gagal apply
        └── app/Modules/Manufacturing/Services/
            ├── KnitOrderService.php
            ├── ProcessingOrderService.php
            └── BarcodeLabelService.php
```

## Cara pakai

### 1. Terapkan bug fix (idempotency guard)
Dari **root repo**:
```bash
git apply --check docs/deliverables/2026-09-11-localization-audit/fixes/idempotency-guard-fixes.patch
git apply docs/deliverables/2026-09-11-localization-audit/fixes/idempotency-guard-fixes.patch
```
Kalau gagal apply (repo sudah berubah dari snapshot yang dianalisa), copy manual
3 file dari `fixes/patched-files-fallback/` ke lokasi aslinya, lalu commit terpisah:
```
fix(manufacturing): guard status sebelum posting jurnal di receiveGreyFabric & receiveFabric
```

### 2. Tindak lanjuti temuan lokalisasi
Baca `LAPORAN-ANALISA.md` bagian 1 dulu — **butuh konfirmasi Anda** apakah
deliverable Phase 1/2 sebelumnya masih ada di suatu tempat sebelum agent mulai
kerja baru (lihat Langkah 0 di `AGENT-PROMPT-localization.md`).

Kalau sudah dikonfirmasi tidak ada sisa Phase 1/2 → serahkan
`AGENT-PROMPT-localization.md` ke coding agent (Antigravity/Claude Code) sebagai
instruksi kerja.

## Status setelah folder ini diproses
- [ ] Patch idempotency di-apply & di-commit
- [ ] Konfirmasi ada/tidaknya sisa deliverable Phase 1/2
- [ ] Langkah 1 (repo hygiene) agent prompt dijalankan
- [ ] Langkah 2–6 agent prompt dijalankan per batch

Setelah folder ini selesai ditindaklanjuti, boleh dihapus dari repo (isinya
dokumentasi kerja, bukan source code permanen).
