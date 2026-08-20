-- project.portal — forced password change, the password-side counterpart to
-- force_mfa_setup. Set by an admin resetting another account's password to a
-- one-time temp value; cleared the moment that account sets its own new one.
-- Defaults to 0 so no existing account is affected on upgrade.

SET NAMES utf8mb4;

ALTER TABLE admins
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER force_mfa_setup;
