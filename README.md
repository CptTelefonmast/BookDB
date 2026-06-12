# BookDB

BookDB is a small PHP/MariaDB web application for managing a personal book collection.
It supports books, genres, wishlist entries, location history, slipcases, lending status,
read status, purchase source tracking and CSV export.

## Requirements

- PHP 8.2 or newer
- MariaDB or MySQL
- Apache or another PHP-capable web server
- PHP mysqli extension

## Database compatibility

This internationalized version keeps the existing database schema unchanged.
The table and column names are intentionally still the original names used by the current BookDB schema, for example:

- `buecher`
- `genres`
- `buch_genres`
- `buch_standorte`
- `ausleihen`
- `wishlist`
- `wishlist_genres`
- `reihe`
- `teil_der_reihe`
- `gelesen`
- `gekauft_bei`

Only the user interface, comments and configuration text were translated or neutralized.
No SQL table or column names were renamed.

## Configuration

The database connection is configured in `db_connect.php`.
The recommended option is to provide these environment variables:

```bash
BOOKDB_HOST=localhost
BOOKDB_USER=bookdb_user
BOOKDB_PASSWORD=change_me
BOOKDB_NAME=bookdb
```

Alternatively, edit the fallback values in `db_connect.php` directly.
Do not commit real production credentials to a public repository.

## Installation

1. Copy the files to your web server directory.
2. Create or import the BookDB database schema.
3. Configure database credentials through environment variables or `db_connect.php`.
4. Open `index.php` in your browser.

## Notes

The application is intentionally kept framework-free and uses plain PHP with `mysqli`.
This makes it easy to deploy on small servers such as a Raspberry Pi.
