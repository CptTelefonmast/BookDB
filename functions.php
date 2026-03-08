<?php
/*
|--------------------------------------------------------------------------
| Application helper functions
|--------------------------------------------------------------------------
|
| Contains general helper utilities used across the application:
| - allowed sort fields
| - ORDER BY generation
| - sorting links
| - sort indicators
| - error pages
|
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

function buildSortLink(string $column, string $currentSort, string $currentDir, string $q, string $perPage): string
{
    $newDir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';

    $params =
    [
        'sort' => $column,
        'dir' => $newDir,
        'per_page' => $perPage
    ];

    if ($q !== '')
    {
        $params['q'] = $q;
    }

    return 'index.php?' . http_build_query($params);
}

function buildPageLink(int $page, string $sort, string $dir, string $q, string $perPage): string
{
    $params =
    [
        'page' => $page,
        'sort' => $sort,
        'dir' => $dir,
        'per_page' => $perPage
    ];

    if ($q !== '')
    {
        $params['q'] = $q;
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