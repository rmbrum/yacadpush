-- =============================================================================
-- schema.sql
-- Banco de dados de publicações acadêmicas
-- Alimentado pelo json intermediário do script ieee_to_zenodo.py
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- publications — registro principal, um por DOI
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS publications (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- identificadores
    doi                 VARCHAR(255)    NOT NULL,
    zenodo_deposit_id   INT UNSIGNED    DEFAULT NULL,
    zenodo_doi          VARCHAR(255)    DEFAULT NULL,
    zenodo_deposit_url  VARCHAR(1024)   DEFAULT NULL,

    -- metadados principais
    title               TEXT            NOT NULL,
    description         MEDIUMTEXT      DEFAULT NULL,
    upload_type         VARCHAR(64)     NOT NULL DEFAULT 'publication',
                        -- article | conferencepaper | book | report | publication
    publication_date    VARCHAR(32)     DEFAULT NULL,   -- YYYY ou YYYY-MM-DD
    publisher           VARCHAR(512)    DEFAULT NULL,

    -- journal (quando upload_type = article)
    journal_title       VARCHAR(512)    DEFAULT NULL,
    journal_volume      VARCHAR(32)     DEFAULT NULL,
    journal_issue       VARCHAR(32)     DEFAULT NULL,
    journal_pages       VARCHAR(64)     DEFAULT NULL,
    issn                VARCHAR(32)     DEFAULT NULL,

    -- conference (quando upload_type = conferencepaper)
    conference_title    VARCHAR(512)    DEFAULT NULL,
    conference_place    VARCHAR(512)    DEFAULT NULL,
    conference_dates    VARCHAR(128)    DEFAULT NULL,

    -- identificadores extras
    isbn                VARCHAR(32)     DEFAULT NULL,

    -- acesso
    access_right        VARCHAR(32)     NOT NULL DEFAULT 'open',
                        -- open | embargoed | restricted | closed
    embargo_date        DATE            DEFAULT NULL,
    access_conditions   TEXT            DEFAULT NULL,

    -- links externos (IEEE)
    ieee_html_url       VARCHAR(1024)   DEFAULT NULL,
    ieee_pdf_url        VARCHAR(1024)   DEFAULT NULL,

    -- controle de importação
    status              VARCHAR(32)     NOT NULL DEFAULT 'pending',
                        -- pending | uploaded | published | error
    error_message       TEXT            DEFAULT NULL,
    collected_at        DATETIME        DEFAULT NULL,
    imported_at         DATETIME        DEFAULT NULL,   -- quando carregado no DB
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_doi (doi),
    KEY idx_upload_type     (upload_type),
    KEY idx_publication_date(publication_date),
    KEY idx_status          (status),
    KEY idx_access_right    (access_right)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- authors — autores de cada publicação (N para 1 com publications)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS authors (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    publication_id  INT UNSIGNED    NOT NULL,
    position        SMALLINT        NOT NULL DEFAULT 0,  -- ordem na lista
    full_name       VARCHAR(512)    NOT NULL,
    affiliation     VARCHAR(1024)   DEFAULT NULL,
    orcid           VARCHAR(64)     DEFAULT NULL,        -- apenas o ID, sem URL
    email           VARCHAR(255)    DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_publication (publication_id),
    KEY idx_orcid       (orcid),
    CONSTRAINT fk_authors_publication
        FOREIGN KEY (publication_id) REFERENCES publications(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- keywords — palavras-chave (N para 1 com publications)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS keywords (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    publication_id  INT UNSIGNED    NOT NULL,
    keyword         VARCHAR(512)    NOT NULL,

    PRIMARY KEY (id),
    KEY idx_publication (publication_id),
    KEY idx_keyword     (keyword(191)),
    CONSTRAINT fk_keywords_publication
        FOREIGN KEY (publication_id) REFERENCES publications(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- related_identifiers — identificadores relacionados (N para 1 com publications)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS related_identifiers (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    publication_id  INT UNSIGNED    NOT NULL,
    identifier      VARCHAR(1024)   NOT NULL,
    relation        VARCHAR(64)     NOT NULL,
                    -- isIdenticalTo | isAlternateIdentifier | isSupplementTo | ...
    resource_type   VARCHAR(64)     DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_publication (publication_id),
    CONSTRAINT fk_related_publication
        FOREIGN KEY (publication_id) REFERENCES publications(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- communities — comunidades Zenodo associadas (N para 1 com publications)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS communities (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    publication_id  INT UNSIGNED    NOT NULL,
    community_id    VARCHAR(255)    NOT NULL,   -- slug da comunidade no Zenodo

    PRIMARY KEY (id),
    KEY idx_publication (publication_id),
    KEY idx_community   (community_id),
    CONSTRAINT fk_communities_publication
        FOREIGN KEY (publication_id) REFERENCES publications(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
