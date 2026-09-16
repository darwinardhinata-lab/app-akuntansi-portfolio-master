<?php

namespace App\Modules\Customs\Support;

use App\Modules\Customs\Models\CustomsDocumentLog;
use Illuminate\Support\Facades\Log;

/**
 * CustomsAuditLogger - Helper logging untuk modul Customs dengan redaksi data sensitif.
 * 
 * IMPORTANT: Kredensial dan signature TIDAK PERNAH ditulis ke log mentah.
 */
class CustomsAuditLogger
{
    /**
     * Field yang harus di-redact dari log.
     */
    private const SENSITIVE_FIELDS = [
        'signature',
        'signing',
        'api_key',
        'api_secret',
        'cert_password',
        'password',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'auth',
        'authorization',
    ];

    /**
     * Redact data sensitif dari array payload.
     */
    public static function redact(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            $keyLower = strtolower($key);

            foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
                if (str_contains($keyLower, $sensitiveField)) {
                    $value = '***REDACTED***';
                    break;
                }
            }
        });

        return $data;
    }

    /**
     * Redact data sensitif dari string JSON.
     */
    public static function redactJson(string $json): string
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return '[INVALID JSON]';
        }

        $redacted = self::redact($data);

        return json_encode($redacted, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Log request ke CEISA (outbound) - menyimpan ke CustomsDocumentLog.
     */
    public static function logRequest(
        int $documentId,
        string $eventType,
        array $payload,
        ?int $httpStatus = null,
        ?string $correlationId = null,
        ?int $actorUserId = null
    ): void {
        $logData = [
            'customs_document_id' => $documentId,
            'direction' => 'OUTBOUND',
            'event_type' => $eventType,
            'http_status' => $httpStatus,
            'request_payload' => json_encode(self::redact($payload)),
            'correlation_id' => $correlationId,
            'actor_user_id' => $actorUserId,
        ];

        CustomsDocumentLog::create($logData);

        Log::info("CEISA Request: {$eventType}", [
            'document_id' => $documentId,
            'correlation_id' => $correlationId,
            'payload' => self::redact($payload),
        ]);
    }

    /**
     * Log response dari CEISA (inbound) - menyimpan ke CustomsDocumentLog.
     */
    public static function logResponse(
        int $documentId,
        string $eventType,
        array $response,
        ?int $httpStatus = null,
        ?string $correlationId = null,
        ?int $latencyMs = null
    ): void {
        $logData = [
            'customs_document_id' => $documentId,
            'direction' => 'INBOUND',
            'event_type' => $eventType,
            'http_status' => $httpStatus,
            'response_payload' => json_encode(self::redact($response)),
            'correlation_id' => $correlationId,
            'latency_ms' => $latencyMs,
        ];

        CustomsDocumentLog::create($logData);

        $level = ($httpStatus ?? 0) >= 400 ? 'warning' : 'info';
        Log::$level("CEISA Response: {$eventType}", [
            'document_id' => $documentId,
            'correlation_id' => $correlationId,
            'http_status' => $httpStatus,
            'response' => self::redact($response),
        ]);
    }

    /**
     * Log error saat komunikasi dengan CEISA gagal.
     */
    public static function logError(
        int $documentId,
        string $errorMessage,
        ?string $correlationId = null,
        ?array $context = null
    ): void {
        $logData = [
            'document_id' => $documentId,
            'error' => $errorMessage,
            'correlation_id' => $correlationId,
            'context' => $context ?? [],
        ];

        Log::error('CEISA Error', $logData);
    }
}
