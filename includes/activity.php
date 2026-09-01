<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function logActivity(string $module, string $action, ?string $recordReference = null, string $status = 'done', ?int $userId = null, ?string $role = null): void
{
    try {
        $statement = database()->prepare('INSERT INTO activity_logs (user_id, role, module, action, record_reference, status) VALUES (:user_id, :role, :module, :action, :reference, :status)');
        $statement->execute([
            'user_id' => $userId ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null),
            'role' => $role ?? (string) ($_SESSION['role'] ?? 'system'),
            'module' => mb_substr($module, 0, 80),
            'action' => mb_substr($action, 0, 500),
            'reference' => $recordReference !== null ? mb_substr($recordReference, 0, 100) : null,
            'status' => mb_substr($status, 0, 30),
        ]);
    } catch (Throwable $exception) {
        error_log('Unable to record activity: ' . $exception->getMessage());
    }
}

function recentActivities(?int $userId = null, int $limit = 8): array
{
    try {
        $limit = max(1, min($limit, 50));
        $sql = "SELECT a.created_at, a.action, a.status, a.module, a.record_reference, COALESCE(u.full_name, 'System') actor_name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id";
        $parameters = [];
        if ($userId !== null) { 
            $sql .= ' WHERE a.user_id=:user_id'; 
            $parameters['user_id'] = $userId; 
            }
        $sql .= " ORDER BY a.id DESC LIMIT $limit";
        $statement = database()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        error_log('Unable to load activity: ' . $exception->getMessage());
        return [];
    }
}
