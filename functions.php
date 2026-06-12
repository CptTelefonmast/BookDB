<?php
/*
|--------------------------------------------------------------------------
| Application helper functions
|--------------------------------------------------------------------------
*/

function getAllowedSorts(): array
{
    return [
        'autor' => 'autor',
        'titel' => 'titel',
        'reihe' => 'reihe',
        'genres' => 'genres',
        'erscheinungsjahr' => 'erscheinungsjahr'
    ];
}

function getAllowedPerPageOptions(): array
{
    return ['10', '25', '50', '100', 'all'];
}

function buildOrderBy(string $sort, string $dir, array $allowedSorts): string
{
    $orderBy = $allowedSorts[$sort] . ' ' . strtoupper($dir);

    if ($sort !== 'autor')
    {
        $orderBy .= ', autor ASC';
    }

    if ($sort !== 'reihe')
    {
        $orderBy .= ', reihe ASC';
    }

    $orderBy .= ', teil_der_reihe ASC';

    if ($sort !== 'titel')
    {
        $orderBy .= ', titel ASC';
    }

    if ($sort !== 'genres')
    {
        $orderBy .= ', genres ASC';
    }

    return $orderBy;
}

function buildSortLink(
    string $column,
    string $currentSort,
    string $currentDir,
    string $q,
    string $perPage,
    bool $onlyLent = false
): string
{
    $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';

    $params = [
        'sort' => $column,
        'dir' => $newDir,
        'per_page' => $perPage
    ];

    if ($q !== '')
    {
        $params['q'] = $q;
    }

    if ($onlyLent)
    {
        $params['lent'] = '1';
    }

    return 'index.php?' . http_build_query($params);
}

function buildPageLink(
    int $page,
    string $sort,
    string $dir,
    string $q,
    string $perPage,
    bool $onlyLent = false
): string
{
    $params = [
        'page' => $page,
        'sort' => $sort,
        'dir' => $dir,
        'per_page' => $perPage
    ];

    if ($q !== '')
    {
        $params['q'] = $q;
    }

    if ($onlyLent)
    {
        $params['lent'] = '1';
    }

    return 'index.php?' . http_build_query($params);
}

function sortIndicator(string $column, string $currentSort, string $currentDir): string
{
    if ($column !== $currentSort)
    {
        return '<span class="sort-indicator inactive">↕</span>';
    }

    if ($currentDir === 'asc')
    {
        return '<span class="sort-indicator active">↑</span>';
    }

    return '<span class="sort-indicator active">↓</span>';
}

function normalizeCaseInsensitiveKey(string $value): string
{
    $value = trim($value);

    if (function_exists('mb_strtolower'))
    {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function normalizeGenreKey(string $value): string
{
    return normalizeCaseInsensitiveKey($value);
}

function getAvailableRegale(mysqli $mysqli): array
{
    $regale = [];

    $sql = "
        SELECT DISTINCT regal
        FROM buch_standorte
        WHERE regal IS NOT NULL
          AND TRIM(regal) <> ''
        ORDER BY regal ASC
    ";

    $result = $mysqli->query($sql);

    while ($row = $result->fetch_assoc())
    {
        $regale[] = (string)$row['regal'];
    }

    $result->free();

    return $regale;
}

function getAvailableFaecherMap(mysqli $mysqli): array
{
    $faecherMap = [];

    $sql = "
        SELECT DISTINCT regal, regalfach
        FROM buch_standorte
        WHERE regal IS NOT NULL
          AND TRIM(regal) <> ''
          AND regalfach IS NOT NULL
          AND TRIM(regalfach) <> ''
        ORDER BY regal ASC, regalfach ASC
    ";

    $result = $mysqli->query($sql);

    while ($row = $result->fetch_assoc())
    {
        $regal = trim((string)$row['regal']);
        $fach = trim((string)$row['regalfach']);

        if ($regal === '' || $fach === '')
        {
            continue;
        }

        if (!isset($faecherMap[$regal]))
        {
            $faecherMap[$regal] = [];
        }

        $faecherMap[$regal][] = $fach;
    }

    $result->free();

    foreach ($faecherMap as $regal => $faecher)
    {
        $faecherMap[$regal] = array_values(array_unique($faecher));
    }

    return $faecherMap;
}

function getAvailableSchuber(mysqli $mysqli): array
{
    $schuber = [];

    $sql = "
        SELECT DISTINCT schuber
        FROM buch_standorte
        WHERE ist_im_schuber = 1
          AND schuber IS NOT NULL
          AND TRIM(schuber) <> ''
        ORDER BY schuber ASC
    ";

    $result = $mysqli->query($sql);

    while ($row = $result->fetch_assoc())
    {
        $schuber[] = (string)$row['schuber'];
    }

    $result->free();

    return $schuber;
}

function getBookFormReferenceData(mysqli $mysqli): array
{
    $availableGenres = getAllGenres($mysqli);

    return [
        'availableGenres' => $availableGenres,
        'validGenreIds' => array_map(
            static function (array $genre): string
            {
                return (string)$genre['id'];
            },
            $availableGenres
        ),
        'availableRegale' => getAvailableRegale($mysqli),
        'availableFaecherMap' => getAvailableFaecherMap($mysqli),
        'availableSchuber' => getAvailableSchuber($mysqli)
    ];
}

function getWishlistFormReferenceData(mysqli $mysqli): array
{
    $availableGenres = getAllGenres($mysqli);

    return [
        'availableGenres' => $availableGenres,
        'validGenreIds' => array_map(
            static function (array $genre): string
            {
                return (string)$genre['id'];
            },
            $availableGenres
        )
    ];
}

function normalizeLocationPayload(
    ?string $regal,
    ?string $regalfach,
    bool $istImSchuber,
    ?string $schuber
): array
{
    $regal = trim((string)$regal);
    $regalfach = trim((string)$regalfach);
    $schuber = trim((string)$schuber);

    if ($regal === '')
    {
        $regal = null;
    }

    if ($regalfach === '')
    {
        $regalfach = null;
    }

    if ($schuber === '')
    {
        $schuber = null;
    }

    if ($schuber === null)
    {
        $istImSchuber = false;
    }

    return [
        'regal' => $regal,
        'regalfach' => $regalfach,
        'ist_im_schuber' => $istImSchuber,
        'schuber' => $schuber
    ];
}

function renderGenreItem(array $availableGenres, string $selectedValue, string $newGenreValue, int $index): void
{
    ?>
    <div class="genre-item" data-index="<?php echo $index; ?>">

        <div class="form-field">
            <label for="genre_selection_<?php echo $index; ?>">
                Genre <?php echo $index + 1; ?>
            </label>

            <select
                id="genre_selection_<?php echo $index; ?>"
                name="genre_selections[]"
                class="genre-selection"
            >
                <option value="">Please select</option>

                <?php foreach ($availableGenres as $availableGenre): ?>
                    <option
                        value="<?php echo (int)$availableGenre['id']; ?>"
                        <?php echo $selectedValue === (string)$availableGenre['id'] ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($availableGenre['name']); ?>
                    </option>
                <?php endforeach; ?>

                <option value="__new__" <?php echo $selectedValue === '__new__' ? 'selected' : ''; ?>>
                    Add new genre...
                </option>
            </select>
        </div>

        <div
            class="form-field genre-new-field"
            style="<?php echo $selectedValue === '__new__' ? '' : 'display:none;'; ?>"
        >
            <label for="new_genre_value_<?php echo $index; ?>">
                New genre <?php echo $index + 1; ?>
            </label>

            <input
                type="text"
                id="new_genre_value_<?php echo $index; ?>"
                name="new_genre_values[]"
                class="new-genre-value"
                value="<?php echo htmlspecialchars($newGenreValue); ?>"
            >
        </div>

    </div>
    <?php
}

function renderBookFormInitScript(array $availableGenres, array $availableFaecherMap, string $regalfachSelection): void
{
    ?>
    <script>
    window.BookDBBookFormConfig = {
        availableGenres: <?php echo json_encode($availableGenres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        availableFaecherMap: <?php echo json_encode($availableFaecherMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        initialRegalfachSelection: <?php echo json_encode($regalfachSelection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };
    </script>
    <?php
}

function renderWishlistFormInitScript(array $availableGenres): void
{
    ?>
    <script>
    window.BookDBWishlistFormConfig = {
        availableGenres: <?php echo json_encode($availableGenres, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };
    </script>
    <?php
}

function renderDatabaseErrorPage(mysqli_sql_exception $e): void
{
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BookDB – Error</title>
</head>
<body>

<h1>Database connection failed</h1>

<p><?php echo htmlspecialchars($e->getMessage()); ?></p>

</body>
</html>
<?php
}

function renderQueryErrorPage(mysqli_sql_exception $e): void
{
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BookDB – Error</title>
</head>
<body>

<h1>Database query failed</h1>

<p><?php echo htmlspecialchars($e->getMessage()); ?></p>

</body>
</html>
<?php
}