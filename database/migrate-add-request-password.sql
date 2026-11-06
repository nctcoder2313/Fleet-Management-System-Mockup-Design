USE fleetwise;

ALTER TABLE access_requests
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER reason;
