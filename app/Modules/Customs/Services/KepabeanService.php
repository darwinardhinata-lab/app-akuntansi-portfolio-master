<?php

namespace App\Modules\Customs\Services;

use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Models\CustomsStatusHistory;
use App\Support\DocumentSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KepabeanService
{
    public const TYPES = ['BC 4.0', 'BC 2.3', 'BC 2.5', 'BC 2.6.1', 'BC 2.6.2', 'BC 2.7', 'BC 3.0', 'BC 4.1', 'PIB', 'PEB'];

    public function dashboard(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $base = CustomsDocument::where('status', '!=', 'VOIDED');
        $current = (clone $base)->whereBetween('created_at', [$start, $end]);
        $previous = (clone $base)->whereBetween('created_at', [$start->copy()->subMonth(), $start->copy()->subSecond()])->count();
        $count = (clone $current)->count();
        $daily = (clone $current)->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $trend = [];
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $start->copy()->day($day)->toDateString();
            $trend[$date] = (int) ($daily[$date] ?? 0);
        }
        $types = (clone $current)->selectRaw('document_type, COUNT(*) as total')->groupBy('document_type')->pluck('total', 'document_type');
        return ['month' => $start, 'count' => $count, 'previous' => $previous,
            'change' => $previous > 0 ? round(($count - $previous) / $previous * 100, 1) : null,
            'submitted' => (clone $base)->whereBetween('submitted_at', [$start, $end])->count(),
            'drafts' => (clone $current)->where('status', 'DRAFT')->count(),
            'total' => (clone $base)->count(), 'trend' => $trend, 'types' => $types,
            'latest' => (clone $base)->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()];
    }

    public function save(array $data, ?CustomsDocument $document, int $actor): CustomsDocument
    {
        return DB::transaction(function () use ($data, $document, $actor) {
            if ($document) {
                $document = CustomsDocument::lockForUpdate()->findOrFail($document->id);
                if ($document->status !== 'DRAFT') {
                    throw new \RuntimeException(__('kepabean.draft_only'));
                }
            } else {
                $document = new CustomsDocument([
                    'internal_number' => DocumentSequence::generateSecure('cst_customs_documents', 'internal_number', 'CST/'.now()->format('Y/m').'/', 5),
                    'source_type' => 'manual:'.Str::uuid(), 'source_id' => 0, 'status' => 'DRAFT',
                    'environment' => config('customs.default_environment', 'sandbox'), 'created_by' => $actor,
                ]);
            }
            $details = $data['details'];
            unset($data['details']);
            $document->fill($data);
            $document->total_value = collect($details)->sum('nilai');
            $document->updated_by = $actor;
            $new = ! $document->exists;
            $document->save();
            $document->details()->delete();
            $document->details()->createMany($details);
            CustomsStatusHistory::create(['customs_document_id' => $document->id, 'status' => 'DRAFT',
                'note' => ($new ? 'Draft dibuat' : 'Draft diperbarui').' oleh user #'.$actor, 'changed_at' => now()]);
            return $document;
        });
    }

    public function archive(CustomsDocument $document, int $actor): void
    {
        DB::transaction(function () use ($document, $actor) {
            $document = CustomsDocument::lockForUpdate()->findOrFail($document->id);
            if ($document->status !== 'DRAFT') {
                throw new \RuntimeException(__('kepabean.draft_only'));
            }
            $document->update(['status' => 'VOIDED', 'updated_by' => $actor]);
            CustomsStatusHistory::create(['customs_document_id' => $document->id, 'status' => 'VOIDED',
                'note' => 'Draft diarsipkan oleh user #'.$actor, 'changed_at' => now()]);
        });
    }
}