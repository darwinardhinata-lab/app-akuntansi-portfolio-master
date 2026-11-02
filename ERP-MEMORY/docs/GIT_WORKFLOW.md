# GIT_WORKFLOW.md — Alur Git & Konvensi Commit

> Last Updated: 2026-11-02  
> Branch aktif: `feat/marvel-dard-integration`  
> Remote: `origin` → `https://github.com/darwinardhinata-lab/app-akuntansi-portfolio-master.git`

---

## STRATEGI BRANCHING

| Branch | Tujuan | Aturan |
|---|---|---|
| `main` | Production-ready | JANGAN merge tanpa approval manusia |
| `feat/*` | Fitur baru | Buat dari `main` atau `staging`, merge via PR |
| `fix/*` | Perbaikan bug | Buat dari branch terkait, merge setelah test |
| `staging/*` | Staging/pre-release | Untuk integrasi sebelum ke `main` |
| `docs/*` | Dokumentasi | Bisa langsung commit ke branch fitur |

---

## KONVENSI COMMIT (Conventional Commits)

Format: `<tipe>(<scope>): <deskripsi>`

### Tipe
| Tipe | Kegunaan | Contoh |
|---|---|---|
| `feat` | Fitur baru | `feat(payment-plan): add AP allocation` |
| `fix` | Perbaikan bug | `fix(payment-plan): resolve allocation syntax error` |
| `docs` | Dokumentasi | `docs: add ERP-MEMORY architecture` |
| `refactor` | Refaktor tanpa perubahan perilaku | `refactor(ledger): simplify posting intent` |
| `test` | Menambah/memperbaiki test | `test(ap): add allocation edge case` |
| `chore` | Tugas maintenance | `chore(deps): update composer packages` |

### Scope (contoh)
`payment-plan`, `purchasing`, `inventory`, `manufacturing`, `ledger`, `filament-ui`, `api`, `db`, `docs`

---

## ALUR KERJA (WORKFLOW)

```powershell
# 1. Pastikan branch terkini
git checkout feat/marvel-dard-integration
git pull origin feat/marvel-dard-integration

# 2. Buat branch fitur dari branch aktif (jika perlu fitur isolasi)
git checkout -b feat/ap-residual

# 3. Lakukan perubahan, baca dokumentasi ERP-MEMORY terlebih dahulu

# 4. Lint file PHP yang diubah
php -l <file>

# 5. Stage perubahan yang relevan (jangan stage file debug/build)
git add <file_direlevansi>

# 6. Commit
git commit -m "feat(ap): implement residual allocation service"

# 7. Jalankan test terkait sebelum push
php vendor/phpunit/phpunit/phpunit tests/Feature/ApAllocation/ --no-progress

# 8. Push ke remote
git push origin feat/ap-residual

# 9. Buat Pull Request dan minta review manusia sebelum merge
```

---

## ATURAN PENTING

| Aturan | Detail |
|---|---|
| **Jangan commit file debug** | `*_fixed.php`, `inspect_service.php`, `PROJECT_MEMORY.md` di root |
| **Jangan commit build output** | `public/build/`, `public/css/`, `public/fonts/`, `public/js/` |
| **Jangan commit `.env`** | Sensitif, ada di `.gitignore` |
| **Jangan commit `vendor/` dan `node_modules/`** | Ada di `.gitignore` |
| **Setiap commit finansial harus dicatat** | Di `docs/CHANGE_DECISIONS.md` jika mengubah arsitektur |
| **Sync sebelum coding** | `git pull` sebelum mulai bekerja |
| **Test sebelum push** | Jalankan test suite terkait |

---

## PUSH KE REMOTE

```powershell
git push origin <nama_branch>
```

Pastikan branch lokal sudah di-`pull` terbaru sebelum push untuk menghindari conflict.

_Last Updated: 2026-11-02_