<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/books.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'autor';
$dir = isset($_GET['dir']) ? strtolower($_GET['dir']) : 'asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? strtolower(trim((string)$_GET['per_page'])) : '25';
$added = isset($_GET['added']) && $_GET['added'] === '1';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';

$allowedSorts = getAllowedSorts();
$allowedPerPageOptions = getAllowedPerPageOptions();

if (!array_key_exists($sort, $allowedSorts))
{
    $sort = 'autor';
}

if ($dir !== 'asc' && $dir !== 'desc')
{
    $dir = 'asc';
}

if (!in_array($perPage, $allowedPerPageOptions, true))
{
    $perPage = '25';
}

if ($page < 1)
{
    $page = 1;
}

$orderBy = buildOrderBy($sort, $dir, $allowedSorts);

try
{
    $mysqli = getDatabaseConnection();
}
catch (mysqli_sql_exception $e)
{
    renderDatabaseErrorPage($e);
    exit;
}

try
{
    $gesamtanzahl = countBooks($mysqli, $q);

    if ($perPage === 'all')
    {
        $limit = null;
        $gesamtseiten = 1;
        $page = 1;
        $offset = 0;
    }
    else
    {
        $limit = (int)$perPage;
        $gesamtseiten = max(1, (int)ceil($gesamtanzahl / $limit));

        if ($page > $gesamtseiten)
        {
            $page = $gesamtseiten;
        }

        $offset = ($page - 1) * $limit;
    }

    $result = getBooks($mysqli, $q, $orderBy, $limit, $offset);
    $anzahl = $gesamtanzahl;
}
catch (mysqli_sql_exception $e)
{
    renderQueryErrorPage($e);
    exit;
}

require __DIR__ . '/view.php';

if ($result instanceof mysqli_result)
{
    $result->free();
}

$mysqli->close();
?>