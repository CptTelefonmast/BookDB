<?php

/*
|--------------------------------------------------------------------------
| Data access for books, genres and locations
|--------------------------------------------------------------------------
|
| This file contains the logic for loading, creating, updating and
| deleting books, including genre assignments and location history.
|
*/

function bindStatementParams(mysqli_stmt $stmt, string $types, array $values): void
{
    if ($types === '' || empty($values))
    {
        return;
    }

    $references = [];

    foreach ($values as $index => $value)
    {
        $references[$index] = &$values[$index];
    }

    $stmt->bind_param($types, ...$references);
}

function buildBookSearchCondition(string $q): array
{
    if ($q === '')
    {
        return [
            'sql' => '',
            'types' => '',
            'params' => []
        ];
    }

    return [
        'sql' => "
            (
                b.autor LIKE CONCAT('%', ?, '%')
                OR b.titel LIKE CONCAT('%', ?, '%')
                OR b.reihe LIKE CONCAT('%', ?, '%')
                OR CAST(b.erscheinungsjahr AS CHAR) LIKE CONCAT('%', ?, '%')
                OR b.gekauft_bei LIKE CONCAT('%', ?, '%')
                OR g.name LIKE CONCAT('%', ?, '%')
                OR a_offen.person LIKE CONCAT('%', ?, '%')
                OR bs_offen.regal LIKE CONCAT('%', ?, '%')
                OR bs_offen.regalfach LIKE CONCAT('%', ?, '%')
                OR bs_offen.schuber LIKE CONCAT('%', ?, '%')
            )
        ",
        'types' => 'ssssssssss',
        'params' => [$q, $q, $q, $q, $q, $q, $q, $q, $q, $q]
    ];
}

function buildBookWhereClause(string $q, bool $onlyLent): array
{
    $conditions = [];
    $types = '';
    $params = [];

    if ($onlyLent)
    {
        $conditions[] = 'a_offen.id IS NOT NULL';
    }

    $search = buildBookSearchCondition($q);

    if ($search['sql'] !== '')
    {
        $conditions[] = $search['sql'];
        $types .= $search['types'];
        $params = array_merge($params, $search['params']);
    }

    if (empty($conditions))
    {
        return [
            'sql' => '',
            'types' => '',
            'params' => []
        ];
    }

    return [
        'sql' => 'WHERE ' . implode(' AND ', $conditions),
        'types' => $types,
        'params' => $params
    ];
}

function countBooks(mysqli $mysqli, string $q, bool $onlyLent = false): int
{
    $where = buildBookWhereClause($q, $onlyLent);

    $sql = "
        SELECT COUNT(DISTINCT b.id) AS anzahl
        FROM buecher b
        LEFT JOIN buch_genres bg
            ON bg.buch_id = b.id
        LEFT JOIN genres g
            ON g.id = bg.genre_id
        LEFT JOIN ausleihen a_offen
            ON a_offen.buch_id = b.id
           AND a_offen.zurueckgegeben_am IS NULL
        LEFT JOIN buch_standorte bs_offen
            ON bs_offen.buch_id = b.id
           AND bs_offen.standort_bis IS NULL
        {$where['sql']}
    ";

    if ($where['types'] === '')
    {
        $result = $mysqli->query($sql);
        $row = $result->fetch_assoc();
        $result->free();

        return (int)($row['anzahl'] ?? 0);
    }

    $stmt = $mysqli->prepare($sql);
    bindStatementParams($stmt, $where['types'], $where['params']);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return (int)($row['anzahl'] ?? 0);
}

function getBooks(
    mysqli $mysqli,
    string $q,
    string $orderBy,
    ?int $limit,
    int $offset,
    bool $onlyLent = false
): mysqli_result
{
    $where = buildBookWhereClause($q, $onlyLent);

    $sql = "
        SELECT
            b.id,
            b.autor,
            b.titel,
            b.reihe,
            b.teil_der_reihe,
            b.erscheinungsjahr,
            b.gekauft_bei,
            COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres,
            MAX(a_offen.person) AS verliehen_an,
            MAX(a_offen.verliehen_am) AS verliehen_am,
            MAX(bs_offen.regal) AS regal,
            MAX(bs_offen.regalfach) AS regalfach,
            MAX(bs_offen.ist_im_schuber) AS ist_im_schuber,
            MAX(bs_offen.schuber) AS schuber,
            MAX(bs_offen.standort_seit) AS standort_seit
        FROM buecher b
        LEFT JOIN buch_genres bg
            ON bg.buch_id = b.id
        LEFT JOIN genres g
            ON g.id = bg.genre_id
        LEFT JOIN ausleihen a_offen
            ON a_offen.buch_id = b.id
           AND a_offen.zurueckgegeben_am IS NULL
        LEFT JOIN buch_standorte bs_offen
            ON bs_offen.buch_id = b.id
           AND bs_offen.standort_bis IS NULL
        {$where['sql']}
        GROUP BY
            b.id,
            b.autor,
            b.titel,
            b.reihe,
            b.teil_der_reihe,
            b.erscheinungsjahr,
            b.gekauft_bei
        ORDER BY {$orderBy}
    ";

    $types = $where['types'];
    $params = $where['params'];

    if ($limit !== null)
    {
        $sql .= "
            LIMIT ? OFFSET ?
        ";

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
    }

    if ($types === '')
    {
        return $mysqli->query($sql);
    }

    $stmt = $mysqli->prepare($sql);
    bindStatementParams($stmt, $types, $params);
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
            erscheinungsjahr,
            gekauft_bei
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

function getAllSchubers(mysqli $mysqli): array
{
    $schubers = [];

    $sql = "
        SELECT DISTINCT schuber
        FROM buch_standorte
        WHERE schuber IS NOT NULL
          AND TRIM(schuber) <> ''
        ORDER BY schuber ASC
    ";

    $result = $mysqli->query($sql);

    while ($row = $result->fetch_assoc())
    {
        $schubers[] = (string)$row['schuber'];
    }

    $result->free();

    return $schubers;
}

function getCurrentLocationByBookId(mysqli $mysqli, int $bookId): ?array
{
    $sql = "
        SELECT
            id,
            buch_id,
            regal,
            regalfach,
            ist_im_schuber,
            schuber,
            standort_seit,
            standort_bis,
            erstellt_am
        FROM buch_standorte
        WHERE buch_id = ?
          AND standort_bis IS NULL
        ORDER BY standort_seit DESC, id DESC
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $bookId);
    $stmt->execute();

    $result = $stmt->get_result();
    $location = $result->fetch_assoc();

    $stmt->close();

    return $location ?: null;
}

function getLocationHistoryByBookId(mysqli $mysqli, int $bookId): array
{
    $history = [];

    $sql = "
        SELECT
            id,
            buch_id,
            regal,
            regalfach,
            ist_im_schuber,
            schuber,
            standort_seit,
            standort_bis,
            erstellt_am
        FROM buch_standorte
        WHERE buch_id = ?
        ORDER BY standort_seit DESC, id DESC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $bookId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc())
    {
        $history[] = $row;
    }

    $stmt->close();

    return $history;
}

function normalizeLocationValue(?string $value): ?string
{
    if ($value === null)
    {
        return null;
    }

    $value = trim($value);

    return $value !== '' ? $value : null;
}

function hasMeaningfulLocationData(?string $regal, ?string $regalfach, bool $istImSchuber, ?string $schuber): bool
{
    if ($regal !== null)
    {
        return true;
    }

    if ($regalfach !== null)
    {
        return true;
    }

    if ($istImSchuber && $schuber !== null)
    {
        return true;
    }

    return false;
}

function locationsAreEqual(?array $currentLocation, ?string $regal, ?string $regalfach, bool $istImSchuber, ?string $schuber): bool
{
    if ($currentLocation === null)
    {
        return false;
    }

    $currentRegal = normalizeLocationValue((string)($currentLocation['regal'] ?? ''));
    $currentRegalfach = normalizeLocationValue((string)($currentLocation['regalfach'] ?? ''));
    $currentIstImSchuber = (int)($currentLocation['ist_im_schuber'] ?? 0) === 1;
    $currentSchuber = normalizeLocationValue((string)($currentLocation['schuber'] ?? ''));

    return
        $currentRegal === $regal
        && $currentRegalfach === $regalfach
        && $currentIstImSchuber === $istImSchuber
        && $currentSchuber === $schuber;
}

function syncBookCurrentLocation(
    mysqli $mysqli,
    int $bookId,
    ?string $regal,
    ?string $regalfach,
    bool $istImSchuber,
    ?string $schuber,
    ?string $standortSeit
): void
{
    $regal = normalizeLocationValue($regal);
    $regalfach = normalizeLocationValue($regalfach);
    $schuber = normalizeLocationValue($schuber);
    $standortSeit = normalizeLocationValue($standortSeit);

    if (!$istImSchuber)
    {
        $schuber = null;
    }

    if ($standortSeit === null)
    {
        $standortSeit = date('Y-m-d');
    }

    $hasLocationData = hasMeaningfulLocationData($regal, $regalfach, $istImSchuber, $schuber);
    $currentLocation = getCurrentLocationByBookId($mysqli, $bookId);

    if (!$hasLocationData)
    {
        if ($currentLocation !== null)
        {
            $closeSql = "
                UPDATE buch_standorte
                SET standort_bis = ?
                WHERE id = ?
                  AND standort_bis IS NULL
                LIMIT 1
            ";

            $closeStmt = $mysqli->prepare($closeSql);
            $currentLocationId = (int)$currentLocation['id'];

            $closeStmt->bind_param("si", $standortSeit, $currentLocationId);
            $closeStmt->execute();
            $closeStmt->close();
        }

        return;
    }

    if (locationsAreEqual($currentLocation, $regal, $regalfach, $istImSchuber, $schuber))
    {
        return;
    }

    if ($currentLocation !== null)
    {
        $closeSql = "
            UPDATE buch_standorte
            SET standort_bis = ?
            WHERE id = ?
              AND standort_bis IS NULL
            LIMIT 1
        ";

        $closeStmt = $mysqli->prepare($closeSql);
        $currentLocationId = (int)$currentLocation['id'];

        $closeStmt->bind_param("si", $standortSeit, $currentLocationId);
        $closeStmt->execute();
        $closeStmt->close();
    }

    $insertSql = "
        INSERT INTO buch_standorte
        (
            buch_id,
            regal,
            regalfach,
            ist_im_schuber,
            schuber,
            standort_seit
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";

    $insertStmt = $mysqli->prepare($insertSql);
    $istImSchuberInt = $istImSchuber ? 1 : 0;

    $insertStmt->bind_param(
        "ississ",
        $bookId,
        $regal,
        $regalfach,
        $istImSchuberInt,
        $schuber,
        $standortSeit
    );
    $insertStmt->execute();
    $insertStmt->close();
}

function createBook(
    mysqli $mysqli,
    string $autor,
    string $titel,
    ?string $reihe,
    ?int $teilDerReihe,
    ?int $erscheinungsjahr,
    ?string $gekauftBei,
    array $genreIds,
    string $newGenresInput,
    bool $gelesen,
    ?string $regal,
    ?string $regalfach,
    bool $istImSchuber,
    ?string $schuber,
    ?string $standortSeit
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
                erscheinungsjahr,
                gelesen,
                gekauft_bei
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";

        $stmt = $mysqli->prepare($sql);
        $gelesenInt = $gelesen ? 1 : 0;

        $stmt->bind_param(
            "sssiiis",
            $autor,
            $titel,
            $reihe,
            $teilDerReihe,
            $erscheinungsjahr,
            $gelesenInt,
            $gekauftBei
        );
        $stmt->execute();

        $bookId = (int)$mysqli->insert_id;

        $stmt->close();

        syncBookGenres($mysqli, $bookId, $genreIds, $newGenresInput);

        syncBookCurrentLocation(
            $mysqli,
            $bookId,
            $regal,
            $regalfach,
            $istImSchuber,
            $schuber,
            $standortSeit
        );

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
    ?string $gekauftBei,
    array $genreIds,
    string $newGenresInput,
    ?string $regal,
    ?string $regalfach,
    bool $istImSchuber,
    ?string $schuber,
    ?string $standortSeit
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
                erscheinungsjahr = ?,
                gekauft_bei = ?
            WHERE id = ?
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            "sssiisi",
            $autor,
            $titel,
            $reihe,
            $teilDerReihe,
            $erscheinungsjahr,
            $gekauftBei,
            $id
        );
        $stmt->execute();
        $stmt->close();

        syncBookGenres($mysqli, $id, $genreIds, $newGenresInput);

        syncBookCurrentLocation(
            $mysqli,
            $id,
            $regal,
            $regalfach,
            $istImSchuber,
            $schuber,
            $standortSeit
        );

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
    $mysqli->begin_transaction();

    try
    {
        $deleteGenresSql = "
            DELETE FROM buch_genres
            WHERE buch_id = ?
        ";

        $deleteGenresStmt = $mysqli->prepare($deleteGenresSql);
        $deleteGenresStmt->bind_param("i", $id);
        $deleteGenresStmt->execute();
        $deleteGenresStmt->close();

        $deleteLoansSql = "
            DELETE FROM ausleihen
            WHERE buch_id = ?
        ";

        $deleteLoansStmt = $mysqli->prepare($deleteLoansSql);
        $deleteLoansStmt->bind_param("i", $id);
        $deleteLoansStmt->execute();
        $deleteLoansStmt->close();

        $deleteLocationsSql = "
            DELETE FROM buch_standorte
            WHERE buch_id = ?
        ";

        $deleteLocationsStmt = $mysqli->prepare($deleteLocationsSql);
        $deleteLocationsStmt->bind_param("i", $id);
        $deleteLocationsStmt->execute();
        $deleteLocationsStmt->close();

        $deleteBookSql = "
            DELETE FROM buecher
            WHERE id = ?
        ";

        $deleteBookStmt = $mysqli->prepare($deleteBookSql);
        $deleteBookStmt->bind_param("i", $id);
        $deleteBookStmt->execute();
        $deleteBookStmt->close();

        $mysqli->commit();
    }
    catch (Throwable $e)
    {
        $mysqli->rollback();
        throw $e;
    }
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

function countBooksBySchuber(mysqli $mysqli, string $schuber): int
{
    $sql = "
        SELECT COUNT(DISTINCT b.id) AS anzahl
        FROM buecher b
        INNER JOIN buch_standorte bs
            ON bs.buch_id = b.id
           AND bs.standort_bis IS NULL
        WHERE bs.schuber = ?
          AND bs.ist_im_schuber = 1
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $schuber);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return (int)($row['anzahl'] ?? 0);
}

function getBooksBySchuber(mysqli $mysqli, string $schuber): array
{
    $books = [];

    $sql = "
        SELECT
            b.id,
            b.autor,
            b.titel,
            b.reihe,
            b.teil_der_reihe,
            b.erscheinungsjahr,
            b.gekauft_bei,
            COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres,
            bs.regal,
            bs.regalfach,
            bs.schuber,
            bs.standort_seit
        FROM buecher b
        INNER JOIN buch_standorte bs
            ON bs.buch_id = b.id
           AND bs.standort_bis IS NULL
        LEFT JOIN buch_genres bg
            ON bg.buch_id = b.id
        LEFT JOIN genres g
            ON g.id = bg.genre_id
        WHERE bs.schuber = ?
          AND bs.ist_im_schuber = 1
        GROUP BY
            b.id,
            b.autor,
            b.titel,
            b.reihe,
            b.teil_der_reihe,
            b.erscheinungsjahr,
            b.gekauft_bei,
            bs.regal,
            bs.regalfach,
            bs.schuber,
            bs.standort_seit
        ORDER BY
            b.autor ASC,
            b.reihe ASC,
            b.teil_der_reihe ASC,
            b.titel ASC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $schuber);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc())
    {
        $books[] = $row;
    }

    $stmt->close();

    return $books;
}
/*
|--------------------------------------------------------------------------
| Wish list functions
|--------------------------------------------------------------------------
|
| Data access for wish list entries
|
*/

function getWishlistItems(mysqli $mysqli): array
{
    $items = [];

    $sql = "
        SELECT
            w.id,
            w.autor,
            w.titel,
            w.reihe,
            w.teil_der_reihe,
            w.erscheinungsjahr,
            COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres
        FROM wishlist w
        LEFT JOIN wishlist_genres wg
            ON wg.wishlist_id = w.id
        LEFT JOIN genres g
            ON g.id = wg.genre_id
        GROUP BY
            w.id,
            w.autor,
            w.titel,
            w.reihe,
            w.teil_der_reihe,
            w.erscheinungsjahr
        ORDER BY
            w.autor ASC,
            w.titel ASC
    ";

    $result = $mysqli->query($sql);

    while ($row = $result->fetch_assoc())
    {
        $items[] = $row;
    }

    $result->free();

    return $items;
}

function getWishlistItemById(mysqli $mysqli, int $id): ?array
{
    $sql = "
        SELECT
            id,
            autor,
            titel,
            reihe,
            teil_der_reihe,
            erscheinungsjahr
        FROM wishlist
        WHERE id = ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $item = $result->fetch_assoc();

    $stmt->close();

    if (!$item)
    {
        return null;
    }

    $genres = getGenresByWishlistId($mysqli, $id);

    $item['genres'] = $genres;
    $item['genre_ids'] = array_map(
        static function (array $genre): int
        {
            return (int)$genre['id'];
        },
        $genres
    );

    return $item;
}

function getGenresByWishlistId(mysqli $mysqli, int $wishlistId): array
{
    $genres = [];

    $sql = "
        SELECT g.id, g.name
        FROM wishlist_genres wg
        INNER JOIN genres g
            ON g.id = wg.genre_id
        WHERE wg.wishlist_id = ?
        ORDER BY g.name ASC
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $wishlistId);
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

function createWishlistItem(
    mysqli $mysqli,
    string $autor,
    string $titel,
    ?string $reihe,
    ?int $teilDerReihe,
    ?int $erscheinungsjahr,
    array $genreIds
): int
{
    $mysqli->begin_transaction();

    try
    {
        $sql = "
            INSERT INTO wishlist
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

        $wishlistId = (int)$mysqli->insert_id;

        $stmt->close();

        syncWishlistGenres($mysqli, $wishlistId, $genreIds);

        $mysqli->commit();

        return $wishlistId;
    }
    catch (Throwable $e)
    {
        $mysqli->rollback();
        throw $e;
    }
}

function updateWishlistItem(
    mysqli $mysqli,
    int $id,
    string $autor,
    string $titel,
    ?string $reihe,
    ?int $teilDerReihe,
    ?int $erscheinungsjahr,
    array $genreIds
): void
{
    $mysqli->begin_transaction();

    try
    {
        $sql = "
            UPDATE wishlist
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

        syncWishlistGenres($mysqli, $id, $genreIds);

        $mysqli->commit();
    }
    catch (Throwable $e)
    {
        $mysqli->rollback();
        throw $e;
    }
}

function deleteWishlistItem(mysqli $mysqli, int $id): void
{
    $mysqli->begin_transaction();

    try
    {
        $deleteGenresSql = "
            DELETE FROM wishlist_genres
            WHERE wishlist_id = ?
        ";

        $stmt = $mysqli->prepare($deleteGenresSql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $deleteSql = "
            DELETE FROM wishlist
            WHERE id = ?
        ";

        $stmt = $mysqli->prepare($deleteSql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
    }
    catch (Throwable $e)
    {
        $mysqli->rollback();
        throw $e;
    }
}

function syncWishlistGenres(mysqli $mysqli, int $wishlistId, array $genreIds): void
{
    $deleteSql = "
        DELETE FROM wishlist_genres
        WHERE wishlist_id = ?
    ";

    $stmt = $mysqli->prepare($deleteSql);
    $stmt->bind_param("i", $wishlistId);
    $stmt->execute();
    $stmt->close();

    if (empty($genreIds))
    {
        return;
    }

    $insertSql = "
        INSERT INTO wishlist_genres
        (
            wishlist_id,
            genre_id
        )
        VALUES
        (
            ?,
            ?
        )
    ";

    $stmt = $mysqli->prepare($insertSql);

    foreach ($genreIds as $genreId)
    {
        $stmt->bind_param("ii", $wishlistId, $genreId);
        $stmt->execute();
    }

    $stmt->close();
}
function countWishlistItems(mysqli $mysqli): int
{
    $sql = "
        SELECT COUNT(*) AS anzahl
        FROM wishlist
    ";

    $result = $mysqli->query($sql);

    if (!$result)
    {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return (int)($row['anzahl'] ?? 0);
}
