<?php

/*
|--------------------------------------------------------------------------
| Datenzugriff für Bücher und Genres
|--------------------------------------------------------------------------
|
| Diese Datei enthält die Logik zum Laden, Anlegen, Aktualisieren und
| Löschen von Büchern sowie deren Genre-Zuordnungen.
|
*/

function countBooks(mysqli $mysqli, string $q): int
{
    if ($q !== '')
    {
        $sql = "
            SELECT COUNT(DISTINCT b.id) AS anzahl
            FROM buecher b
            LEFT JOIN buch_genres bg
                ON bg.buch_id = b.id
            LEFT JOIN genres g
                ON g.id = bg.genre_id
            WHERE b.autor LIKE CONCAT('%', ?, '%')
               OR b.titel LIKE CONCAT('%', ?, '%')
               OR b.reihe LIKE CONCAT('%', ?, '%')
               OR g.name LIKE CONCAT('%', ?, '%')
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ssss", $q, $q, $q, $q);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        return (int)($row['anzahl'] ?? 0);
    }

    $sql = "
        SELECT COUNT(*) AS anzahl
        FROM buecher
    ";

    $result = $mysqli->query($sql);
    $row = $result->fetch_assoc();
    $result->free();

    return (int)($row['anzahl'] ?? 0);
}

function getBooks(mysqli $mysqli, string $q, string $orderBy, ?int $limit, int $offset): mysqli_result
{
    if ($q !== '')
    {
        if ($limit === null)
        {
            $sql = "
                SELECT
                    b.id,
                    b.autor,
                    b.titel,
                    b.reihe,
                    b.teil_der_reihe,
                    b.erscheinungsjahr,
                    COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres
                FROM buecher b
                LEFT JOIN buch_genres bg
                    ON bg.buch_id = b.id
                LEFT JOIN genres g
                    ON g.id = bg.genre_id
                WHERE b.autor LIKE CONCAT('%', ?, '%')
                   OR b.titel LIKE CONCAT('%', ?, '%')
                   OR b.reihe LIKE CONCAT('%', ?, '%')
                   OR g.name LIKE CONCAT('%', ?, '%')
                GROUP BY
                    b.id,
                    b.autor,
                    b.titel,
                    b.reihe,
                    b.teil_der_reihe,
                    b.erscheinungsjahr
                ORDER BY $orderBy
            ";

            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ssss", $q, $q, $q, $q);
            $stmt->execute();

            return $stmt->get_result();
        }

        $sql = "
            SELECT
                b.id,
                b.autor,
                b.titel,
                b.reihe,
                b.teil_der_reihe,
                b.erscheinungsjahr,
                COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres
            FROM buecher b
            LEFT JOIN buch_genres bg
                ON bg.buch_id = b.id
            LEFT JOIN genres g
                ON g.id = bg.genre_id
            WHERE b.autor LIKE CONCAT('%', ?, '%')
               OR b.titel LIKE CONCAT('%', ?, '%')
               OR b.reihe LIKE CONCAT('%', ?, '%')
               OR g.name LIKE CONCAT('%', ?, '%')
            GROUP BY
                b.id,
                b.autor,
                b.titel,
                b.reihe,
                b.teil_der_reihe,
                b.erscheinungsjahr
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ssssii", $q, $q, $q, $q, $limit, $offset);
        $stmt->execute();

        return $stmt->get_result();
    }

    if ($limit === null)
    {
        $sql = "
            SELECT
                b.id,
                b.autor,
                b.titel,
                b.reihe,
                b.teil_der_reihe,
                b.erscheinungsjahr,
                COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres
            FROM buecher b
            LEFT JOIN buch_genres bg
                ON bg.buch_id = b.id
            LEFT JOIN genres g
                ON g.id = bg.genre_id
            GROUP BY
                b.id,
                b.autor,
                b.titel,
                b.reihe,
                b.teil_der_reihe,
                b.erscheinungsjahr
            ORDER BY $orderBy
        ";

        return $mysqli->query($sql);
    }

    $sql = "
        SELECT
            b.id,
            b.autor,
            b.titel,
            b.reihe,
            b.teil_der_reihe,
            b.erscheinungsjahr,
            COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres
        FROM buecher b
        LEFT JOIN buch_genres bg
            ON bg.buch_id = b.id
        LEFT JOIN genres g
            ON g.id = bg.genre_id
        GROUP BY
            b.id,
            b.autor,
            b.titel,
            b.reihe,
            b.teil_der_reihe,
            b.erscheinungsjahr
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();

    return $stmt->get_result();
}

function getBookById(mysqli $mysqli, int $id): ?array
{
    $sql = "
        SELECT
            id,
            autor,
            titel,
            reihe,
            teil_der_reihe,
            erscheinungsjahr
        FROM buecher
        WHERE id = ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $book = $result->fetch_assoc();

    $stmt->close();

    if (!$book)
    {
        return null;
    }

    $genres = getGenresByBookId($mysqli, $id);

    $book['genres'] = $genres;
    $book['genre_ids'] = array_map(
        static function (array $genre): int
        {
            return (int)$genre['id'];
        },
        $genres
    );
    $book['genres_display'] = implode(
        ', ',
        array_map(
            static function (array $genre): string
            {
                return (string)$genre['name'];
            },
            $genres
        )
    );

    return $book;
}

function getAllGenres(mysqli $mysqli): array
{
    $genres = [];

    $sql = "
        SELECT id, name
        FROM genres
        ORDER BY name ASC
    ";

    $result = $mysqli->query($sql);

    while ($row = $result->fetch_assoc())
    {
        $genres[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name']
        ];
    }

    $result->free();

    return $genres;
}

function getGenresByBookId(mysqli $mysqli, int $bookId): array
{
    $genres = [];

    $sql = "
        SELECT g.id, g.name
        FROM buch_genres bg
        INNER JOIN genres g
            ON g.id = bg.genre_id
        WHERE bg.buch_id = ?
        ORDER BY g.name ASC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $bookId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc())
    {
        $genres[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name']
        ];
    }

    $stmt->close();

    return $genres;
}

function createBook(
    mysqli $mysqli,
    string $autor,
    string $titel,
    ?string $reihe,
    ?int $teilDerReihe,
    ?int $erscheinungsjahr,
    array $genreIds,
    string $newGenresInput
): int
{
    $mysqli->begin_transaction();

    try
    {
        $sql = "
            INSERT INTO buecher
            (
                autor,
                titel,
                reihe,
                teil_der_reihe,
                erscheinungsjahr
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "sssii",
            $autor,
            $titel,
            $reihe,
            $teilDerReihe,
            $erscheinungsjahr
        );
        $stmt->execute();

        $bookId = (int)$mysqli->insert_id;

        $stmt->close();

        syncBookGenres($mysqli, $bookId, $genreIds, $newGenresInput);

        $mysqli->commit();

        return $bookId;
    }
    catch (Throwable $e)
    {
        $mysqli->rollback();
        throw $e;
    }
}

function updateBook(
    mysqli $mysqli,
    int $id,
    string $autor,
    string $titel,
    ?string $reihe,
    ?int $teilDerReihe,
    ?int $erscheinungsjahr,
    array $genreIds,
    string $newGenresInput
): void
{
    $mysqli->begin_transaction();

    try
    {
        $sql = "
            UPDATE buecher
            SET
                autor = ?,
                titel = ?,
                reihe = ?,
                teil_der_reihe = ?,
                erscheinungsjahr = ?
            WHERE id = ?
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "sssiii",
            $autor,
            $titel,
            $reihe,
            $teilDerReihe,
            $erscheinungsjahr,
            $id
        );
        $stmt->execute();
        $stmt->close();

        syncBookGenres($mysqli, $id, $genreIds, $newGenresInput);

        $mysqli->commit();
    }
    catch (Throwable $e)
    {
        $mysqli->rollback();
        throw $e;
    }
}

function deleteBook(mysqli $mysqli, int $id): void
{
    $sql = "
        DELETE FROM buecher
        WHERE id = ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

function syncBookGenres(mysqli $mysqli, int $bookId, array $genreIds, string $newGenresInput): void
{
    $finalGenreIds = [];

    foreach ($genreIds as $genreId)
    {
        $genreId = (int)$genreId;

        if ($genreId > 0)
        {
            $finalGenreIds[] = $genreId;
        }
    }

    $newGenreNames = parseGenreNamesInput($newGenresInput);
    $newGenreIds = ensureGenresExist($mysqli, $newGenreNames);

    foreach ($newGenreIds as $genreId)
    {
        $finalGenreIds[] = $genreId;
    }

    $finalGenreIds = array_values(array_unique($finalGenreIds));

    $deleteSql = "
        DELETE FROM buch_genres
        WHERE buch_id = ?
    ";

    $deleteStmt = $mysqli->prepare($deleteSql);
    $deleteStmt->bind_param("i", $bookId);
    $deleteStmt->execute();
    $deleteStmt->close();

    if (empty($finalGenreIds))
    {
        return;
    }

    $insertSql = "
        INSERT INTO buch_genres
        (
            buch_id,
            genre_id
        )
        VALUES
        (
            ?,
            ?
        )
    ";

    $insertStmt = $mysqli->prepare($insertSql);

    foreach ($finalGenreIds as $genreId)
    {
        $insertStmt->bind_param("ii", $bookId, $genreId);
        $insertStmt->execute();
    }

    $insertStmt->close();
}

function parseGenreNamesInput(string $input): array
{
    $parts = preg_split('/[\r\n,;]+/', $input) ?: [];
    $genreNames = [];

    foreach ($parts as $part)
    {
        $genreName = trim($part);

        if ($genreName === '')
        {
            continue;
        }

        $genreNames[] = $genreName;
    }

    return array_values(array_unique($genreNames));
}

function ensureGenresExist(mysqli $mysqli, array $genreNames): array
{
    $genreIds = [];

    if (empty($genreNames))
    {
        return $genreIds;
    }

    $selectSql = "
        SELECT id
        FROM genres
        WHERE name = ?
        LIMIT 1
    ";

    $insertSql = "
        INSERT INTO genres
        (
            name
        )
        VALUES
        (
            ?
        )
    ";

    $selectStmt = $mysqli->prepare($selectSql);
    $insertStmt = $mysqli->prepare($insertSql);

    foreach ($genreNames as $genreName)
    {
        $selectStmt->bind_param("s", $genreName);
        $selectStmt->execute();

        $result = $selectStmt->get_result();
        $row = $result->fetch_assoc();

        if ($row)
        {
            $genreIds[] = (int)$row['id'];
            continue;
        }

        $insertStmt->bind_param("s", $genreName);
        $insertStmt->execute();

        $genreIds[] = (int)$mysqli->insert_id;
    }

    $selectStmt->close();
    $insertStmt->close();

    return array_values(array_unique($genreIds));
}