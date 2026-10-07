<?php
/**
 * Tenancy lifecycle and move-out request helpers.
 */
function ensureMoveOutRequestsTable(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS move_out_requests (
        request_id INT(11) NOT NULL AUTO_INCREMENT,
        tenancy_id INT(11) NOT NULL,
        tenant_id INT(11) NOT NULL,
        owner_id INT(11) NOT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        proposed_move_out_date DATE NOT NULL,
        responded_at DATETIME DEFAULT NULL,
        status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
        initiated_by ENUM('Tenant','Owner') NOT NULL DEFAULT 'Tenant',
        PRIMARY KEY (request_id),
        KEY tenancy_id (tenancy_id),
        KEY tenant_id (tenant_id),
        KEY owner_id (owner_id),
        CONSTRAINT move_out_requests_ibfk_1 FOREIGN KEY (tenancy_id)
            REFERENCES tenancies (tenancy_id) ON DELETE CASCADE,
        CONSTRAINT move_out_requests_ibfk_2 FOREIGN KEY (tenant_id)
            REFERENCES tenants (tenant_id) ON DELETE CASCADE,
        CONSTRAINT move_out_requests_ibfk_3 FOREIGN KEY (owner_id)
            REFERENCES owners (owner_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $ready = true;
}

/**
 * Finish tenancies whose scheduled move-out date has already passed and
 * keep apartment occupancy in sync with the current date.
 */
function syncTenancyLifecycle(PDO $pdo): void
{
    ensureMoveOutRequestsTable($pdo);

    try {
        $pdo->beginTransaction();

        $expiredStmt = $pdo->query("SELECT tenancy_id, apartment_id
            FROM tenancies
            WHERE tenancy_status = 'Active'
              AND move_out_date IS NOT NULL
              AND move_out_date < CURDATE()
            FOR UPDATE");
        $expired = $expiredStmt->fetchAll();

        foreach ($expired as $row) {
            $pdo->prepare("UPDATE tenancies
                SET tenancy_status = 'Ended'
                WHERE tenancy_id = ?
                  AND tenancy_status = 'Active'")->execute([(int)$row['tenancy_id']]);
        }

        // Any scheduled tenancy whose move-in date has arrived now owns the unit.
        $pdo->exec("UPDATE apartments a
            JOIN tenancies tn ON tn.apartment_id = a.apartment_id
            SET a.status = 'Occupied'
            WHERE tn.tenancy_status = 'Active'
              AND tn.move_in_date <= CURDATE()
              AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())");

        // Release apartments that no longer have a current or scheduled-active tenancy.
        $pdo->exec("UPDATE apartments a
            SET a.status = 'Available'
            WHERE a.status = 'Occupied'
              AND NOT EXISTS (
                  SELECT 1
                  FROM tenancies tn
                  WHERE tn.apartment_id = a.apartment_id
                    AND tn.tenancy_status = 'Active'
              )");

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Lifecycle synchronization should never take the application down.
    }
}

function getCurrentActiveTenancy(PDO $pdo, int $tenantId): ?array
{
    $stmt = $pdo->prepare("SELECT tn.*, a.owner_id, a.apartment_number, a.floor_level,
            a.description AS apartment_desc, a.monthly_rent, a.status AS apartment_status
        FROM tenancies tn
        JOIN apartments a ON a.apartment_id = tn.apartment_id
        WHERE tn.tenant_id = ?
          AND tn.tenancy_status = 'Active'
          AND tn.move_in_date <= CURDATE()
          AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
        ORDER BY tn.move_in_date DESC, tn.tenancy_id DESC
        LIMIT 1");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getScheduledTenancy(PDO $pdo, int $tenantId): ?array
{
    $stmt = $pdo->prepare("SELECT tn.*, a.owner_id, a.apartment_number, a.floor_level, a.monthly_rent
        FROM tenancies tn
        JOIN apartments a ON a.apartment_id = tn.apartment_id
        WHERE tn.tenant_id = ?
          AND tn.tenancy_status = 'Active'
          AND tn.move_in_date > CURDATE()
        ORDER BY tn.move_in_date ASC, tn.tenancy_id ASC
        LIMIT 1");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getLatestMoveOutNoticeForTenant(PDO $pdo, int $tenantId): ?array
{
    $stmt = $pdo->prepare("SELECT mor.*, tn.move_in_date, tn.move_out_date, tn.tenancy_status,
            a.apartment_id, a.apartment_number, a.floor_level, a.monthly_rent,
            a.owner_id, o.owner_name
        FROM move_out_requests mor
        JOIN tenancies tn ON tn.tenancy_id = mor.tenancy_id
        JOIN apartments a ON a.apartment_id = tn.apartment_id
        JOIN owners o ON o.owner_id = mor.owner_id
        WHERE mor.tenant_id = ?
          AND mor.status IN ('Pending','Approved')
          AND (mor.proposed_move_out_date IS NULL OR mor.proposed_move_out_date >= CURDATE())
        ORDER BY CASE WHEN mor.proposed_move_out_date >= CURDATE() THEN 0 ELSE 1 END,
                 mor.proposed_move_out_date ASC, mor.request_id DESC
        LIMIT 1");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getLatestMoveOutRequest(PDO $pdo, int $tenancyId): ?array
{
    $stmt = $pdo->prepare("SELECT *
        FROM move_out_requests
        WHERE tenancy_id = ?
          AND status IN ('Pending','Approved')
        ORDER BY request_id DESC
        LIMIT 1");
    $stmt->execute([$tenancyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function tenantHasMoveOutPermission(PDO $pdo, int $tenancyId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM move_out_requests
        WHERE tenancy_id = ?
          AND status IN ('Pending','Approved')");
    $stmt->execute([$tenancyId]);
    return (int)$stmt->fetchColumn() > 0;
}

function formatMoveOutStatus(string $status, string $initiatedBy = 'Tenant'): string
{
    if ($status === 'Pending') {
        return 'Pending owner approval';
    }
    if ($status === 'Approved' && $initiatedBy === 'Owner') {
        return 'Scheduled by owner';
    }
    if ($status === 'Approved') {
        return 'Approved';
    }
    return $status;
}
