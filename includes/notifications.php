<?php
declare(strict_types=1);

/**
 * In-app notification system. Single shared implementation — both index.php
 * (self-contained bootstrap) and config.php (used by mobile_api.php,
 * api_v2.php, admin tools) require_once this file, so there is exactly one
 * copy of the table schema and the write path.
 */

/**
 * Three-way notification categorization used by index.php's Transfers/P2P
 * pages and the dedicated Security page. Kept here (not inline in
 * index.php) so any future consumer (mobile_api.php, admin tools) sees the
 * same buckets.
 */
const NOTIF_TRANSFER_TYPES = ['transfer_sent', 'transfer_received', 'api_escrow_withdraw', 'api_deposit_code', 'api_transfer_completed', 'api_payout_received'];
const NOTIF_P2P_TYPES = ['p2p_new_order', 'p2p_payment_confirmed', 'p2p_completed', 'p2p_message', 'p2p_dispute_opened', 'p2p_admin_message'];
const NOTIF_SECURITY_TYPES = ['login', 'register', 'profile', 'api_key_generated'];

/**
 * Types that light up the header bell's red unread count. Login/register/
 * profile are informational account history, not urgent — they never
 * trigger the badge, even though they still show up in the Security feed.
 */
const NOTIF_BADGE_TYPES = [
    'transfer_sent', 'transfer_received', 'api_escrow_withdraw', 'api_deposit_code', 'api_transfer_completed', 'api_payout_received',
    'p2p_new_order', 'p2p_payment_confirmed', 'p2p_completed', 'p2p_message', 'p2p_dispute_opened', 'p2p_admin_message',
    'api_key_generated',
];

function notificationsEnsureTable(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(190) NOT NULL,
            body TEXT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'system',
            related_avatar LONGTEXT NULL,
            meta TEXT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_notifications_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function createUserNotification(PDO $pdo, int $userId, string $title, string $body, string $type = 'system', ?string $relatedAvatar = null, ?array $meta = null): void {
    if ($userId <= 0 || $title === '') {
        return;
    }
    notificationsEnsureTable($pdo);
    $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, title, body, type, related_avatar, meta) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $body, $type, $relatedAvatar, $metaJson]);
}

function fetchUserNotifications(PDO $pdo, int $userId, int $limit = 60): array {
    notificationsEnsureTable($pdo);
    $stmt = $pdo->prepare("SELECT id, title, body, type, related_avatar, meta, is_read, created_at, UNIX_TIMESTAMP(created_at) AS created_ts FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function countUnreadNotifications(PDO $pdo, int $userId): int {
    notificationsEnsureTable($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** Unread count for the header bell — only badge-eligible types (see NOTIF_BADGE_TYPES), so a new login never lights it up. */
function countUnreadBadgeNotifications(PDO $pdo, int $userId): int {
    notificationsEnsureTable($pdo);
    $placeholders = implode(',', array_fill(0, count(NOTIF_BADGE_TYPES), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0 AND type IN ($placeholders)");
    $stmt->execute([$userId, ...NOTIF_BADGE_TYPES]);
    return (int) $stmt->fetchColumn();
}

function markAllNotificationsRead(PDO $pdo, int $userId): void {
    notificationsEnsureTable($pdo);
    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

/** Marks read only notifications whose type is in $types — used to scope "mark read" to one section (Transfers+P2P, or Security) without touching the others. */
function markNotificationsReadByTypes(PDO $pdo, int $userId, array $types): void {
    if (empty($types)) {
        return;
    }
    notificationsEnsureTable($pdo);
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND type IN ($placeholders)");
    $stmt->execute([$userId, ...$types]);
}
