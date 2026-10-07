<?php
/**
 * Ensure the apartment rental-request table exists.
 *
 * This keeps the feature working even when an older SAMS database dump was
 * imported without the new apartment_requests table.
 */
function ensureApartmentRequestsTable(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS apartment_requests (
        request_id INT(11) NOT NULL AUTO_INCREMENT,
        tenant_id INT(11) NOT NULL,
        apartment_id INT(11) NOT NULL,
        owner_id INT(11) NOT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        responded_at DATETIME DEFAULT NULL,
        status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        PRIMARY KEY (request_id),
        KEY tenant_id (tenant_id),
        KEY apartment_id (apartment_id),
        KEY owner_id (owner_id),
        CONSTRAINT apartment_requests_ibfk_1 FOREIGN KEY (tenant_id)
            REFERENCES tenants (tenant_id) ON DELETE CASCADE,
        CONSTRAINT apartment_requests_ibfk_2 FOREIGN KEY (apartment_id)
            REFERENCES apartments (apartment_id) ON DELETE CASCADE,
        CONSTRAINT apartment_requests_ibfk_3 FOREIGN KEY (owner_id)
            REFERENCES owners (owner_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $ready = true;
}
