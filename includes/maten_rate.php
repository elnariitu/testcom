<?php
declare(strict_types=1);

/**
 * MATEN -> fiat exchange rate.
 *
 * The rate is derived from a single canonical MATEN-in-USD price (see
 * matenCanonicalUsdRate()), itself blended from the average price of recent
 * completed P2P trades in each currency (see includes/maten_p2p.php:
 * matenP2pRecentAveragePrice), converted to USD via cached fiat cross-rates
 * (see fiatFxRatesUsdBase()). Every displayed currency (KZT/RUB/USD) is then
 * canonicalUsd * (currency-per-USD) — this is what keeps Maten/KZT,
 * Maten/RUB and Maten/USD mutually consistent by construction instead of
 * each drifting independently from its own thin trade history.
 *
 * If there is no P2P trade history at all yet, this falls back to the fixed
 * MATEN_RATE_FALLBACK constants so the site never divides by zero or shows
 * an empty rate.
 *
 * Rate values are handled as decimal strings so money math can run through
 * bcmath without ever touching a float.
 */
const MATEN_RATE_FALLBACK = [
    'KZT' => '1.00000000',
    'RUB' => '1.00000000',
    'USD' => '1.00000000',
];

/** Used only if the FX API has never once succeeded and no cache exists yet — a rough placeholder, immediately replaced once the API answers. */
const FIAT_FX_EMERGENCY_FALLBACK = ['USD' => 1.0, 'KZT' => 480.0, 'RUB' => 95.0];
const FIAT_FX_CACHE_TTL_SECONDS = 3600;

function matenFxEnsureTable(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fiat_fx_cache (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            base VARCHAR(3) NOT NULL,
            rates_json TEXT NOT NULL,
            fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_fiat_fx_cache_base (base)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * KZT/RUB units per 1 USD, cached in the DB for up to an hour so we don't
 * hit the external API on every request. Falls back to a stale cached row
 * (or, failing that, a hardcoded emergency default) if the API call fails —
 * the site's rates must never break just because a free third-party API is
 * briefly down.
 */
function fiatFxRatesUsdBase(PDO $pdo): array {
    matenFxEnsureTable($pdo);
    $stmt = $pdo->query("SELECT rates_json, UNIX_TIMESTAMP(fetched_at) AS fetched_ts FROM fiat_fx_cache WHERE base = 'USD' LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && (time() - (int) $row['fetched_ts']) < FIAT_FX_CACHE_TTL_SECONDS) {
        $cached = json_decode((string) $row['rates_json'], true);
        if (is_array($cached)) {
            return array_merge(['USD' => 1.0], $cached);
        }
    }

    $fresh = fiatFxFetchFromApi();
    if ($fresh !== null) {
        $pdo->prepare("
            INSERT INTO fiat_fx_cache (base, rates_json) VALUES ('USD', ?)
            ON DUPLICATE KEY UPDATE rates_json = VALUES(rates_json), fetched_at = CURRENT_TIMESTAMP
        ")->execute([json_encode($fresh)]);
        return array_merge(['USD' => 1.0], $fresh);
    }

    if ($row) {
        $cached = json_decode((string) $row['rates_json'], true);
        if (is_array($cached)) {
            return array_merge(['USD' => 1.0], $cached);
        }
    }

    return FIAT_FX_EMERGENCY_FALLBACK;
}

/** Free, keyless FX API — returns ['KZT' => float, 'RUB' => float] (units per 1 USD), or null on any failure. */
function fiatFxFetchFromApi(): ?array {
    $context = stream_context_create(['http' => ['timeout' => 4], 'https' => ['timeout' => 4]]);
    $raw = @file_get_contents('https://open.er-api.com/v6/latest/USD', false, $context);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['result'] ?? '') !== 'success' || !isset($data['rates']['KZT'], $data['rates']['RUB'])) {
        return null;
    }
    return [
        'KZT' => (float) $data['rates']['KZT'],
        'RUB' => (float) $data['rates']['RUB'],
    ];
}

/**
 * Blends the recent P2P trade average of each currency (converted to USD)
 * into one canonical MATEN-in-USD price. Returns null when there is no
 * completed P2P trade in ANY currency yet, so the caller can fall back to
 * the fixed MATEN_RATE_FALLBACK instead of inventing a number from nothing.
 */
function matenCanonicalUsdRate(PDO $pdo): ?string {
    matenRequireBcmath();
    $fx = fiatFxRatesUsdBase($pdo);

    $usdPrices = [];
    foreach (['KZT', 'RUB', 'USD'] as $currency) {
        $avg = matenP2pRecentAveragePrice($pdo, $currency, 10);
        if ($avg === null) {
            continue;
        }
        $unitsPerUsd = $fx[$currency] ?? null;
        if ($unitsPerUsd === null || (float) $unitsPerUsd <= 0) {
            continue;
        }
        $usdPrices[] = bcdiv($avg, (string) $unitsPerUsd, 8);
    }

    if (empty($usdPrices)) {
        return null;
    }

    $sum = '0';
    foreach ($usdPrices as $price) {
        $sum = bcadd($sum, $price, 8);
    }
    return bcdiv($sum, (string) count($usdPrices), 8);
}

/** Rate as an exact decimal string — use this for money math. $pdo is optional so old call sites without a currency keep working against KZT. */
function matenRate(PDO $pdo, string $currency = 'KZT'): string {
    matenRequireBcmath();
    $currency = strtoupper($currency);

    $canonicalUsd = matenCanonicalUsdRate($pdo);
    if ($canonicalUsd === null) {
        return MATEN_RATE_FALLBACK[$currency] ?? '1.00000000';
    }

    $fx = fiatFxRatesUsdBase($pdo);
    $unitsPerUsd = $fx[$currency] ?? null;
    if ($unitsPerUsd === null || (float) $unitsPerUsd <= 0) {
        return MATEN_RATE_FALLBACK[$currency] ?? '1.00000000';
    }

    return bcmul($canonicalUsd, (string) $unitsPerUsd, 8);
}

/** Back-compat: KZT rate. */
function matenKztRate(?PDO $pdo = null): string {
    if ($pdo === null) {
        return MATEN_RATE_FALLBACK['KZT'];
    }
    return matenRate($pdo, 'KZT');
}

/** Rate as a float — display only, never for money math. */
function matenKztRateFloat(?PDO $pdo = null): float {
    return (float) matenKztRate($pdo);
}

/**
 * Money math needs bcmath. Fail loudly rather than silently degrading to
 * floats, which would round real balances wrong.
 */
function matenRequireBcmath(): void {
    if (!function_exists('bcmul')) {
        throw new RuntimeException(
            'The bcmath PHP extension is required for MATEN money math but is not installed.'
        );
    }
}

/**
 * Convert MATEN to fiat. Both operands and the result stay decimal strings.
 */
function matenToFiat(PDO $pdo, string $matenAmount, string $currency = 'KZT', int $scale = 8): string {
    matenRequireBcmath();
    return bcmul($matenAmount, matenRate($pdo, $currency), $scale);
}

/**
 * Convert fiat to MATEN: amount / rate.
 */
function matenFromFiat(PDO $pdo, string $fiatAmount, string $currency = 'KZT', int $scale = 8): string {
    matenRequireBcmath();
    $rate = matenRate($pdo, $currency);
    if (bccomp($rate, '0', 8) === 0) {
        throw new RuntimeException('Current MATEN rate must not be zero.');
    }
    return bcdiv($fiatAmount, $rate, $scale);
}
