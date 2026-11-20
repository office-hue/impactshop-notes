-- Replace __PREFIX__ with the verified site's WordPress table prefix before applying.
CREATE TABLE IF NOT EXISTS `__PREFIX__sharity_expo_stripe_events_v1` (
    binding CHAR(64) NOT NULL,
    object_id VARCHAR(255) NOT NULL,
    stripe_event_id VARCHAR(255) NOT NULL,
    kind ENUM('payment', 'refund', 'refund_reversal') NOT NULL,
    payment_id VARCHAR(255) NOT NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    stripe_created BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (binding, kind, object_id),
    UNIQUE KEY binding_event (binding, stripe_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;
