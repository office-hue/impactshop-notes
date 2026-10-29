<?php
/** The shared live port uses native mysqli; wpdb-only SQL fakes no longer exercise it.
 * Reuse the existing isolated actual-wpdb SQL suite, including the JYSK award,
 * replay/conflict, level-write rollback and durable lost-COMMIT-ACK readback cases.
 * It requires IMPACTSHOP_MYSQL_CONFIG, IMPACTSHOP_WPDB_FILE and explicit local confirmation.
 * Missing sandbox fails explicitly; it is never reported as PASS.
 * The separate JYSK game/results/prize tests remain unchanged.
 */
require __DIR__ . '/impactshop-points-mysql.integration.php';
