<?php

namespace App\Support;

/**
 * Manufacturing Label Helper
 *
 * Terjemahkan enum/status/proses manufaktur ke label UI multi-bahasa.
 * DB values tetap baku (OPEN, COMPLETED, KNITTING, dll) — hanya UI yang diterjemahkan.
 *
 * @see RULES.md §10 — Standar Multi-Bahasa (i18n)
 * @see MANUFACTURING_INTEGRATION.md §10 — Multi-Bahasa untuk Modul Manufaktur
 */
class ManufacturingLabel
{
    /**
     * Terjemahkan status SPK ke label UI multi-bahasa.
     * DB: 'COMPLETED' → ID: 'Selesai' / EN: 'Completed' / ZH: '已完成'
     */
    public static function workOrderStatus(string $status): string
    {
        return match(strtoupper($status)) {
            'OPEN' => __('erp.mfg_status_open'),
            'IN_PROGRESS' => __('erp.mfg_status_in_progress'),
            'COMPLETED' => __('erp.mfg_status_completed'),
            'VOIDED' => __('erp.mfg_status_voided'),
            default => $status, // fallback aman
        };
    }

    /**
     * Terjemahkan tipe proses manufaktur ke label UI multi-bahasa.
     * DB: 'KNITTING' → ID: 'Knitting (Rajut)' / EN: 'Knitting' / ZH: '针织'
     */
    public static function processType(string $type): string
    {
        return match(strtoupper($type)) {
            'KNITTING' => __('erp.mfg_process_knitting'),
            'DYEING' => __('erp.mfg_process_dyeing'),
            'PRINTING' => __('erp.mfg_process_printing'),
            'FINISHING' => __('erp.mfg_process_finishing'),
            'CUTTING' => __('erp.mfg_process_cutting'),
            'STITCHING' => __('erp.mfg_process_stitching'),
            'OTHER' => __('erp.mfg_process_other'),
            default => $type,
        };
    }

    /**
     * Terjemahkan state kain (greige/finished) ke label UI multi-bahasa.
     * DB: 'GREY' → ID: 'Kain Grey (Mentah)' / EN: 'Grey Fabric' / ZH: '毛坯布'
     */
    public static function fabricState(string $state): string
    {
        return match(strtoupper($state)) {
            'GREY' => __('erp.mfg_fabric_grey'),
            'FINISHED' => __('erp.mfg_fabric_finished'),
            default => $state,
        };
    }

    /**
     * Terjemahkan tahap finishing ke label UI multi-bahasa.
     * DB: 'WASHING' → ID: 'Washing (Cuci)' / EN: 'Washing' / ZH: '水洗'
     */
    public static function finishingStage(string $stage): string
    {
        return match(strtoupper($stage)) {
            'WASHING' => __('erp.mfg_stage_washing'),
            'IRONING' => __('erp.mfg_stage_ironing'),
            'QC' => __('erp.mfg_stage_qc'),
            'PACKING' => __('erp.mfg_stage_packing'),
            'OTHER' => __('erp.mfg_stage_other'),
            default => $stage,
        };
    }

    /**
     * Terjemahkan tipe item material (YARN/FABRIC) ke label UI multi-bahasa.
     * DB: 'YARN' → ID: 'Benang' / EN: 'Yarn' / ZH: '纱线'
     */
    public static function materialType(string $type): string
    {
        return match(strtoupper($type)) {
            'YARN' => __('erp.mfg_material_yarn'),
            'FABRIC' => __('erp.mfg_material_fabric'),
            default => $type,
        };
    }

    /**
     * Terjemahkan tipe ledger (IN/OUT) ke label UI multi-bahasa.
     * DB: 'IN' → ID: 'Masuk' / EN: 'In' / ZH: '入库'
     */
    public static function ledgerType(string $type): string
    {
        return match(strtoupper($type)) {
            'IN' => __('erp.mfg_ledger_in'),
            'OUT' => __('erp.mfg_ledger_out'),
            default => $type,
        };
    }
}
