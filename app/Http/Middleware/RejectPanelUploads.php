<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class RejectPanelUploads
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('platform.filament_enabled', false), 404);

        $canReconcile = false;
        try {
            app(\App\Modules\PaymentPlan\Services\PrivateAttachmentStore::class)->reconciliationCompany('propose');
            $canReconcile = true;
        } catch (\Throwable) {
            $canReconcile = false;
        }

        $canMaterialPreview = app(\App\Modules\Manufacturing\Support\AuxiliaryMaterialPreviewUploadPolicy::class)->allows($request, $request->user());

        // Either reconciliation proposer or scoped material preview reader is allowed to stage temporary bytes.
        // The respective page action still checks ownership, schema, and lifecycle before any processing.
        abort_unless($canReconcile || $canMaterialPreview, 403);

        return $next($request);
    }
}