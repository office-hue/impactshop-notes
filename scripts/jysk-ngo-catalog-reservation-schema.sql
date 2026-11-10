-- Explicit operator-approved schema step; never executed by WordPress init.
-- Target prefix wp_ is verified before execution. No existing table is altered.
CREATE TABLE wp_sharity_ngo_enrollment_reservations (
    reservation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload LONGTEXT NOT NULL,
    PRIMARY KEY (reservation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
