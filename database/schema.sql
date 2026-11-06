-- =====================================================================
--  Puff Drive — интернет-магазин «Сухой туман»
--  Структура базы данных (MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4)
--
--  Импорт: phpMyAdmin → вкладка «Импорт» → schema.sql, затем seed.sql
--  или в консоли:  mysql -u USER -p DB_NAME < schema.sql
--  ВНИМАНИЕ: скрипт удаляет существующие таблицы проекта!
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
    order_status_history, payments, order_items, orders,
    cart_items, carts, promo_codes,
    reviews, product_aromas, aromas, product_images, product_variants, products, categories,
    machine_requests, user_addresses, password_resets, login_attempts, users, settings;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  Пользователи и доступ
-- ---------------------------------------------------------------------

CREATE TABLE users (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    phone          VARCHAR(20)  NULL COMMENT 'Формат +375XXXXXXXXX',
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    is_blocked     TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at  DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Покупатели и администраторы';

CREATE TABLE password_resets (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL COMMENT 'SHA-256 от токена; сам токен хранится только в ссылке',
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Токены восстановления пароля';

CREATE TABLE login_attempts (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(190) NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    is_success  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempts_email (email, created_at),
    KEY idx_login_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Журнал попыток входа (ограничение перебора)';

CREATE TABLE user_addresses (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(60)  NOT NULL DEFAULT 'Адрес' COMMENT 'Дом, Работа…',
    recipient   VARCHAR(100) NULL,
    phone       VARCHAR(20)  NULL,
    city        VARCHAR(100) NOT NULL,
    address     VARCHAR(255) NOT NULL COMMENT 'Улица, дом, квартира',
    postcode    VARCHAR(10)  NULL,
    is_default  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_addresses_user (user_id),
    CONSTRAINT fk_user_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Сохранённые адреса доставки';

-- ---------------------------------------------------------------------
--  Каталог
-- ---------------------------------------------------------------------

CREATE TABLE categories (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id    INT UNSIGNED NULL,
    type         ENUM('liquid','machine','part') NOT NULL COMMENT 'Тип определяет поведение карточки (конфигуратор у аппаратов)',
    slug         VARCHAR(120) NOT NULL,
    name         VARCHAR(120) NOT NULL,
    description  TEXT         NULL,
    image        VARCHAR(255) NULL,
    sort_order   INT          NOT NULL DEFAULT 0,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id),
    KEY idx_categories_active (is_active, sort_order),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Категории каталога';

CREATE TABLE products (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id        INT UNSIGNED NOT NULL,
    slug               VARCHAR(190) NOT NULL,
    name               VARCHAR(190) NOT NULL,
    short_description  VARCHAR(500) NULL,
    description        TEXT         NULL,
    specs              JSON         NULL COMMENT 'Характеристики: [{"name":"…","value":"…"}]',
    aroma_slots        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Число настраиваемых слотов ароматов (для аппаратов)',
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    is_featured        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Показывать в «Популярных» на главной',
    sales_count        INT UNSIGNED NOT NULL DEFAULT 0,
    views_count        INT UNSIGNED NOT NULL DEFAULT 0,
    rating_avg         DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT 'Пересчитывается при модерации отзывов',
    rating_count       INT UNSIGNED NOT NULL DEFAULT 0,
    meta_title         VARCHAR(190) NULL,
    meta_description   VARCHAR(300) NULL,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_category (category_id, is_active),
    KEY idx_products_featured (is_featured, is_active),
    KEY idx_products_created (created_at),
    KEY idx_products_rating (rating_avg),
    FULLTEXT KEY ft_products_search (name, short_description, description),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Товары';

CREATE TABLE product_variants (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id      INT UNSIGNED NOT NULL,
    sku             VARCHAR(64)  NOT NULL COMMENT 'Артикул',
    label           VARCHAR(80)  NOT NULL COMMENT 'Например «1 л» или «Базовая комплектация»',
    volume_ml       INT UNSIGNED NULL COMMENT 'Объём в мл (для жидкостей): 500, 1000, 5000',
    price           DECIMAL(10,2) NOT NULL COMMENT 'Обычная цена',
    sale_price      DECIMAL(10,2) NULL COMMENT 'Цена по акции (если меньше обычной и акция действует)',
    sale_starts_at  DATETIME     NULL,
    sale_ends_at    DATETIME     NULL,
    stock           INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Остаток на складе',
    sort_order      INT          NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_variants_sku (sku),
    KEY idx_product_variants_product (product_id, is_active, sort_order),
    KEY idx_product_variants_volume (volume_ml),
    KEY idx_product_variants_price (price),
    CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT chk_product_variants_price CHECK (price >= 0 AND (sale_price IS NULL OR sale_price >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Варианты товара: объём/комплектация, цена, остаток, артикул';

CREATE TABLE product_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    path        VARCHAR(255) NOT NULL COMMENT 'Путь относительно public/uploads/',
    alt         VARCHAR(190) NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    is_main     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_images_product (product_id, is_main, sort_order),
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Фотографии товаров';

CREATE TABLE aromas (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug         VARCHAR(80)  NOT NULL,
    name         VARCHAR(100) NOT NULL,
    color        CHAR(7)      NOT NULL DEFAULT '#C9A96E' COMMENT 'Цвет иконки аромата (HEX)',
    description  VARCHAR(500) NULL,
    is_popular   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Используется кнопкой «Заполнить популярными»',
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order   INT          NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aromas_slug (slug),
    KEY idx_aromas_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Справочник ароматов';

CREATE TABLE product_aromas (
    product_id  INT UNSIGNED NOT NULL,
    aroma_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (product_id, aroma_id),
    KEY idx_product_aromas_aroma (aroma_id),
    CONSTRAINT fk_product_aromas_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_product_aromas_aroma   FOREIGN KEY (aroma_id)   REFERENCES aromas (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Связь товар ↔ аромат';

CREATE TABLE reviews (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id            INT UNSIGNED NOT NULL,
    user_id               INT UNSIGNED NOT NULL,
    rating                TINYINT UNSIGNED NOT NULL,
    text                  TEXT         NOT NULL,
    status                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    is_verified_purchase  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Пометка «Купил товар»',
    moderated_at          DATETIME     NULL,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reviews_product_user (product_id, user_id),
    KEY idx_reviews_status (status, created_at),
    KEY idx_reviews_user (user_id),
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user    FOREIGN KEY (user_id)    REFERENCES users (id)    ON DELETE CASCADE,
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Отзывы (публикуются после модерации)';

-- ---------------------------------------------------------------------
--  Промокоды и корзина
-- ---------------------------------------------------------------------

CREATE TABLE promo_codes (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code              VARCHAR(40)  NOT NULL,
    description       VARCHAR(255) NULL,
    type              ENUM('percent','fixed') NOT NULL,
    value             DECIMAL(10,2) NOT NULL COMMENT 'Процент или сумма в BYN',
    min_order_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    starts_at         DATETIME     NULL,
    ends_at           DATETIME     NULL,
    usage_limit       INT UNSIGNED NULL COMMENT 'NULL — без ограничений',
    used_count        INT UNSIGNED NOT NULL DEFAULT 0,
    is_active         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promo_codes_code (code),
    KEY idx_promo_codes_active (is_active, starts_at, ends_at),
    CONSTRAINT chk_promo_codes_value CHECK (value > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Промокоды';

CREATE TABLE carts (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    promo_code_id  INT UNSIGNED NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carts_user (user_id),
    CONSTRAINT fk_carts_user  FOREIGN KEY (user_id)       REFERENCES users (id)       ON DELETE CASCADE,
    CONSTRAINT fk_carts_promo FOREIGN KEY (promo_code_id) REFERENCES promo_codes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Корзины авторизованных пользователей (у гостей — в сессии)';

CREATE TABLE cart_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cart_id       INT UNSIGNED NOT NULL,
    variant_id    INT UNSIGNED NOT NULL,
    quantity      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    options       JSON         NULL COMMENT 'Конфигурация аппарата: {"slots":[aroma_id × 6]}',
    options_hash  CHAR(32)     NOT NULL DEFAULT '' COMMENT 'MD5 от options — разные конфигурации = разные позиции',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cart_items_line (cart_id, variant_id, options_hash),
    KEY idx_cart_items_variant (variant_id),
    CONSTRAINT fk_cart_items_cart    FOREIGN KEY (cart_id)    REFERENCES carts (id)            ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
    CONSTRAINT chk_cart_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Позиции корзины';

-- ---------------------------------------------------------------------
--  Заказы и оплата
-- ---------------------------------------------------------------------

CREATE TABLE orders (
    id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    number                    VARCHAR(20)  NOT NULL COMMENT 'Номер для покупателя: PD-251029-0001',
    access_token              CHAR(32)     NOT NULL COMMENT 'Доступ гостя к странице заказа',
    user_id                   INT UNSIGNED NULL,
    status                    ENUM('new','confirmed','paid','shipped','delivered','cancelled') NOT NULL DEFAULT 'new',
    payment_method            VARCHAR(20)  NOT NULL COMMENT 'card | cod | erip',
    payment_status            ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    delivery_method           VARCHAR(20)  NOT NULL COMMENT 'pickup | courier | europost | belpost',
    customer_name             VARCHAR(100) NOT NULL,
    customer_phone            VARCHAR(20)  NOT NULL,
    customer_email            VARCHAR(190) NOT NULL,
    delivery_city             VARCHAR(100) NULL,
    delivery_address          VARCHAR(255) NULL,
    delivery_postcode         VARCHAR(10)  NULL,
    delivery_point            VARCHAR(255) NULL COMMENT 'Отделение Европочты',
    comment                   TEXT         NULL,
    subtotal                  DECIMAL(10,2) NOT NULL,
    discount_amount           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_cost             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total                     DECIMAL(10,2) NOT NULL,
    promo_code_id             INT UNSIGNED NULL,
    promo_code                VARCHAR(40)  NULL COMMENT 'Снимок кода на момент заказа',
    personal_data_consent_at  DATETIME     NOT NULL COMMENT 'Когда дано согласие на обработку ПД (Закон РБ № 99-З)',
    ip                        VARCHAR(45)  NULL,
    created_at                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (number),
    KEY idx_orders_user (user_id, created_at),
    KEY idx_orders_status (status, created_at),
    KEY idx_orders_created (created_at),
    CONSTRAINT fk_orders_user  FOREIGN KEY (user_id)       REFERENCES users (id)       ON DELETE SET NULL,
    CONSTRAINT fk_orders_promo FOREIGN KEY (promo_code_id) REFERENCES promo_codes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Заказы';

CREATE TABLE order_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id       INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED NULL,
    variant_id     INT UNSIGNED NULL,
    product_name   VARCHAR(190) NOT NULL COMMENT 'Снимок названия на момент покупки',
    variant_label  VARCHAR(80)  NULL,
    sku            VARCHAR(64)  NULL,
    price          DECIMAL(10,2) NOT NULL COMMENT 'Снимок цены за единицу',
    quantity       SMALLINT UNSIGNED NOT NULL,
    line_total     DECIMAL(10,2) NOT NULL,
    options        JSON         NULL COMMENT 'Конфигурация аппарата: {"slots":[{"id":1,"name":"Ocean"}, …]}',
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    KEY idx_order_items_variant (variant_id),
    CONSTRAINT fk_order_items_order   FOREIGN KEY (order_id)   REFERENCES orders (id)           ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id)         ON DELETE SET NULL,
    CONSTRAINT fk_order_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Позиции заказа';

CREATE TABLE order_status_history (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    INT UNSIGNED NOT NULL,
    status      VARCHAR(20)  NOT NULL,
    comment     VARCHAR(255) NULL,
    user_id     INT UNSIGNED NULL COMMENT 'Кто сменил статус (администратор)',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_status_history_order (order_id, created_at),
    CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_status_history_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='История смены статусов заказа';

CREATE TABLE payments (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT UNSIGNED NOT NULL,
    provider        VARCHAR(30)  NOT NULL COMMENT 'mock | bepaid | erip | cod',
    transaction_id  VARCHAR(100) NULL COMMENT 'ID транзакции у провайдера',
    amount          DECIMAL(10,2) NOT NULL,
    currency        CHAR(3)      NOT NULL DEFAULT 'BYN',
    status          ENUM('pending','success','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    payload         JSON         NULL COMMENT 'Ответ/callback провайдера (без данных карт!)',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_transaction (provider, transaction_id),
    KEY idx_payments_order (order_id),
    KEY idx_payments_status (status),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Платежи (под любой шлюз: mock, bePaid, ЕРИП)';

-- ---------------------------------------------------------------------
--  Заявки и настройки
-- ---------------------------------------------------------------------

CREATE TABLE machine_requests (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NULL,
    name             VARCHAR(100) NOT NULL,
    phone            VARCHAR(20)  NOT NULL,
    city             VARCHAR(100) NOT NULL,
    comment          TEXT         NULL,
    status           ENUM('new','in_progress','closed') NOT NULL DEFAULT 'new',
    manager_comment  TEXT         NULL,
    ip               VARCHAR(45)  NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_requests_status (status, created_at),
    CONSTRAINT fk_machine_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Заявки на покупку аппарата / бизнес';

CREATE TABLE settings (
    setting_key    VARCHAR(100) NOT NULL,
    setting_value  TEXT         NULL,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Настройки магазина (контакты, соцсети, тексты, доставка)';
