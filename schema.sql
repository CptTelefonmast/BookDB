CREATE DATABASE IF NOT EXISTS bookdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'bookdb_user'@'localhost' IDENTIFIED BY 'change_me';
GRANT ALL PRIVILEGES ON bookdb.* TO 'bookdb_user'@'localhost';
FLUSH PRIVILEGES;

USE bookdb;

CREATE TABLE IF NOT EXISTS buecher (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    autor VARCHAR(255) NOT NULL,
    titel VARCHAR(255) NOT NULL,
    reihe VARCHAR(255) NULL,
    teil_der_reihe INT UNSIGNED NULL DEFAULT NULL,
    erscheinungsjahr SMALLINT UNSIGNED NULL DEFAULT NULL,
    gelesen TINYINT(1) NOT NULL DEFAULT 0,
    gekauft_bei VARCHAR(255) NULL DEFAULT NULL,

    INDEX idx_buecher_autor (autor),
    INDEX idx_buecher_titel (titel),
    INDEX idx_buecher_reihe (reihe),
    INDEX idx_buecher_series_order (reihe, teil_der_reihe),
    INDEX idx_buecher_gekauft_bei (gekauft_bei)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS buch_genres (
    buch_id INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (buch_id, genre_id),
    INDEX idx_buch_genres_genre (genre_id),

    CONSTRAINT fk_buch_genres_buch
        FOREIGN KEY (buch_id)
        REFERENCES buecher(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_buch_genres_genre
        FOREIGN KEY (genre_id)
        REFERENCES genres(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS buch_standorte (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buch_id INT UNSIGNED NOT NULL,
    regal VARCHAR(255) NULL,
    regalfach VARCHAR(255) NULL,
    ist_im_schuber TINYINT(1) NOT NULL DEFAULT 0,
    schuber VARCHAR(255) NULL,
    standort_seit DATE NOT NULL,
    standort_bis DATE NULL,
    erstellt_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_buch_standorte_buch (buch_id),
    INDEX idx_buch_standorte_current (buch_id, standort_bis),
    INDEX idx_buch_standorte_regal (regal, regalfach),
    INDEX idx_buch_standorte_schuber (schuber),

    CONSTRAINT fk_buch_standorte_buch
        FOREIGN KEY (buch_id)
        REFERENCES buecher(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ausleihen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buch_id INT UNSIGNED NOT NULL,
    person VARCHAR(255) NOT NULL,
    verliehen_am DATE NOT NULL,
    zurueckgegeben_am DATE NULL,
    erstellt_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ausleihen_buch (buch_id),
    INDEX idx_ausleihen_current (buch_id, zurueckgegeben_am),
    INDEX idx_ausleihen_person (person),

    CONSTRAINT fk_ausleihen_buch
        FOREIGN KEY (buch_id)
        REFERENCES buecher(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    autor VARCHAR(255) NOT NULL,
    titel VARCHAR(255) NOT NULL,
    reihe VARCHAR(255) NULL,
    teil_der_reihe INT UNSIGNED NULL DEFAULT NULL,
    erscheinungsjahr SMALLINT UNSIGNED NULL DEFAULT NULL,

    INDEX idx_wishlist_autor (autor),
    INDEX idx_wishlist_titel (titel),
    INDEX idx_wishlist_reihe (reihe),
    INDEX idx_wishlist_series_order (reihe, teil_der_reihe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wishlist_genres (
    wishlist_id INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (wishlist_id, genre_id),
    INDEX idx_wishlist_genres_genre (genre_id),

    CONSTRAINT fk_wishlist_genres_wishlist
        FOREIGN KEY (wishlist_id)
        REFERENCES wishlist(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_wishlist_genres_genre
        FOREIGN KEY (genre_id)
        REFERENCES genres(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
