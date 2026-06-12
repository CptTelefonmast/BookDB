# BookDB

> ⚠ **Security Notice**  
> This project currently does **not include encryption or user authentication**.  
> Anyone who can access the application can add, edit or delete books.  
>
> The project was originally built for use on a Raspberry Pi in a private network.  
> If you plan to expose it to the internet, you should add authentication and HTTPS.
> 
> Yes, I know it's coded badly. I'm a system integrator by trade, not a programmer :)
Overview
BookDB is a small PHP web application for managing a personal book collection.
It can store books with author, title, series information, publication year, read status,
purchase source, multiple genres, location history, loan history and wish list entries.
The interface supports searching, sorting, pagination, desktop and mobile layouts, and a
light / dark theme toggle.
---
Important schema note
The user interface and documentation are written in English, but the database table and
column names intentionally remain German.
Examples:
`buecher`
`buch_genres`
`buch_standorte`
`ausleihen`
`reihe`
`teil_der_reihe`
`gelesen`
`gekauft_bei`
`regal`
`regalfach`
`schuber`
This is intentional to keep the application compatible with existing BookDB installations.
Do not rename these database identifiers unless you also update the PHP code accordingly.
---
Features
Add, edit and delete books
Store author, title, series, series part and publication year
Store read status
Store where a book was purchased
Assign multiple genres per book
Create new genres dynamically while adding or editing books
Search by:
author
title
series
publication year
genre
purchase source
current loan person
shelf
shelf compartment
slipcase / box
Sortable table columns
Pagination with configurable page size
Responsive desktop and mobile layout
Light / dark theme
Book details page
Current location tracking
Location history support
Shelf, shelf compartment and slipcase / box support
Loan management
Loan history
Wish list
Transfer wish list entries into the main book database
CSV export
CLI helper for updating read status
---
Technology Stack
PHP 8
MariaDB / MySQL
Apache
Vanilla JavaScript
CSS without a framework
---
Installation
Install required packages
On Debian, Ubuntu or Raspberry Pi OS:
```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql unzip git
```
Enable and start Apache and MariaDB:
```bash
sudo systemctl enable apache2
sudo systemctl enable mariadb

sudo systemctl start apache2
sudo systemctl start mariadb
```
---
Install the application files
Clone the repository into the Apache web directory:
```bash
cd /var/www/html
sudo git clone https://github.com/YOUR_USERNAME/BookDB.git bookdb
```
Your project directory should now be:
```text
/var/www/html/bookdb
```
Alternatively, upload the files manually via FTP or SCP into:
```text
/var/www/html/bookdb
```
Make sure the PHP files are directly inside that directory, not inside an additional nested
folder.
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
---
Database Setup
Open the MariaDB shell:
```bash
sudo mysql
```
Create the database
```sql
CREATE DATABASE bookdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Create a database user
Replace `<bookdb_user>` and `<secure_password>` with credentials of your choice.
```sql
CREATE USER '<bookdb_user>'@'localhost' IDENTIFIED BY '<secure_password>';
GRANT ALL PRIVILEGES ON bookdb.* TO '<bookdb_user>'@'localhost';
FLUSH PRIVILEGES;
```
Select the database:
```sql
USE bookdb;
```
---
Create Tables
The current BookDB version needs the full schema below.
Books
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
```
Genres
```sql
CREATE TABLE genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
Book-Genre relation
```sql
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
```
Book locations
The application stores location history instead of only one static location.  
The current location is the row where `standort_bis IS NULL`.
```sql
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
```
Loans
The current open loan is the row where `zurueckgegeben_am IS NULL`.
```sql
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
```
Wish list
Wish list entries intentionally store only core book metadata.  
They do not store location, loan status or read status because the books are not owned yet.
```sql
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
```
Wish list genre relation
```sql
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
Exit the MariaDB shell:
```sql
EXIT;
```
---
Upgrading an Older BookDB Database
If you already have an old BookDB database with only these tables:
`buecher`
`genres`
`buch_genres`
then you need to add the missing columns and tables.
Open MariaDB:
```bash
sudo mysql
```
Select your database:
```sql
USE bookdb;
```
Add the new book fields if they do not exist yet:
```sql
ALTER TABLE buecher
    ADD COLUMN gelesen TINYINT(1) NOT NULL DEFAULT 0 AFTER erscheinungsjahr;

ALTER TABLE buecher
    ADD COLUMN gekauft_bei VARCHAR(255) NULL DEFAULT NULL AFTER gelesen;
```
Create the missing tables:
```sql
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
    teil_der_reihe INT UNSIGNED NULL,
    erscheinungsjahr SMALLINT UNSIGNED NULL,

    INDEX idx_wishlist_autor (autor),
    INDEX idx_wishlist_titel (titel),
    INDEX idx_wishlist_reihe (reihe)
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
```
Optional but recommended indexes for older installations:
```sql
CREATE INDEX idx_buecher_autor ON buecher (autor);
CREATE INDEX idx_buecher_titel ON buecher (titel);
CREATE INDEX idx_buecher_reihe ON buecher (reihe);
CREATE INDEX idx_buecher_series_order ON buecher (reihe, teil_der_reihe);
CREATE INDEX idx_buecher_gekauft_bei ON buecher (gekauft_bei);
```
If an index already exists, MariaDB/MySQL will return an error. In that case, you can ignore
that specific duplicate-index error or check your existing indexes first.
Exit MariaDB:
```sql
EXIT;
```
---
Database Configuration
BookDB reads its database connection from `db_connect.php`.
The internationalized version supports these environment variables:
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
Option 1: Edit `db_connect.php`
For a simple local installation, you can edit the fallback values in:
```text
db_connect.php
```
Do not commit real credentials to a public repository.
Option 2: Use Apache environment variables
Create an Apache configuration file:
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
---
Folder Permissions
Apache must be able to read the files.  
If you upload files via FTP and want to overwrite them later, your FTP user also needs write
access.
Replace `<yourusername>` with your Linux / FTP user.
```bash
sudo usermod -aG www-data <yourusername>

sudo chown -R www-data:www-data /var/www/html/bookdb

sudo find /var/www/html/bookdb -type d -exec chmod 2775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
```
After adding your user to the `www-data` group, log out and back in, or reconnect your FTP
session.
Expected permissions should look similar to this:
```text
drwxrwsr-x  www-data www-data  bookdb
-rw-rw-r--  www-data www-data  index.php
-rw-rw-r--  www-data www-data  details.php
-rw-rw-r--  www-data www-data  books.php
```
Avoid using `chmod 777`.
---
Running the Application
Open the application in your browser:
```text
http://localhost/bookdb
```
or from another device in your network:
```text
http://your-server/bookdb
```
If you installed it into a different directory, adjust the URL accordingly.
---
CSV Export
The application includes a CLI export script:
```bash
cd /var/www/html/bookdb
php export.php
```
This creates a timestamped CSV file in the project directory.
You can also pass a target path:
```bash
php export.php /home/YOUR_USERNAME/bookdb_export.csv
```
---
Read Status CLI Helper
The file `mark_read_status.php` can be used from the command line to update read status
for existing books.
Example:
```bash
cd /var/www/html/bookdb
php mark_read_status.php
```
Follow the prompts shown by the script.
---
Troubleshooting
500 Internal Server Error
Check the Apache error log:
```bash
sudo tail -n 100 /var/log/apache2/error.log
```
Common causes:
missing PHP files
wrong file permissions
incorrect database credentials
missing database tables or columns
syntax errors after manual edits
Permission denied when including PHP files
Fix ownership and permissions:
```bash
sudo chown -R www-data:www-data /var/www/html/bookdb
sudo find /var/www/html/bookdb -type d -exec chmod 2775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
sudo systemctl reload apache2
```
Database connection fails
Check:
the database exists
the database user exists
the password is correct
`db_connect.php` or Apache environment variables use the same credentials
the user has privileges on the selected database
You can test the database login manually:
```bash
mysql -u bookdb_user -p bookdb
```
Tables or columns are missing
Make sure the full schema from this README was created.
The current version needs more than the original three tables. At minimum, the following
tables are expected:
```text
buecher
genres
buch_genres
buch_standorte
ausleihen
wishlist
wishlist_genres
```
---
Backup Recommendation
Before updating the application or changing the database schema, create a backup.
Example database backup:
```bash
mysqldump -u bookdb_user -p bookdb > bookdb_backup.sql
```
Example file backup:
```bash
sudo tar -czf bookdb_files_backup.tar.gz /var/www/html/bookdb
```
---
License
This project is licensed under the GNU General Public License (GPL).
