# BookDB

BookDB is a small, framework-free PHP application for managing a personal book collection.
It is designed for simple self-hosting, for example on a Raspberry Pi or another small home server.

The application supports books, genres, wish list entries, location tracking, lending history,
read status, purchase source tracking, search, sorting, pagination and CSV export.

## Security notice

BookDB currently does not include user accounts, authentication or role-based permissions.
Anyone who can access the application can add, edit, lend, return or delete records.

Use it only in a trusted private network unless you add authentication, HTTPS and proper server hardening.

## Current features

- Add, edit and delete books
- Store author, title, series, series part and publication year
- Store read status
- Store where a book was purchased
- Assign multiple genres to one book
- Create new genres from the book form
- Search across book metadata, genres, locations, lending data and purchase source
- Sort the book table by common fields
- Pagination with selectable page size
- Responsive desktop and mobile layout
- Light and dark theme
- Book detail pages
- Current location tracking
- Location history through `buch_standorte`
- Shelf, shelf compartment and slipcase support
- Lending and return tracking
- Lending history
- Wish list management
- Transfer wish list entries into the main book database
- CSV export through `export.php`
- CLI helper for updating read status through `mark_read_status.php`

## Technology stack

- PHP 8.2 or newer
- MariaDB or MySQL
- Apache or another PHP-capable web server
- PHP `mysqli` extension
- Vanilla JavaScript
- Plain CSS, no frontend framework

## Database compatibility

The interface and documentation are written in English, but the database schema intentionally keeps the original German table and column names.

Examples:

- `buecher`
- `buch_genres`
- `buch_standorte`
- `ausleihen`
- `wishlist_genres`
- `reihe`
- `teil_der_reihe`
- `gelesen`
- `gekauft_bei`
- `regal`
- `regalfach`
- `schuber`

Do not rename these database identifiers unless you also update the PHP code accordingly.
Keeping the existing schema names makes this version compatible with existing BookDB installations.

## Installation

### 1. Install packages

On Debian, Ubuntu or Raspberry Pi OS:

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

### 2. Install the application files

Clone the repository into the Apache web root:

```bash
cd /var/www/html
sudo git clone https://github.com/YOUR_USERNAME/BookDB.git bookdb
```

Or upload the files manually into:

```text
/var/www/html/bookdb
```

The PHP files must be directly inside the application directory.

Correct:

```text
/var/www/html/bookdb/index.php
/var/www/html/bookdb/details.php
/var/www/html/bookdb/books.php
```

Incorrect:

```text
/var/www/html/bookdb/bookdb/index.php
```

### 3. Create the database

Open the MariaDB shell:

```bash
sudo mysql
```

Create the database:

```sql
CREATE DATABASE bookdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Create a database user. Replace the placeholders with your own values.

```sql
CREATE USER 'bookdb_user'@'localhost' IDENTIFIED BY 'change_this_password';
GRANT ALL PRIVILEGES ON bookdb.* TO 'bookdb_user'@'localhost';
FLUSH PRIVILEGES;
```

Select the database:

```sql
USE bookdb;
```

### 4. Create the tables

For a new installation, run the full schema below.

<details>
<summary>Full database schema</summary>

```sql
CREATE TABLE buecher (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    autor VARCHAR(255) NOT NULL,
    titel VARCHAR(255) NOT NULL,
    reihe VARCHAR(255) NULL,
    teil_der_reihe INT UNSIGNED NULL,
    erscheinungsjahr SMALLINT UNSIGNED NULL,
    gelesen TINYINT(1) NOT NULL DEFAULT 0,
    gekauft_bei VARCHAR(255) NULL DEFAULT NULL,

    INDEX idx_buecher_autor (autor),
    INDEX idx_buecher_titel (titel),
    INDEX idx_buecher_reihe (reihe),
    INDEX idx_buecher_series_order (reihe, teil_der_reihe),
    INDEX idx_buecher_gekauft_bei (gekauft_bei)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE buch_genres (
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

CREATE TABLE buch_standorte (
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

CREATE TABLE ausleihen (
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

CREATE TABLE wishlist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    autor VARCHAR(255) NOT NULL,
    titel VARCHAR(255) NOT NULL,
    reihe VARCHAR(255) NULL,
    teil_der_reihe INT UNSIGNED NULL,
    erscheinungsjahr SMALLINT UNSIGNED NULL,

    INDEX idx_wishlist_autor (autor),
    INDEX idx_wishlist_titel (titel),
    INDEX idx_wishlist_reihe (reihe)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist_genres (
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
```

</details>

Exit MariaDB after creating the schema:

```sql
EXIT;
```

### 5. Configure the database connection

BookDB reads the database connection settings from `db_connect.php`.
The recommended setup is to use Apache environment variables.

Supported variables:

```text
BOOKDB_DB_HOST
BOOKDB_DB_USER
BOOKDB_DB_PASSWORD
BOOKDB_DB_NAME
BOOKDB_DB_PORT
```

Example values:

```text
BOOKDB_DB_HOST=localhost
BOOKDB_DB_USER=bookdb_user
BOOKDB_DB_PASSWORD=change_this_password
BOOKDB_DB_NAME=bookdb
BOOKDB_DB_PORT=3306
```

#### Option A: Apache environment variables

Create a small Apache configuration file:

```bash
sudo nano /etc/apache2/conf-available/bookdb.conf
```

Add your values:

```apache
SetEnv BOOKDB_DB_HOST localhost
SetEnv BOOKDB_DB_USER bookdb_user
SetEnv BOOKDB_DB_PASSWORD change_this_password
SetEnv BOOKDB_DB_NAME bookdb
SetEnv BOOKDB_DB_PORT 3306
```

Enable the configuration and reload Apache:

```bash
sudo a2enconf bookdb
sudo systemctl reload apache2
```

#### Option B: Edit `db_connect.php`

For a simple local installation, you can edit the fallback values in `db_connect.php`.
Do not commit real credentials to a public repository.

### 6. Set file permissions

Apache must be able to read the files. If you upload files via FTP and want to overwrite them later, your FTP user also needs write access.

Replace `<yourusername>` with your Linux or FTP user:

```bash
sudo usermod -aG www-data <yourusername>

sudo chown -R www-data:www-data /var/www/html/bookdb

sudo find /var/www/html/bookdb -type d -exec chmod 2775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
```

After adding your user to the `www-data` group, log out and back in or reconnect your FTP session.

Expected permissions:

```text
drwxrwsr-x  www-data www-data  bookdb
-rw-rw-r--  www-data www-data  index.php
-rw-rw-r--  www-data www-data  details.php
-rw-rw-r--  www-data www-data  books.php
```

Avoid using `chmod 777`.

## Upgrading an older BookDB database

Older BookDB versions only used these tables:

- `buecher`
- `genres`
- `buch_genres`

The current version also needs these fields and tables:

- `buecher.gelesen`
- `buecher.gekauft_bei`
- `buch_standorte`
- `ausleihen`
- `wishlist`
- `wishlist_genres`

Before upgrading, create a backup.

```bash
mysqldump -u bookdb_user -p bookdb > bookdb_backup.sql
```

Then open MariaDB:

```bash
sudo mysql
```

Select the database:

```sql
USE bookdb;
```

Add the new columns if they do not exist yet:

```sql
ALTER TABLE buecher
    ADD COLUMN gelesen TINYINT(1) NOT NULL DEFAULT 0 AFTER erscheinungsjahr;

ALTER TABLE buecher
    ADD COLUMN gekauft_bei VARCHAR(255) NULL DEFAULT NULL AFTER gelesen;
```

Then create the missing tables from the full schema above.

If an index or column already exists, MariaDB/MySQL may return a duplicate-name error.
In that case, skip that specific statement and continue with the missing objects.

## Running the application

Open the application in your browser:

```text
http://localhost/bookdb
```

Or from another device in your network:

```text
http://your-server/bookdb
```

Adjust the URL if you installed BookDB into a different directory.

## CSV export

Run the export script from the command line:

```bash
cd /var/www/html/bookdb
php export.php
```

This creates a timestamped CSV file in the project directory.

You can also pass a target path:

```bash
php export.php /home/YOUR_USERNAME/bookdb_export.csv
```

## Read status CLI helper

The file `mark_read_status.php` can be used from the command line to update the read status for existing books.

```bash
cd /var/www/html/bookdb
php mark_read_status.php
```

Follow the prompts shown by the script.

## Troubleshooting

### 500 Internal Server Error

Check the Apache error log:

```bash
sudo tail -n 100 /var/log/apache2/error.log
```

Common causes:

- missing PHP files
- wrong file permissions
- incorrect database credentials
- missing database tables or columns
- syntax errors after manual edits

### Permission denied when including PHP files

Fix ownership and permissions:

```bash
sudo chown -R www-data:www-data /var/www/html/bookdb
sudo find /var/www/html/bookdb -type d -exec chmod 2775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
sudo systemctl reload apache2
```

### Database connection fails

Check that:

- the database exists
- the database user exists
- the password is correct
- `db_connect.php` or the Apache environment variables contain the same credentials
- the database user has privileges on the selected database

You can test the login manually:

```bash
mysql -u bookdb_user -p bookdb
```

### Tables or columns are missing

The current version expects at least these tables:

```text
buecher
genres
buch_genres
buch_standorte
ausleihen
wishlist
wishlist_genres
```

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

## License

This project is licensed under the GNU General Public License.
