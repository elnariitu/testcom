<?php
declare(strict_types=1);

require_once __DIR__ . '/maten_rate.php';

/**
 * P2P MATEN-for-fiat marketplace. A seller lists MATEN for sale at a fixed
 * fiat price; the listed amount is escrowed (debited from the seller's own
 * balance_maten) the moment the listing goes live, so a buyer can always
 * trust that the MATEN really exists. Fiat itself never touches this
 * system — buyer and seller settle that leg off-platform (bank transfer,
 * any bank — payment_methods is free-text tags, not a fixed list) and use
 * the order's private chat + "I paid" / "I received it" confirmations to
 * release escrow. Completed trades also feed the site-wide MATEN/fiat rate
 * (see matenP2pRecentAveragePrice) and a 1-5 star seller rating.
 */

function matenP2pEnsureTables(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS p2p_listings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(40) NOT NULL,
            seller_id INT NOT NULL,
            currency VARCHAR(8) NOT NULL,
            price_per_maten DECIMAL(18,4) NOT NULL,
            total_amount DECIMAL(24,8) NOT NULL,
            remaining_amount DECIMAL(24,8) NOT NULL,
            min_amount DECIMAL(24,8) NOT NULL DEFAULT 0,
            payment_methods VARCHAR(500) NOT NULL DEFAULT '',
            status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_p2p_listings_public_id (public_id),
            INDEX idx_p2p_listings_status_currency (status, currency),
            INDEX idx_p2p_listings_seller (seller_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS p2p_trade_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(40) NOT NULL,
            listing_id INT UNSIGNED NOT NULL,
            seller_id INT NOT NULL,
            buyer_id INT NOT NULL,
            amount_maten DECIMAL(24,8) NOT NULL,
            price_per_maten DECIMAL(18,4) NOT NULL,
            currency VARCHAR(8) NOT NULL,
            total_fiat DECIMAL(18,2) NOT NULL,
            status ENUM('pending_payment','payment_confirmed','completed','cancelled','disputed') NOT NULL DEFAULT 'pending_payment',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            UNIQUE KEY uniq_p2p_trade_orders_public_id (public_id),
            INDEX idx_p2p_trade_orders_buyer (buyer_id),
            INDEX idx_p2p_trade_orders_seller (seller_id),
            INDEX idx_p2p_trade_orders_listing (listing_id),
            INDEX idx_p2p_trade_orders_status_completed (status, completed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS p2p_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_p2p_messages_order_created (order_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS p2p_seller_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            reviewer_id INT NOT NULL,
            reviewed_id INT NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            comment VARCHAR(500) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_p2p_seller_reviews_order (order_id),
            INDEX idx_p2p_seller_reviews_reviewee (reviewed_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $cardColStmt = $pdo->query("SHOW COLUMNS FROM p2p_listings LIKE 'card_number'");
    if (!$cardColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_listings ADD COLUMN card_number VARCHAR(20) NOT NULL DEFAULT '' AFTER seller_id");
    }

    $proofColStmt = $pdo->query("SHOW COLUMNS FROM p2p_trade_orders LIKE 'payment_proof'");
    if (!$proofColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_trade_orders ADD COLUMN payment_proof LONGTEXT NULL AFTER status");
    }

    $orderCardColStmt = $pdo->query("SHOW COLUMNS FROM p2p_trade_orders LIKE 'seller_card_number'");
    if (!$orderCardColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_trade_orders ADD COLUMN seller_card_number VARCHAR(20) NOT NULL DEFAULT '' AFTER currency");
    }

    $orderBankLabelColStmt = $pdo->query("SHOW COLUMNS FROM p2p_trade_orders LIKE 'seller_bank_label'");
    if (!$orderBankLabelColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_trade_orders ADD COLUMN seller_bank_label VARCHAR(60) NOT NULL DEFAULT '' AFTER seller_card_number");
    }
    $orderCardholderColStmt = $pdo->query("SHOW COLUMNS FROM p2p_trade_orders LIKE 'seller_cardholder_name'");
    if (!$orderCardholderColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_trade_orders ADD COLUMN seller_cardholder_name VARCHAR(120) NOT NULL DEFAULT '' AFTER seller_bank_label");
    }

    $disputeByColStmt = $pdo->query("SHOW COLUMNS FROM p2p_trade_orders LIKE 'dispute_requested_by'");
    if (!$disputeByColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_trade_orders ADD COLUMN dispute_requested_by INT NULL AFTER status");
    }
    $disputeAtColStmt = $pdo->query("SHOW COLUMNS FROM p2p_trade_orders LIKE 'dispute_requested_at'");
    if (!$disputeAtColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_trade_orders ADD COLUMN dispute_requested_at TIMESTAMP NULL AFTER dispute_requested_by");
    }

    // A seller can now list under several banks/cards at once ("Добавить ещё
    // банк") — a buyer picks whichever is convenient when opening an order.
    // p2p_listings.card_number stays as a legacy single value for old rows;
    // this table is the real, current source of a listing's payment options.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS p2p_listing_banks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            listing_id INT UNSIGNED NOT NULL,
            bank_label VARCHAR(60) NOT NULL,
            card_number VARCHAR(20) NOT NULL,
            cardholder_name VARCHAR(120) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_p2p_listing_banks_listing (listing_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $bankCardholderColStmt = $pdo->query("SHOW COLUMNS FROM p2p_listing_banks LIKE 'cardholder_name'");
    if (!$bankCardholderColStmt->fetch()) {
        $pdo->exec("ALTER TABLE p2p_listing_banks ADD COLUMN cardholder_name VARCHAR(120) NOT NULL DEFAULT '' AFTER card_number");
    }

    // Backfill: any listing created before this table existed gets one bank
    // row derived from its old single card_number column, so it keeps working.
    $pdo->exec("
        INSERT INTO p2p_listing_banks (listing_id, bank_label, card_number)
        SELECT l.id, 'Банк', l.card_number
        FROM p2p_listings l
        LEFT JOIN p2p_listing_banks b ON b.listing_id = l.id
        WHERE b.id IS NULL AND l.card_number <> ''
    ");
}

/** All banks a seller listed for one listing — what the buyer picks from when opening an order. */
function matenP2pListingBanks(PDO $pdo, int $listingId): array {
    $stmt = $pdo->prepare("SELECT id, bank_label, card_number, cardholder_name FROM p2p_listing_banks WHERE listing_id = ? ORDER BY id ASC");
    $stmt->execute([$listingId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function matenP2pPublicId(string $prefix): string {
    return $prefix . '_' . bin2hex(random_bytes(12));
}

/** Creates a listing and immediately escrows the MATEN out of the seller's own balance. */
/**
 * $banks: array of ['label' => string, 'card_number' => string], 1-5 entries
 * — one row per bank the seller accepts payment on. payment_methods (the
 * tag list shown on the listing card) is derived from the bank labels.
 */
function matenP2pCreateListing(PDO $pdo, int $sellerId, string $amount, string $pricePerMaten, string $currency, string $minAmount, array $banks): array {
    matenRequireBcmath();

    $amount = trim($amount);
    $pricePerMaten = trim($pricePerMaten);
    $minAmount = trim($minAmount) !== '' ? trim($minAmount) : '0';
    $currency = strtoupper(trim($currency));

    $cleanBanks = [];
    foreach (array_slice($banks, 0, 5) as $bank) {
        $label = trim((string) ($bank['label'] ?? ''));
        $card = preg_replace('/\D/', '', (string) ($bank['card_number'] ?? '')) ?? '';
        $cardholder = trim((string) ($bank['cardholder_name'] ?? ''));
        if ($label === '' || strlen($card) !== 16 || mb_strlen($cardholder) < 3) {
            continue;
        }
        $cleanBanks[] = ['label' => mb_substr($label, 0, 60), 'card_number' => $card, 'cardholder_name' => mb_substr($cardholder, 0, 120)];
    }

    if (!preg_match('/^\d+(\.\d{1,8})?$/', $amount) || bccomp($amount, '0', 8) <= 0) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }
    if (!preg_match('/^\d+(\.\d{1,4})?$/', $pricePerMaten) || bccomp($pricePerMaten, '0', 4) <= 0) {
        return ['ok' => false, 'error' => 'invalid_price'];
    }
    if (!preg_match('/^\d+(\.\d{1,8})?$/', $minAmount) || bccomp($minAmount, $amount, 8) > 0) {
        return ['ok' => false, 'error' => 'invalid_min_amount'];
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        return ['ok' => false, 'error' => 'invalid_currency'];
    }
    if (empty($cleanBanks)) {
        return ['ok' => false, 'error' => 'invalid_card_number'];
    }

    $paymentMethods = implode(', ', array_column($cleanBanks, 'label'));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT balance_maten FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$sellerId]);
        $balance = $stmt->fetchColumn();
        if ($balance === false) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'user_not_found'];
        }
        if (bccomp((string) $balance, $amount, 8) < 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'insufficient_balance'];
        }

        $debit = $pdo->prepare("UPDATE users SET balance_maten = balance_maten - ? WHERE id = ? AND balance_maten >= ?");
        $debit->execute([$amount, $sellerId, $amount]);
        if ($debit->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'insufficient_balance'];
        }

        $publicId = matenP2pPublicId('lst');
        $insert = $pdo->prepare("
            INSERT INTO p2p_listings (public_id, seller_id, card_number, currency, price_per_maten, total_amount, remaining_amount, min_amount, payment_methods)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$publicId, $sellerId, $cleanBanks[0]['card_number'], $currency, $pricePerMaten, $amount, $amount, $minAmount, $paymentMethods]);
        $listingId = (int) $pdo->lastInsertId();

        $bankInsert = $pdo->prepare("INSERT INTO p2p_listing_banks (listing_id, bank_label, card_number, cardholder_name) VALUES (?, ?, ?, ?)");
        foreach ($cleanBanks as $bank) {
            $bankInsert->execute([$listingId, $bank['label'], $bank['card_number'], $bank['cardholder_name']]);
        }

        $pdo->commit();
        return ['ok' => true, 'public_id' => $publicId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pCreateListing failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

/** Cancels a listing and refunds whatever MATEN is still escrowed back to the seller. */
function matenP2pCancelListing(PDO $pdo, string $listingPublicId, int $sellerId): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM p2p_listings WHERE public_id = ? FOR UPDATE");
        $stmt->execute([$listingPublicId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$listing || (int) $listing['seller_id'] !== $sellerId) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($listing['status'] !== 'active') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'not_active'];
        }

        $pdo->prepare("UPDATE p2p_listings SET status = 'cancelled', remaining_amount = 0 WHERE id = ?")->execute([(int) $listing['id']]);
        $pdo->prepare("UPDATE users SET balance_maten = balance_maten + ? WHERE id = ?")->execute([(string) $listing['remaining_amount'], $sellerId]);

        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pCancelListing failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

/** Public id of the buyer's oldest completed order that still has no review — used to block new purchases until it's rated. */
function matenP2pPendingReviewOrder(PDO $pdo, int $buyerId): ?string {
    $stmt = $pdo->prepare("
        SELECT o.public_id FROM p2p_trade_orders o
        LEFT JOIN p2p_seller_reviews r ON r.order_id = o.id
        WHERE o.buyer_id = ? AND o.status = 'completed' AND r.id IS NULL
        ORDER BY o.completed_at ASC LIMIT 1
    ");
    $stmt->execute([$buyerId]);
    $publicId = $stmt->fetchColumn();
    return $publicId !== false ? (string) $publicId : null;
}

/** Buyer opens an order against a listing — reserves the amount, does NOT move MATEN yet. */
/** $bankId: id of the p2p_listing_banks row the buyer picked (0 = fall back to the listing's first/legacy bank). */
function matenP2pCreateOrder(PDO $pdo, string $listingPublicId, int $buyerId, string $amount, int $bankId = 0): array {
    matenRequireBcmath();
    $amount = trim($amount);
    if (!preg_match('/^\d+(\.\d{1,8})?$/', $amount) || bccomp($amount, '0', 8) <= 0) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }
    $pendingReview = matenP2pPendingReviewOrder($pdo, $buyerId);
    if ($pendingReview !== null) {
        return ['ok' => false, 'error' => 'review_required', 'order_public_id' => $pendingReview];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM p2p_listings WHERE public_id = ? FOR UPDATE");
        $stmt->execute([$listingPublicId]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$listing || $listing['status'] !== 'active') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ((int) $listing['seller_id'] === $buyerId) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'self_trade'];
        }
        if (bccomp($amount, (string) $listing['min_amount'], 8) < 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'below_minimum'];
        }
        if (bccomp($amount, (string) $listing['remaining_amount'], 8) > 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'exceeds_remaining'];
        }

        $newRemaining = bcsub((string) $listing['remaining_amount'], $amount, 8);
        $remainingUpdate = $pdo->prepare("UPDATE p2p_listings SET remaining_amount = ? WHERE id = ? AND remaining_amount = ?");
        $remainingUpdate->execute([$newRemaining, (int) $listing['id'], (string) $listing['remaining_amount']]);
        if ($remainingUpdate->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'listing_changed'];
        }
        if (bccomp($newRemaining, '0', 8) === 0) {
            $pdo->prepare("UPDATE p2p_listings SET status = 'completed' WHERE id = ?")->execute([(int) $listing['id']]);
        }

        $chosenBank = null;
        if ($bankId > 0) {
            $bankStmt = $pdo->prepare("SELECT bank_label, card_number, cardholder_name FROM p2p_listing_banks WHERE id = ? AND listing_id = ? LIMIT 1");
            $bankStmt->execute([$bankId, (int) $listing['id']]);
            $chosenBank = $bankStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($chosenBank === null) {
            // No (valid) bank_id supplied — fall back to the listing's first bank row, or its legacy single card_number.
            $firstBank = $pdo->prepare("SELECT bank_label, card_number, cardholder_name FROM p2p_listing_banks WHERE listing_id = ? ORDER BY id ASC LIMIT 1");
            $firstBank->execute([(int) $listing['id']]);
            $chosenBank = $firstBank->fetch(PDO::FETCH_ASSOC) ?: ['bank_label' => '', 'card_number' => (string) $listing['card_number'], 'cardholder_name' => ''];
        }

        $totalFiat = bcmul($amount, (string) $listing['price_per_maten'], 2);
        $publicId = matenP2pPublicId('ord');
        $insert = $pdo->prepare("
            INSERT INTO p2p_trade_orders (public_id, listing_id, seller_id, buyer_id, amount_maten, price_per_maten, currency, seller_card_number, seller_bank_label, seller_cardholder_name, total_fiat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$publicId, (int) $listing['id'], (int) $listing['seller_id'], $buyerId, $amount, (string) $listing['price_per_maten'], (string) $listing['currency'], (string) $chosenBank['card_number'], (string) $chosenBank['bank_label'], (string) $chosenBank['cardholder_name'], $totalFiat]);

        $pdo->commit();
        return ['ok' => true, 'public_id' => $publicId, 'seller_id' => (int) $listing['seller_id']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pCreateOrder failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

function matenP2pFetchOrder(PDO $pdo, string $orderPublicId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE public_id = ? LIMIT 1");
    $stmt->execute([$orderPublicId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    return $order ?: null;
}

/** Same as matenP2pFetchOrder but with counterpart display info (username/avatar) for the modal UI. */
function matenP2pFetchOrderWithParties(PDO $pdo, string $orderPublicId): ?array {
    $stmt = $pdo->prepare("
        SELECT o.*,
            b.username AS buyer_username, b.avatar_data AS buyer_avatar,
            s.username AS seller_username, s.avatar_data AS seller_avatar
        FROM p2p_trade_orders o
        JOIN users b ON b.id = o.buyer_id
        JOIN users s ON s.id = o.seller_id
        WHERE o.public_id = ? LIMIT 1
    ");
    $stmt->execute([$orderPublicId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    return $order ?: null;
}

/** Buyer declares the off-platform fiat payment sent, attaching photo proof of the transfer. */
function matenP2pMarkPaid(PDO $pdo, string $orderPublicId, int $buyerId, string $paymentProof): array {
    if (trim($paymentProof) === '' || !preg_match('/^data:image\/(png|jpe?g|webp);base64,/', $paymentProof)) {
        return ['ok' => false, 'error' => 'proof_required'];
    }
    if (strlen($paymentProof) > 6 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'proof_too_large'];
    }
    $stmt = $pdo->prepare("UPDATE p2p_trade_orders SET status = 'payment_confirmed', paid_at = NOW(), payment_proof = ? WHERE public_id = ? AND buyer_id = ? AND status = 'pending_payment'");
    $stmt->execute([$paymentProof, $orderPublicId, $buyerId]);
    if ($stmt->rowCount() !== 1) {
        return ['ok' => false, 'error' => 'invalid_state'];
    }
    return ['ok' => true];
}

/** Seller confirms fiat received — releases the escrowed MATEN to the buyer. This is the only place MATEN moves in the whole P2P flow. */
function matenP2pConfirmReceived(PDO $pdo, string $orderPublicId, int $sellerId): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE public_id = ? FOR UPDATE");
        $stmt->execute([$orderPublicId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || (int) $order['seller_id'] !== $sellerId) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($order['status'] !== 'payment_confirmed') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_state'];
        }

        $pdo->prepare("UPDATE users SET balance_maten = balance_maten + ? WHERE id = ?")->execute([(string) $order['amount_maten'], (int) $order['buyer_id']]);
        $pdo->prepare("UPDATE p2p_trade_orders SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([(int) $order['id']]);

        $pdo->commit();
        return ['ok' => true, 'buyer_id' => (int) $order['buyer_id'], 'amount' => (string) $order['amount_maten']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pConfirmReceived failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

/** Cancels an order before completion and refunds the reserved MATEN back onto the listing. */
function matenP2pCancelOrder(PDO $pdo, string $orderPublicId, int $userId): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE public_id = ? FOR UPDATE");
        $stmt->execute([$orderPublicId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || ((int) $order['buyer_id'] !== $userId && (int) $order['seller_id'] !== $userId)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($order['status'] !== 'pending_payment') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_state'];
        }

        $pdo->prepare("UPDATE p2p_trade_orders SET status = 'cancelled' WHERE id = ?")->execute([(int) $order['id']]);
        $listingStmt = $pdo->prepare("SELECT id, status, remaining_amount FROM p2p_listings WHERE id = ? FOR UPDATE");
        $listingStmt->execute([(int) $order['listing_id']]);
        $listing = $listingStmt->fetch(PDO::FETCH_ASSOC);
        if ($listing) {
            $newRemaining = bcadd((string) $listing['remaining_amount'], (string) $order['amount_maten'], 8);
            $pdo->prepare("UPDATE p2p_listings SET remaining_amount = ?, status = 'active' WHERE id = ?")->execute([$newRemaining, (int) $listing['id']]);
        }

        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pCancelOrder failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

/** Buyer or seller flags an order for admin help — freezes it in 'disputed' so neither side can keep acting until an admin steps in. */
function matenP2pRequestAdminHelp(PDO $pdo, string $orderPublicId, int $requesterId): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE public_id = ? FOR UPDATE");
        $stmt->execute([$orderPublicId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || ((int) $order['buyer_id'] !== $requesterId && (int) $order['seller_id'] !== $requesterId)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (!in_array($order['status'], ['pending_payment', 'payment_confirmed'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_state'];
        }

        $pdo->prepare("UPDATE p2p_trade_orders SET status = 'disputed', dispute_requested_by = ?, dispute_requested_at = NOW() WHERE id = ?")
            ->execute([$requesterId, (int) $order['id']]);
        $pdo->prepare("INSERT INTO p2p_messages (order_id, sender_id, message) VALUES (?, 0, ?)")
            ->execute([(int) $order['id'], 'Пользователь позвал администратора. Ожидайте ответа в этом чате.']);

        $pdo->commit();
        $counterpartId = (int) $order['buyer_id'] === $requesterId ? (int) $order['seller_id'] : (int) $order['buyer_id'];
        return ['ok' => true, 'counterpart_id' => $counterpartId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pRequestAdminHelp failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

/** Admin posts a chat message on any order. sender_id 0 is reserved for this — never a real user id — and is rendered client-side as "ADMIN". */
function matenP2pAdminSendMessage(PDO $pdo, string $orderPublicId, string $message): array {
    $message = trim($message);
    if ($message === '' || mb_strlen($message) > 2000) {
        return ['ok' => false, 'error' => 'invalid_message'];
    }
    $order = matenP2pFetchOrder($pdo, $orderPublicId);
    if (!$order) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    $pdo->prepare("INSERT INTO p2p_messages (order_id, sender_id, message) VALUES (?, 0, ?)")->execute([(int) $order['id'], $message]);
    return ['ok' => true, 'buyer_id' => (int) $order['buyer_id'], 'seller_id' => (int) $order['seller_id']];
}

/**
 * Admin arbitration: releases the escrowed MATEN straight to the buyer —
 * used when the seller has the buyer's payment but won't confirm receipt.
 * Allowed from payment_confirmed or disputed, bypassing the normal
 * seller-only ownership check that matenP2pConfirmReceived enforces.
 */
function matenP2pAdminForceReleaseToBuyer(PDO $pdo, string $orderPublicId): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE public_id = ? FOR UPDATE");
        $stmt->execute([$orderPublicId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || !in_array($order['status'], ['payment_confirmed', 'disputed'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_state'];
        }

        $pdo->prepare("UPDATE users SET balance_maten = balance_maten + ? WHERE id = ?")->execute([(string) $order['amount_maten'], (int) $order['buyer_id']]);
        $pdo->prepare("UPDATE p2p_trade_orders SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([(int) $order['id']]);
        $pdo->prepare("INSERT INTO p2p_messages (order_id, sender_id, message) VALUES (?, 0, ?)")
            ->execute([(int) $order['id'], 'Спор решён администратором: MATEN отправлен покупателю.']);

        $pdo->commit();
        return ['ok' => true, 'buyer_id' => (int) $order['buyer_id'], 'seller_id' => (int) $order['seller_id'], 'amount' => (string) $order['amount_maten']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pAdminForceReleaseToBuyer failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

/**
 * Admin arbitration: cancels the order and refunds the reserved MATEN back
 * onto the seller's listing — used when the buyer never actually paid.
 * Allowed from pending_payment, payment_confirmed or disputed, bypassing
 * the ownership/state checks matenP2pCancelOrder enforces for normal users.
 */
function matenP2pAdminRefundToSeller(PDO $pdo, string $orderPublicId): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE public_id = ? FOR UPDATE");
        $stmt->execute([$orderPublicId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || !in_array($order['status'], ['pending_payment', 'payment_confirmed', 'disputed'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_state'];
        }

        $pdo->prepare("UPDATE p2p_trade_orders SET status = 'cancelled' WHERE id = ?")->execute([(int) $order['id']]);
        $listingStmt = $pdo->prepare("SELECT id, status, remaining_amount FROM p2p_listings WHERE id = ? FOR UPDATE");
        $listingStmt->execute([(int) $order['listing_id']]);
        $listing = $listingStmt->fetch(PDO::FETCH_ASSOC);
        if ($listing) {
            $newRemaining = bcadd((string) $listing['remaining_amount'], (string) $order['amount_maten'], 8);
            $pdo->prepare("UPDATE p2p_listings SET remaining_amount = ?, status = 'active' WHERE id = ?")->execute([$newRemaining, (int) $listing['id']]);
        }
        $pdo->prepare("INSERT INTO p2p_messages (order_id, sender_id, message) VALUES (?, 0, ?)")
            ->execute([(int) $order['id'], 'Спор решён администратором: сделка отменена, MATEN возвращён продавцу.']);

        $pdo->commit();
        return ['ok' => true, 'buyer_id' => (int) $order['buyer_id'], 'seller_id' => (int) $order['seller_id']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('matenP2pAdminRefundToSeller failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'internal_error'];
    }
}

/** Admin dismisses a dispute without moving money — reverts the order to whatever state it was in before the dispute was opened. */
function matenP2pAdminResolveDispute(PDO $pdo, string $orderPublicId): array {
    $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE public_id = ? LIMIT 1");
    $stmt->execute([$orderPublicId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || $order['status'] !== 'disputed') {
        return ['ok' => false, 'error' => 'invalid_state'];
    }
    $revertTo = $order['paid_at'] !== null ? 'payment_confirmed' : 'pending_payment';
    $pdo->prepare("UPDATE p2p_trade_orders SET status = ? WHERE id = ?")->execute([$revertTo, (int) $order['id']]);
    $pdo->prepare("INSERT INTO p2p_messages (order_id, sender_id, message) VALUES (?, 0, ?)")
        ->execute([(int) $order['id'], 'Администратор закрыл спор без вмешательства — сделка продолжается.']);
    return ['ok' => true, 'buyer_id' => (int) $order['buyer_id'], 'seller_id' => (int) $order['seller_id']];
}

function matenP2pSendMessage(PDO $pdo, string $orderPublicId, int $senderId, string $message): array {
    $message = trim($message);
    if ($message === '' || mb_strlen($message) > 2000) {
        return ['ok' => false, 'error' => 'invalid_message'];
    }
    $order = matenP2pFetchOrder($pdo, $orderPublicId);
    if (!$order || ((int) $order['buyer_id'] !== $senderId && (int) $order['seller_id'] !== $senderId)) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    $pdo->prepare("INSERT INTO p2p_messages (order_id, sender_id, message) VALUES (?, ?, ?)")->execute([(int) $order['id'], $senderId, $message]);
    $counterpartId = (int) $order['buyer_id'] === $senderId ? (int) $order['seller_id'] : (int) $order['buyer_id'];
    return ['ok' => true, 'counterpart_id' => $counterpartId, 'order_id' => (int) $order['id']];
}

function matenP2pFetchMessages(PDO $pdo, int $orderId, int $limit = 100): array {
    $stmt = $pdo->prepare("SELECT * FROM p2p_messages WHERE order_id = ? ORDER BY created_at ASC, id ASC LIMIT ?");
    $stmt->bindValue(1, $orderId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Only the buyer of a completed order can review, exactly once. */
function matenP2pSubmitReview(PDO $pdo, string $orderPublicId, int $reviewerId, int $rating, string $comment): array {
    $rating = max(1, min(5, $rating));
    $comment = trim(mb_substr($comment, 0, 500));

    $order = matenP2pFetchOrder($pdo, $orderPublicId);
    if (!$order || (int) $order['buyer_id'] !== $reviewerId) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if ($order['status'] !== 'completed') {
        return ['ok' => false, 'error' => 'not_completed'];
    }
    try {
        $pdo->prepare("INSERT INTO p2p_seller_reviews (order_id, reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, ?, ?, ?)")
            ->execute([(int) $order['id'], $reviewerId, (int) $order['seller_id'], $rating, $comment]);
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'already_reviewed'];
    }
    return ['ok' => true];
}

function matenP2pSellerRating(PDO $pdo, int $sellerId): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating FROM p2p_seller_reviews WHERE reviewed_id = ?");
    $stmt->execute([$sellerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'avg_rating' => null];
    return [
        'count' => (int) ($row['cnt'] ?? 0),
        'average' => $row['avg_rating'] !== null ? round((float) $row['avg_rating'], 2) : null,
    ];
}

/** Individual reviews left for a seller, newest first — the text/star detail behind matenP2pSellerRating's summary. */
function matenP2pSellerReviews(PDO $pdo, int $sellerId, int $limit = 50): array {
    $stmt = $pdo->prepare("
        SELECT r.rating, r.comment, r.created_at, u.username, u.avatar_data, o.amount_maten, o.currency
        FROM p2p_seller_reviews r
        JOIN users u ON u.id = r.reviewer_id
        JOIN p2p_trade_orders o ON o.id = r.order_id
        WHERE r.reviewed_id = ?
        ORDER BY r.created_at DESC LIMIT ?
    ");
    $stmt->bindValue(1, $sellerId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Average price of the last N completed trades in a currency — this is the
 * live source for the site-wide MATEN/fiat rate (see maten_rate.php).
 */
function matenP2pRecentAveragePrice(PDO $pdo, string $currency, int $limit = 5): ?string {
    $stmt = $pdo->prepare("SELECT price_per_maten FROM p2p_trade_orders WHERE currency = ? AND status = 'completed' ORDER BY completed_at DESC LIMIT ?");
    $stmt->bindValue(1, strtoupper($currency), PDO::PARAM_STR);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($prices)) {
        return null;
    }
    matenRequireBcmath();
    $sum = '0';
    foreach ($prices as $p) {
        $sum = bcadd($sum, (string) $p, 8);
    }
    return bcdiv($sum, (string) count($prices), 8);
}

/**
 * Daily average price_per_maten for completed trades in $currency, from the
 * first completed trade in that currency to today, forward-filling any day
 * with no trades so the homepage chart line has no gaps. Empty array when
 * the currency has no completed trades at all yet (chart shows its empty
 * state in that case).
 */
function matenP2pDailyPriceSeries(PDO $pdo, string $currency): array {
    $currency = strtoupper($currency);
    $stmt = $pdo->prepare("
        SELECT DATE(completed_at) AS day, AVG(price_per_maten) AS avg_price
        FROM p2p_trade_orders
        WHERE currency = ? AND status = 'completed'
        GROUP BY DATE(completed_at)
        ORDER BY day ASC
    ");
    $stmt->execute([$currency]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return [];
    }

    $byDay = [];
    foreach ($rows as $row) {
        $byDay[(string) $row['day']] = round((float) $row['avg_price'], 8);
    }

    $series = [];
    $cursor = new DateTimeImmutable((string) $rows[0]['day']);
    $today = new DateTimeImmutable('today');
    $lastPrice = null;
    while ($cursor <= $today) {
        $key = $cursor->format('Y-m-d');
        $lastPrice = $byDay[$key] ?? $lastPrice;
        $series[] = ['date' => $key, 'price' => $lastPrice];
        $cursor = $cursor->modify('+1 day');
    }
    return $series;
}

/** 24h completed-trade count + fiat volume for a currency, for the homepage chart's stats row. */
function matenP2pLast24hStats(PDO $pdo, string $currency): array {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS trades, COALESCE(SUM(total_fiat), 0) AS volume
        FROM p2p_trade_orders
        WHERE currency = ? AND status = 'completed' AND completed_at >= (NOW() - INTERVAL 24 HOUR)
    ");
    $stmt->execute([strtoupper($currency)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['trades' => 0, 'volume' => 0];
    return ['trades' => (int) $row['trades'], 'volume' => (string) $row['volume']];
}

/** Active listings, highest-rated sellers first (unrated sellers sort last), then cheapest price. */
function matenP2pActiveListings(PDO $pdo, ?string $currency = null, int $limit = 50): array {
    $ratingJoin = "
        LEFT JOIN (
            SELECT reviewed_id, AVG(rating) AS avg_rating, COUNT(*) AS rating_count
            FROM p2p_seller_reviews GROUP BY reviewed_id
        ) r ON r.reviewed_id = l.seller_id
    ";
    if ($currency) {
        $stmt = $pdo->prepare("
            SELECT l.*, u.username, u.email, u.avatar_data, r.avg_rating AS seller_rating, COALESCE(r.rating_count, 0) AS seller_rating_count
            FROM p2p_listings l
            JOIN users u ON u.id = l.seller_id
            {$ratingJoin}
            WHERE l.status = 'active' AND l.currency = ?
            ORDER BY COALESCE(r.avg_rating, 0) DESC, l.price_per_maten ASC, l.created_at DESC LIMIT ?
        ");
        $stmt->bindValue(1, strtoupper($currency), PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("
            SELECT l.*, u.username, u.email, u.avatar_data, r.avg_rating AS seller_rating, COALESCE(r.rating_count, 0) AS seller_rating_count
            FROM p2p_listings l
            JOIN users u ON u.id = l.seller_id
            {$ratingJoin}
            WHERE l.status = 'active'
            ORDER BY COALESCE(r.avg_rating, 0) DESC, l.created_at DESC LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return matenP2pAttachBanks($pdo, $listings);
}

function matenP2pMyListings(PDO $pdo, int $sellerId): array {
    $stmt = $pdo->prepare("SELECT * FROM p2p_listings WHERE seller_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$sellerId]);
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return matenP2pAttachBanks($pdo, $listings);
}

/** Attaches each listing's bank list (see matenP2pListingBanks) under a 'banks' key — used by both the buy-tab feed and "my listings". */
function matenP2pAttachBanks(PDO $pdo, array $listings): array {
    foreach ($listings as &$listing) {
        $listing['banks'] = matenP2pListingBanks($pdo, (int) $listing['id']);
    }
    unset($listing);
    return $listings;
}

function matenP2pMyOrders(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM p2p_trade_orders WHERE buyer_id = ? OR seller_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// --- admin_support_p2p.php read helpers -------------------------------------

/** Count of orders currently awaiting admin attention — the red "Шақыртылым" badge on the support console. */
function matenP2pAdminDisputedCount(PDO $pdo): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM p2p_trade_orders WHERE status = 'disputed'")->fetchColumn();
}

/** Every active listing with seller + bank info, newest first — for the admin dispute console. */
function matenP2pAdminAllListings(PDO $pdo, int $limit = 200): array {
    $stmt = $pdo->prepare("
        SELECT l.*, u.username, u.email
        FROM p2p_listings l
        JOIN users u ON u.id = l.seller_id
        WHERE l.status = 'active'
        ORDER BY l.created_at DESC LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return matenP2pAttachBanks($pdo, $listings);
}

/** Every order with buyer/seller usernames, disputed ones surfaced first — for the admin dispute console. */
function matenP2pAdminAllOrders(PDO $pdo, int $limit = 300): array {
    $stmt = $pdo->prepare("
        SELECT o.*, b.username AS buyer_username, s.username AS seller_username
        FROM p2p_trade_orders o
        JOIN users b ON b.id = o.buyer_id
        JOIN users s ON s.id = o.seller_id
        ORDER BY (o.status = 'disputed') DESC, o.created_at DESC LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
