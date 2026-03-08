# BookDB
BookDB is a lightweight PHP web application for managing a personal book collection.
It allows storing books with authors, series information, publication year and multiple genres.  
The interface supports searching, sorting and pagination and works on both desktop and mobile devices.
# Features
- Add, edit and delete books
- Multiple genres per book
- Create new genres dynamically
- Search by
  - author
  - title
  - series
  - genre
- Sortable table columns
- Pagination
- Responsive layout (desktop + mobile)
- Light / dark theme
# Technology Stack
- PHP 8
- MariaDB / MySQL
- Apache
- Vanilla JavaScript
- CSS (no framework)

# Attention! As of now, there is no built-in encryption out of the box. It's just plain http. There's also no authentication to edit or delete books for now as I've got it all running on a RasPi on my internal network.


# Requirements
# Install the required packages:
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql

# Enable and start the services:
sudo systemctl enable apache2
sudo systemctl enable mariadb
sudo systemctl start apache2
sudo systemctl start mariadb

# Clone the repository into the Apache web directory:
cd /var/www/html
sudo git clone https://github.com/YOUR_USERNAME/bookdb.git

# Database Setup
Open the MariaDB shell:
sudo mysql
# Create the database:
CREATE DATABASE bookdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
Create the database user (replace <yourusername> and <yourpassword> with whatever you want: 
CREATE USER '<yourusername>'@'localhost' IDENTIFIED BY '<yourpassword>';
GRANT ALL PRIVILEGES ON bookdb.* TO 'bookdb'@'localhost';
FLUSH PRIVILEGES;
# Select database:
USE bookdb;
# Create tables:
Books:
CREATE TABLE buecher (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    autor VARCHAR(255) NOT NULL,
    titel VARCHAR(255) NOT NULL,
    reihe VARCHAR(255) NULL,
    teil_der_reihe INT NULL,
    erscheinungsjahr INT NULL
);
Genres:
CREATE TABLE genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);
Book-Genre relation:
CREATE TABLE buch_genres (
    buch_id INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (buch_id, genre_id),
    FOREIGN KEY (buch_id) REFERENCES buecher(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);

# Edit db_connect.php:
Change the variables set under Database configuration. Replace $dbUser with the username you stated while creating the database and $dbPassword with the password you stated while creating the database.
# Adjust folder permissions:
sudo chown -R <yourusername>:www-data /var/www/html/bookdb
sudo find /var/www/html/bookdb -type d -exec chmod 775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
Optional (recommended):
Enable group inheritance so new files automatically belong to the www-data group:
sudo chmod g+s /var/www/html/bookdb
