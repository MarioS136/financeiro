<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * Registra uma operação importante no sistema.
 */
function audit_log(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?array $details = null
): void {

    $userId = !empty($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if ($userAgent !== null) {
        $userAgent = mb_substr($userAgent, 0, 500);
    }

    $detailsJson = $details !== null
        ? json_encode(
            $details,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        )
        : null;

    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs
            (
                user_id,
                action,
                entity_type,
                entity_id,
                ip_address,
                user_agent,
                details
            )
         VALUES
            (
                :user_id,
                :action,
                :entity_type,
                :entity_id,
                :ip_address,
                :user_agent,
                :details
            )'
    );

    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'details' => $detailsJson
    ]);
}
