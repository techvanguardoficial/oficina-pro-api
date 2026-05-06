<?php

namespace App\Services;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log a model creation
     */
    public static function logCreated(Model $model): void
    {
        self::createAudit($model, 'created', null, $model->getAttributes());
    }

    /**
     * Log a model update
     */
    public static function logUpdated(Model $model): void
    {
        $oldValues = array_intersect_key(
            $model->getOriginal(),
            array_flip($model->getDirty())
        );
        $newValues = array_intersect_key(
            $model->getAttributes(),
            array_flip($model->getDirty())
        );

        self::createAudit($model, 'updated', $oldValues, $newValues);
    }

    /**
     * Log a model deletion
     */
    public static function logDeleted(Model $model): void
    {
        self::createAudit($model, 'deleted', $model->getAttributes(), null);
    }

    /**
     * Log a model restoration
     */
    public static function logRestored(Model $model): void
    {
        self::createAudit($model, 'restored', null, $model->getAttributes());
    }

    /**
     * Log a custom action
     */
    public static function logAction(
        Model $model,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        self::createAudit($model, $action, $oldValues, $newValues);
    }

    /**
     * Create an audit record
     */
    protected static function createAudit(
        Model $model,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $user = Auth::user();
            $companyId = $user?->company_id;

            // Skip audit if we can't determine company context
            if (!$companyId) {
                return;
            }

            Audit::create([
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            // Don't let audit logging errors crash the application
            \Log::warning('Failed to create audit log', [
                'model' => get_class($model),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get audit logs for a company
     */
    public static function getCompanyAudits(
        int $companyId,
        int $limit = 50,
        ?string $action = null
    ) {
        $query = Audit::forCompany($companyId)
            ->with(['user', 'company'])
            ->latest();

        if ($action) {
            $query->byAction($action);
        }

        return $query->paginate($limit);
    }

    /**
     * Get audit logs for a specific model
     */
    public static function getModelAudits(
        string $modelClass,
        int $modelId,
        int $limit = 20
    ) {
        return Audit::forModel($modelClass, $modelId)
            ->with(['user', 'company'])
            ->latest()
            ->paginate($limit);
    }

    /**
     * Get audit logs by user
     */
    public static function getUserAudits(
        int $userId,
        int $limit = 20
    ) {
        return Audit::byUser($userId)
            ->with(['company'])
            ->latest()
            ->paginate($limit);
    }
}
