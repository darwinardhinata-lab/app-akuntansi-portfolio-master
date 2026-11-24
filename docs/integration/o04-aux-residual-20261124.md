# O04 AUX — aturan residu valuasi

Nilai total diturunkan dari `IN.total_cost - OUT.total_cost` per material, di bawah lock master dalam transaksi posting. `running_value` historis bukan saldo pembuka karena dapat berasal dari qty × MAC yang dibulatkan. Saldo master tetap menyimpan kuantitas dan MAC dua desimal; nilai total ada pada kartu, bukan hasil perkalian keduanya. Riwayat yang tidak cocok dengan kuantitas master, saldo negatif, saldo bernilai saat qty nol, dan jenis mutasi yang tidak didukung ditolak. Tidak ada skema baru, backfill, atau perubahan transaksi historis.

Receipt menambah nilai aktual dan memperbarui MAC. Issue parsial mempertahankan tarif MAC berjalan; issue terakhir memakai sisa nilai penuh dan mengosongkan qty, MAC, serta nilai. Void membalik nominal asli dan memeriksa periode asal serta reversal. Void EXPENSE menolak bila ada mutasi lanjutan; jalur WIP tetap mendukung reversal append-only setelah issue lain sesuai regresi existing. Guard mutasi lanjutan pada void receipt tetap berlaku.

Jika nominal parsial melampaui nilai tercatat, posting ditolak tanpa efek. Contoh: 5 PCS × 0.00 + 5 PCS × 0.01 → nilai 0.05, MAC 0.01. Issue parsial 6 PCS meminta 0.06, sehingga ditolak; issue seluruh 10 PCS memakai 0.05. Cap atau perubahan tarif memerlukan keputusan terpisah.

## Rekonsiliasi historis read-only

Pada snapshot read-only yang disetujui, query berikut menyaring kandidat selisih kartu/master. Jangan gunakan hasilnya untuk UPDATE. SQL tidak membuktikan GL historis: telusuri receipt/issue/reversal dan jurnal sumbernya, kelompokkan receipt multi-item sebelum membandingkan GL, dan eskalasi histori hilang, opening tanpa bukti, atau perbedaan jurnal ke Accounting.

```sql
WITH ledger_totals AS (
  SELECT item_id, MAX(id) AS last_id,
         SUM(CASE type WHEN 'IN' THEN qty WHEN 'OUT' THEN -qty ELSE 0 END) AS qty,
         SUM(CASE type WHEN 'IN' THEN total_cost WHEN 'OUT' THEN -total_cost ELSE 0 END) AS value,
         SUM(CASE WHEN type NOT IN ('IN','OUT') THEN 1 ELSE 0 END) AS unsupported
  FROM mfg_material_ledgers WHERE item_type = 'AUXILIARY' GROUP BY item_id
)
SELECT m.id, m.material_code, m.stock_quantity, t.qty AS ledger_qty,
       l.running_value AS card_value, t.value AS movement_value, t.unsupported
FROM mfg_auxiliary_materials m
LEFT JOIN ledger_totals t ON t.item_id = m.id
LEFT JOIN mfg_material_ledgers l ON l.id = t.last_id
WHERE m.stock_quantity <> COALESCE(t.qty, 0) OR t.unsupported > 0
   OR t.value < 0 OR (t.qty = 0 AND t.value <> 0) OR l.running_value <> t.value;
```

Tidak dijalankan pada DB utama atau staging. Bukti asli tetap di `D:/ERP-Recovery/finance-uat-local-895d410/o04-mixed-cost-final/`; RED patch `o04-residual-red/` menunjukkan dua kegagalan terkait 400.68 vs 400.66, runner exit 1 dan datadir dibersihkan.