-- =============================================================================
-- migrations/2.6.0.sql
-- Upgrades a database created with schema.sql before 2.6.0. Run it once:
--     mysql -u USER -p DBNAME < migrations/2.6.0.sql
-- New databases get all of this from schema.sql.
--
-- 1. Publications without a DOI: `doi` becomes optional and `url` (a landing
--    page, e.g. a Handle) is added, unique like the DOI.
-- 2. `files`: files attached to a publication (abstract, poster, video...).
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE publications
    MODIFY COLUMN doi VARCHAR(255) DEFAULT NULL,
    ADD COLUMN url VARCHAR(512) DEFAULT NULL AFTER doi,
    ADD UNIQUE KEY uq_url (url);

CREATE TABLE IF NOT EXISTS files (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    publication_id  INT UNSIGNED    NOT NULL,
    position        SMALLINT        NOT NULL DEFAULT 0,
    label           VARCHAR(255)    DEFAULT NULL,
    name            VARCHAR(512)    DEFAULT NULL,
    url             VARCHAR(1024)   NOT NULL,
    size_bytes      BIGINT UNSIGNED DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_publication (publication_id),
    CONSTRAINT fk_files_publication
        FOREIGN KEY (publication_id) REFERENCES publications(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
