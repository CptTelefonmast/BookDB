# BookDB

BookDB is a small, framework-free PHP application for managing a personal book collection.
It is designed for simple self-hosting, for example on a Raspberry Pi or another small home server.

The application supports books, genres, wish list entries, location tracking, lending history,
read status, purchase source tracking, search, sorting, pagination and CSV export.

Attention! I'm not ashamed to admit that the original project is in German and I've translated everything using AI. I'm just to lazy to do it by hand lol. If you find any mistakes, feel free to notify me. 

## Security notice

BookDB currently does not include user accounts, authentication or role-based permissions.
Anyone who can access the application can add, edit, lend, return or delete records.

Use it only in a trusted private network unless you add authentication, HTTPS and proper server hardening.
I know it's badly coded. I'm a system integrator, not a programmer :)

---

## Important note about the database schema

The interface and documentation are in English, but the database table and column names are
intentionally still German. This keeps the international version compatible with existing
BookDB installations.

Do **not** rename these database identifiers unless you also update the PHP code:

- `buecher`
- `genres`
- `buch_genres`
- `buch_standorte`
- `ausleihen`
- `wishlist`
- `wishlist_genres`
- `autor`
- `titel`
- `reihe`
- `teil_der_reihe`
- `erscheinungsjahr`
- `gelesen`
- `gekauft_bei`
- `regal`
- `regalfach`
- `schuber`

---

## Features

- Add, edit and delete books
- Multiple genres per book
- Dynamic creation of new genres
- Reading status
- Purchase source field
- Location tracking with location history
- Shelf, shelf compartment and slipcase/box support
- Clickable slipcase/box overview
- Loan management
- Loan history
- Wish list
- Transfer wish list entries into the main book database
- Search across books, genres, locations, purchase source and open loans
- Sorting and pagination
- Responsive desktop/mobile layout
- Light/dark theme
- CSV export
- CLI helper for read status updates

---

## Requirements

- Apache
- PHP 8.x
- PHP MySQL extension
- MariaDB or MySQL
- A Linux/Raspberry Pi style environment is recommended

Install the required packages on Debian, Ubuntu or Raspberry Pi OS:

```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql unzip git
```

Enable and start the services:

```bash
sudo systemctl enable apache2
sudo systemctl enable mariadb
sudo systemctl start apache2
sudo systemctl start mariadb
```

---

## Install the application files

Clone or upload the project into Apache's web directory.

Example using Git:

```bash
cd /var/www/html
sudo git clone https://github.com/YOUR_USERNAME/BookDB.git bookdb
```

Example target directory:

```text
/var/www/html/bookdb
```

The files must be directly inside the application directory.

Correct:

```text
/var/www/html/bookdb/index.php
/var/www/html/bookdb/details.php
/var/www/html/bookdb/books.php
```

Wrong:

```text
/var/www/html/bookdb/bookdb/index.php
```

---

## Set file permissions before editing configuration

If the files were created by `root`, `www-data` or through `sudo git clone`, your normal user
may not be able to edit `db_connect.php` or overwrite files by FTP.

Replace `<yourusername>` with your Linux/FTP user:

```bash
sudo usermod -aG www-data <yourusername>

sudo chown -R www-data:www-data /var/www/html/bookdb

sudo find /var/www/html/bookdb -type d -exec chmod 2775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
```

After adding the user to the `www-data` group, log out and back in, or reconnect SSH/FTP.

Expected permissions should look similar to this:

```text
drwxrwsr-x  www-data www-data  bookdb
-rw-rw-r--  www-data www-data  db_connect.php
-rw-rw-r--  www-data www-data  index.php
```

Do not use `chmod 777`.

---

## Create the complete database

This section creates the database, the database user and **all required tables**.

The default values below match the fallback values in `db_connect.php`:

- Database: `bookdb`
- User: `bookdb_user`
- Password: `change_me`

For a real installation, change the password and update `db_connect.php` accordingly.

Run this whole block on the server:

```bash
sudo mysql <<'SQL'
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
SQL
```

Check that the tables exist:

```bash
mysql -u bookdb_user -p bookdb -e "SHOW TABLES;"
```

You should see:

```text
ausleihen
buecher
buch_genres
buch_standorte
genres
wishlist
wishlist_genres
```

---

## Configure the database connection

Edit:

```text
/var/www/html/bookdb/db_connect.php
```

The file supports these environment variables:

```text
BOOKDB_HOST
BOOKDB_USER
BOOKDB_PASSWORD
BOOKDB_NAME
```

The default values are:

```text
BOOKDB_HOST=localhost
BOOKDB_USER=bookdb_user
BOOKDB_PASSWORD=change_me
BOOKDB_NAME=bookdb
```

If you used the default SQL block above, the application should work without changing
`db_connect.php`. For a real installation, use a stronger password and keep the PHP config
in sync with the database user.

---

## Run the application

Open the application in your browser:

```text
http://localhost/bookdb
```

or from another device in your network:

```text
http://your-server/bookdb
```

---

## CSV export

Run from the command line:

```bash
cd /var/www/html/bookdb
php export.php
```

Optional target file:

```bash
php export.php /home/YOUR_USERNAME/bookdb_export.csv
```

---

## Read status CLI helper

Run:

```bash
cd /var/www/html/bookdb
php mark_read_status.php
```

Follow the prompts.

---

## Troubleshooting

### 500 Internal Server Error

Check Apache's error log:

```bash
sudo tail -n 100 /var/log/apache2/error.log
```

Common causes:

- missing PHP files
- wrong permissions
- wrong database credentials
- missing tables or columns
- PHP syntax errors after manual edits

### Permission denied when PHP includes a file

Reset ownership and permissions:

```bash
sudo chown -R www-data:www-data /var/www/html/bookdb
sudo find /var/www/html/bookdb -type d -exec chmod 2775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
sudo systemctl reload apache2
```

### FTP upload cannot overwrite files

Make sure your FTP user is in the `www-data` group and the application directory is group-writable:

```bash
sudo usermod -aG www-data <yourusername>
sudo chown -R www-data:www-data /var/www/html/bookdb
sudo find /var/www/html/bookdb -type d -exec chmod 2775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
```

Reconnect FTP after changing group membership.

### Database connection fails

Test the login manually:

```bash
mysql -u bookdb_user -p bookdb
```

Check that the credentials in `db_connect.php` match the database user.

---

## Backup recommendation

Before updating the application or changing the database schema, create a backup.

Database backup:

```bash
mysqldump -u bookdb_user -p bookdb > bookdb_backup.sql
```

File backup:

```bash
sudo tar -czf bookdb_files_backup.tar.gz /var/www/html/bookdb
```

---

## License

This project is licensed under the GNU General Public License.
