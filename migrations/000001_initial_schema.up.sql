-- Initial Portmaster schema (MariaDB, reached via pdo_mysql).
--
-- Ids are application-generated Snowflakes (BIGINT UNSIGNED), Base62-encoded at
-- the edge; only telemetry_logs auto-increments. Weights/capacities are DOUBLE
-- (PHP float). Enum-like columns (status, risk_class) are stored as their string
-- values. search_* columns hold the normalised LIKE-filter key.

CREATE TABLE roles (
    id           BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(255)    NOT NULL,
    permissions  JSON            NOT NULL,
    search_name  VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_roles_search_name (search_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id             BIGINT UNSIGNED NOT NULL,
    name           VARCHAR(255)    NOT NULL,
    email          VARCHAR(255)    NOT NULL,
    password_hash  VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id  BIGINT UNSIGNED NOT NULL,
    role_id  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    KEY idx_user_roles_role_id (role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id           BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(255)    NOT NULL,
    density      DOUBLE          NOT NULL DEFAULT 0,
    risk_class   VARCHAR(48)     NOT NULL,
    search_name  VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_products_search_name (search_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE containers (
    id              BIGINT UNSIGNED NOT NULL,
    code            VARCHAR(255)    NOT NULL,
    current_weight  DOUBLE          NOT NULL DEFAULT 0,
    max_capacity    DOUBLE          NOT NULL DEFAULT 0,
    status          VARCHAR(32)     NOT NULL,
    search_code     VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_containers_code (code),
    KEY idx_containers_search_code (search_code),
    KEY idx_containers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE container_items (
    container_id  BIGINT UNSIGNED NOT NULL,
    product_id    BIGINT UNSIGNED NOT NULL,
    quantity      DOUBLE          NOT NULL DEFAULT 0,
    weight        DOUBLE          NOT NULL DEFAULT 0,
    PRIMARY KEY (container_id, product_id),
    KEY idx_container_items_product (product_id),
    CONSTRAINT fk_container_items_container FOREIGN KEY (container_id) REFERENCES containers (id) ON DELETE CASCADE,
    CONSTRAINT fk_container_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE telemetry_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    container_id  BIGINT UNSIGNED NOT NULL,
    event         VARCHAR(255)    NOT NULL,
    description   TEXT            NULL,
    timestamp     DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_telemetry_container (container_id),
    CONSTRAINT fk_telemetry_container FOREIGN KEY (container_id) REFERENCES containers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
