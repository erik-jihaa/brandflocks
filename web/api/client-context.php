<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/schema-helper.php';

function ce_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    require_once __DIR__ . '/session-repository.php';

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    try {
        ce_register_db_session_handler(ce_db());
    } catch (Throwable) {
        // DB nog niet beschikbaar — file-based session fallback.
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 14));

    session_name('CESESSID');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 14,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/** Service-aanroep: n8n, cron en scripts sturen de reset-token mee. */
function ce_request_is_service_call(): bool
{
    return function_exists('ce_reset_token_valid') && ce_reset_token_valid();
}

/** Platform-beheerders mogen wel over tenants heen werken. */
function ce_request_has_platform_session(): bool
{
    ce_session_start();

    return !empty($_SESSION['ce_platform_authenticated'])
        && trim((string) ($_SESSION['ce_platform_user_id'] ?? '')) !== '';
}

/** De tenant waarop deze browsersessie is ingelogd, of null. */
function ce_session_client_id(): ?string
{
    ce_session_start();
    if (empty($_SESSION['ce_authenticated'])) {
        return null;
    }

    $clientId = trim((string) ($_SESSION['ce_client_id'] ?? ''));

    return $clientId !== '' ? $clientId : null;
}

/**
 * Een ingelogde tenant blijft binnen zijn eigen sessie, ook als het verzoek een
 * andere client_id meestuurt. Service-aanroepen en platform-sessies mogen dat wel,
 * want die werken over tenants heen.
 */
function ce_resolve_client_id(PDO $pdo, ?string $explicit = null): ?string
{
    $explicit = trim((string) ($explicit ?? ''));

    $sessionClientId = ce_session_client_id();
    if ($sessionClientId !== null) {
        if (
            $explicit !== ''
            && !hash_equals($sessionClientId, $explicit)
            && !ce_request_is_service_call()
            && !ce_request_has_platform_session()
        ) {
            ce_log_cross_tenant_attempt($pdo, $sessionClientId, $explicit);
            ce_json_error('Geen toegang tot deze client', 403);
        }

        return $sessionClientId;
    }

    if ($explicit !== '') {
        return $explicit;
    }

    ce_load_env();
    $fromEnv = trim((string) (getenv('CE_DEFAULT_CLIENT_ID') ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    return null;
}

/**
 * True als dit verzoek van een gewone tenantsessie komt en dus binnen één client
 * moet blijven. False voor service-aanroepen, platform-sessies en lokale opzet
 * zonder login.
 */
function ce_request_is_tenant_scoped(PDO $pdo): bool
{
    if (ce_request_is_service_call() || ce_request_has_platform_session()) {
        return false;
    }

    require_once __DIR__ . '/auth-guard.php';

    return ce_auth_is_required($pdo);
}

/**
 * Voor endpoints die zonder client_id álle tenants behandelen. Service-aanroepen en
 * platform-sessies houden die brede werking; een ingelogde tenant wordt op zijn
 * eigen client vastgezet en krijgt 403 als hij een andere meestuurt.
 */
function ce_restrict_sync_scope_to_session(PDO $pdo, string $clientId): string
{
    if (!ce_request_is_tenant_scoped($pdo)) {
        return $clientId;
    }

    $sessionClientId = ce_session_client_id();
    if ($sessionClientId === null) {
        ce_json_error('Niet ingelogd', 401);
    }

    if ($clientId !== '' && !hash_equals($sessionClientId, $clientId)) {
        ce_log_cross_tenant_attempt($pdo, $sessionClientId, $clientId);
        ce_json_error('Geen toegang tot deze client', 403);
    }

    return $sessionClientId;
}

/** Logt een geweigerde cross-tenant poging; mag de afhandeling nooit breken. */
function ce_log_cross_tenant_attempt(PDO $pdo, string $sessionClientId, string $requestedClientId): void
{
    try {
        require_once __DIR__ . '/event-log-repository.php';
        ce_log_event(
            $pdo,
            'warning',
            'auth',
            'cross_tenant_blocked',
            'Verzoek met een andere client_id dan de sessie is geweigerd',
            [
                'clientId' => $sessionClientId,
                'requestedClientId' => $requestedClientId,
                'path' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ],
        );
    } catch (Throwable) {
        // Loggen mag de guard niet in de weg zitten.
    }
}

function ce_set_active_client_id(string $clientId): void
{
    ce_session_start();
    $_SESSION['ce_client_id'] = $clientId;
}

function ce_client_reviewer_email(PDO $pdo, ?string $clientId = null): ?string
{
    require_once __DIR__ . '/schema-helper.php';
    $clientId = ce_resolve_client_id($pdo, $clientId);
    if ($clientId === null || !ce_table_exists($pdo, 'clients')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT contact_role, contact_email, reviewer_email FROM clients WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $clientId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $role = strtolower(trim((string) ($row['contact_role'] ?? 'both')));
    $contact = trim((string) ($row['contact_email'] ?? ''));
    $reviewer = trim((string) ($row['reviewer_email'] ?? ''));

    if (in_array($role, ['reviewer', 'both'], true) && $contact !== '') {
        return $contact;
    }
    if ($reviewer !== '') {
        return $reviewer;
    }

    return $contact !== '' ? $contact : null;
}

/** @return array{sql: string, params: array<string, string>} */
function ce_client_scope_sql(
    PDO $pdo,
    string $table,
    string $alias,
    ?string $clientId = null,
    string $param = 'client_id',
): array {
    $clientId = ce_resolve_client_id($pdo, $clientId);
    if (
        $clientId === null
        || !ce_table_exists($pdo, $table)
        || !ce_table_has_column($pdo, $table, 'client_id')
    ) {
        return ['sql' => " AND 1=0", 'params' => []];
    }

    return [
        'sql' => " AND {$alias}.client_id = :{$param}",
        'params' => [$param => $clientId],
    ];
}

function ce_slot_belongs_to_client(PDO $pdo, string $slotId, ?string $clientId = null): bool
{
    if (!ce_table_exists($pdo, 'content_slots') || !ce_table_has_column($pdo, 'content_slots', 'client_id')) {
        return true;
    }

    $clientId = ce_resolve_client_id($pdo, $clientId);
    if ($clientId === null) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id FROM content_slots WHERE id = :id AND client_id = :client_id LIMIT 1');
    $stmt->execute(['id' => $slotId, 'client_id' => $clientId]);

    return (bool) $stmt->fetch();
}

function ce_assert_slot_client_access(PDO $pdo, string $slotId, ?string $clientId = null): void
{
    if (!ce_slot_belongs_to_client($pdo, $slotId, $clientId)) {
        throw new RuntimeException('Slot niet gevonden');
    }
}

/**
 * True als dit verzoek binnen één tenant moet blijven.
 * Service-token, platform-sessie en lokale opzet zonder login blijven buiten deze poort.
 */
function ce_tenant_isolation_enforced(PDO $pdo): bool
{
    return ce_request_is_tenant_scoped($pdo);
}

/**
 * Hoort dit theme, slot, bron, artikel, research-item, feedback of review-edit
 * bij de ingelogde tenant? Zonder afgedwongen isolatie is het antwoord ja.
 */
function ce_resource_belongs_to_session(
    PDO $pdo,
    string $kind,
    string $id,
    ?string $boundSlotId = null,
): bool {
    if (!ce_tenant_isolation_enforced($pdo)) {
        return true;
    }

    $clientId = ce_session_client_id();
    if ($clientId === null || $id === '') {
        return false;
    }

    $boundSlotId = trim((string) $boundSlotId);

    return match ($kind) {
        'slot' => ce_slot_belongs_to_client($pdo, $id, $clientId),
        'theme' => ce_id_in_client_table($pdo, 'themes', $id, $clientId),
        'source' => ce_id_in_client_table($pdo, 'sources', $id, $clientId),
        'article' => ce_article_belongs_to_client($pdo, $id, $clientId),
        'research' => ce_research_item_belongs_to_client($pdo, $id, $clientId),
        'feedback' => ce_feedback_belongs_to_client($pdo, $id, $clientId, $boundSlotId),
        'review_edit' => ce_review_edit_belongs_to_client($pdo, $id, $clientId, $boundSlotId),
        default => false,
    };
}

/** 404, zodat een vreemd id niet verraadt dat het bestaat. */
function ce_require_resource_access(
    PDO $pdo,
    string $kind,
    string $id,
    ?string $boundSlotId = null,
): void {
    if (!ce_resource_belongs_to_session($pdo, $kind, $id, $boundSlotId)) {
        ce_json_error('Niet gevonden', 404);
    }
}

function ce_id_in_client_table(PDO $pdo, string $table, string $id, string $clientId): bool
{
    if (!ce_table_exists($pdo, $table) || !ce_table_has_column($pdo, $table, 'client_id')) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE id = :id AND client_id = :client_id LIMIT 1");
    $stmt->execute(['id' => $id, 'client_id' => $clientId]);

    return (bool) $stmt->fetch();
}

function ce_article_belongs_to_client(PDO $pdo, string $articleId, string $clientId): bool
{
    if (!ce_table_exists($pdo, 'articles') || !ce_table_exists($pdo, 'content_slots')) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT a.id
         FROM articles a
         INNER JOIN content_slots s ON s.id = a.slot_id
         WHERE a.id = :id AND s.client_id = :client_id
         LIMIT 1'
    );
    $stmt->execute(['id' => $articleId, 'client_id' => $clientId]);

    return (bool) $stmt->fetch();
}

function ce_research_item_belongs_to_client(PDO $pdo, string $itemId, string $clientId): bool
{
    if (!ce_table_exists($pdo, 'research_items')) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT ri.id
         FROM research_items ri
         LEFT JOIN themes t ON t.id = ri.theme_id
         LEFT JOIN content_slots s ON s.id = ri.slot_id
         LEFT JOIN sources src ON src.id = ri.source_id
         WHERE ri.id = :id
           AND (
                t.client_id = :client_id
                OR s.client_id = :client_id
                OR src.client_id = :client_id
           )
         LIMIT 1'
    );
    $stmt->execute(['id' => $itemId, 'client_id' => $clientId]);

    return (bool) $stmt->fetch();
}

function ce_feedback_belongs_to_client(
    PDO $pdo,
    string $feedbackId,
    string $clientId,
    string $boundSlotId,
): bool {
    if (!ce_table_exists($pdo, 'article_feedback')) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT slot_id FROM article_feedback WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $feedbackId]);
    $slotId = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($slotId === '') {
        return false;
    }
    if ($boundSlotId !== '' && !hash_equals($slotId, $boundSlotId)) {
        return false;
    }

    return ce_slot_belongs_to_client($pdo, $slotId, $clientId);
}

function ce_review_edit_belongs_to_client(
    PDO $pdo,
    string $editId,
    string $clientId,
    string $boundSlotId,
): bool {
    if (!ce_table_exists($pdo, 'article_review_edits')) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT e.slot_id AS edit_slot, a.slot_id AS article_slot
         FROM article_review_edits e
         LEFT JOIN articles a ON a.id = e.article_id
         WHERE e.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    $slotId = trim((string) ($row['edit_slot'] ?? ''));
    if ($slotId === '') {
        $slotId = trim((string) ($row['article_slot'] ?? ''));
    }
    if ($slotId === '') {
        return false;
    }
    if ($boundSlotId !== '' && !hash_equals($slotId, $boundSlotId)) {
        return false;
    }

    return ce_slot_belongs_to_client($pdo, $slotId, $clientId);
}
