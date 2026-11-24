<?php

namespace App\Modules\Manufacturing\Support;

use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\MaterialLedger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use RuntimeException;

/** Call under the existing AUX master row lock, inside the posting transaction. */
final class AuxiliaryStockValue
{
    public static function decimal(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    public static function average(string $value, string $qty): string
    {
        return bccomp($qty, '0', 2) === 0 ? '0.00'
            : (string) BigDecimal::of($value)->dividedBy($qty, 2, RoundingMode::HalfUp);
    }

    public static function product(mixed $qty, mixed $rate): string
    {
        return (string) BigDecimal::of((string) $qty)->multipliedBy((string) $rate)->toScale(2, RoundingMode::HalfUp);
    }

    public static function balance(AuxiliaryMaterial $material): array
    {
        // Historical running_value could be qty × rounded MAC. Replay signed
        // posted movement amounts instead. Locking read avoids stale snapshots.
        $rows = MaterialLedger::where('item_type', 'AUXILIARY')->where('item_id', $material->id)
            ->orderBy('id')->lockForUpdate()->get(['id', 'evidence_number', 'type', 'qty', 'total_cost', 'running_qty']);
        $qty = $value = '0.00';
        foreach ($rows as $row) {
            if (! in_array($row->type, ['IN', 'OUT'], true) || bccomp((string) $row->qty, '0', 2) <= 0
                || bccomp((string) $row->total_cost, '0', 2) < 0) {
                throw new RuntimeException('Riwayat nilai bahan penolong tidak dapat direkonsiliasi.');
            }
            $deltaQty = (string) $row->qty;
            $deltaValue = (string) $row->total_cost;
            $qty = $row->type === 'IN' ? bcadd($qty, $deltaQty, 2) : bcsub($qty, $deltaQty, 2);
            $value = $row->type === 'IN' ? bcadd($value, $deltaValue, 2) : bcsub($value, $deltaValue, 2);
            if (bccomp($qty, (string) $row->running_qty, 2) !== 0 || bccomp($qty, '0', 2) < 0) {
                throw new RuntimeException('Riwayat kuantitas bahan penolong tidak lengkap.');
            }
        }
        if (bccomp($qty, (string) $material->stock_quantity, 2) !== 0) {
            throw new RuntimeException('Riwayat kuantitas bahan penolong tidak cocok dengan saldo master.');
        }
        self::assertBalance($qty, $value);

        return [$qty, $value, $rows->last()];
    }

    public static function assertBalance(string $qty, string $value): void
    {
        if (bccomp($qty, '0', 2) < 0 || bccomp($value, '0', 2) < 0
            || (bccomp($qty, '0', 2) === 0 && bccomp($value, '0', 2) !== 0)) {
            throw new RuntimeException('Saldo kuantitas atau nilai bahan penolong tidak valid; rekonsiliasi diperlukan.');
        }
    }

    public static function record(AuxiliaryMaterial $material, string $date, string $evidence, string $type, string $qty,
        string $unitCost, string $amount, string $stock, string $value, string $mac, string $description): void
    {
        self::assertBalance($stock, $value);
        $material->update(['stock_quantity' => $stock, 'average_cost' => $mac]);
        MaterialLedger::create(['transaction_date' => $date, 'evidence_number' => $evidence, 'item_type' => 'AUXILIARY',
            'item_id' => $material->id, 'type' => $type, 'qty' => $qty, 'unit_cost' => $unitCost,
            'total_cost' => $amount, 'running_qty' => $stock, 'running_value' => $value,
            'moving_average_cost' => $mac, 'description' => $description]);
    }
}