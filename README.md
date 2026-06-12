# BookDB

> ⚠ **Security Notice**  
> This project currently does **not include encryption or user authentication**.  
> Anyone who can access the application can add, edit or delete books.  
>
> The project was originally built for use on a Raspberry Pi in a private network.  
> If you plan to expose it to the internet, you should add authentication and HTTPS.
> 
> Yes, I know it's coded badly. I'm a system integrator by trade, not a programmer :)

## Overview

I used to have an excel sheet to keep track of my book collection and to prevent double purchases. It started to get annoying to upload the updated sheet to the cloud whenever I added a book or edited anything. So here is my solution.

BookDB is a PHP web application for managing a personal book collection.

It allows storing books with authors, series information, publication year and multiple genres.  
The interface supports searching, sorting and pagination and works on both desktop and mobile devices.

---

## Features

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
- Responsive layout (desktop and mobile)
- Light / dark theme

---

## Technology Stack

- PHP 8
- MariaDB / MySQL
- Apache
- Vanilla JavaScript
- CSS (no framework)

---

# Installation

## Install required packages

```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql
```

Enable and start the services:

```bash
sudo systemctl enable apache2
sudo systemctl enable mariadb

sudo systemctl start apache2
sudo systemctl start mariadb
```

---

## Clone the repository

Clone the repository into the Apache web directory:

```bash
cd /var/www/html
sudo git clone https://github.com/YOUR_USERNAME/bookdb.git
```

Your project directory should now be:

```
/var/www/html/bookdb
```

---

# Database Setup

Open the MariaDB shell:

```bash
sudo mysql
```

## Create the database

```sql
CREATE DATABASE bookdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Create a database user

Replace `<yourusername>` and `<yourpassword>` with credentials of your choice.

```sql
CREATE USER '<yourusername>'@'localhost' IDENTIFIED BY '<yourpassword>';
GRANT ALL PRIVILEGES ON bookdb.* TO '<yourusername>'@'localhost';
FLUSH PRIVILEGES;
```

Select the database:

```sql
USE bookdb;
```

---

# Create Tables

## Books

```sql
CREATE TABLE buecher (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    autor VARCHAR(255) NOT NULL,
    titel VARCHAR(255) NOT NULL,
    reihe VARCHAR(255) NULL,
    teil_der_reihe INT NULL,
    erscheinungsjahr INT NULL
);
```

## Genres

```sql
CREATE TABLE genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);
```

## Book-Genre relation

```sql
CREATE TABLE buch_genres (
    buch_id INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (buch_id, genre_id),
    FOREIGN KEY (buch_id) REFERENCES buecher(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
);
```

---

# Database Configuration

Edit the file:

```
db_connect.php
```

Adjust the variables in the **Database configuration** section.

Replace:

- `$dbUser` with the username you created
- `$dbPassword` with the password you created

---

# Adjust Folder Permissions

Allow Apache and your user to access the project directory.

Replace `<yourusername>` with the user account you use to upload files.

```bash
sudo chown -R <yourusername>:www-data /var/www/html/bookdb
sudo find /var/www/html/bookdb -type d -exec chmod 775 {} \;
sudo find /var/www/html/bookdb -type f -exec chmod 664 {} \;
```

### Optional (recommended)

Enable group inheritance so new files automatically belong to the `www-data` group:

```bash
sudo chmod g+s /var/www/html/bookdb
```

---

# Running the Application

Open the application in your browser:

```
http://localhost/bookdb
```

or on a server:

```
http://your-server/bookdb
```

---

# License

This project is licensed under the **GNU General Public License (GPL)**.
