<?php

/*
|--------------------------------------------------------------------------
| Book details page
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$updated = isset($_GET['updated']) && $_GET['updated'] === '1';

if ($id <= 0)
{
    die('Invalid book ID.');
}

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
    $book = getBookById($mysqli, $id);
}
catch (mysqli_sql_exception $e)
{
    renderQueryErrorPage($e);
    exit;
}

$mysqli->close();

if ($book === null)
{
    $pageTitle = 'Book not found';
    require __DIR__ . '/header.php';
?>
<section class="hero">

<button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color theme">
🌙
</button>

<div class="eyebrow">BookDB</div>

<h1>Book not found</h1>

<p class="subtitle">
No entry exists for the given ID.
</p>

</section>

<section class="details-card">

<div class="empty">
The requested book could not be found.
</div>

<div class="form-actions">
<a class="btn btn-secondary" href="index.php">
Back to list
</a>
</div>

</section>
<?php
require __DIR__ . '/footer.php';
exit;
}

$pageTitle = $book['titel'] . ' – Details';
require __DIR__ . '/header.php';
?>

<section class="hero">

<button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color theme">
🌙
</button>

<div class="eyebrow">BookDB</div>

<h1><?php echo htmlspecialchars($book['titel']); ?></h1>

<p class="subtitle">
by <?php echo htmlspecialchars($book['autor']); ?>
</p>

</section>

<?php if ($updated): ?>
<div class="alert alert-success">
Changes saved successfully.
</div>
<?php endif; ?>

<section class="details-card">

<div class="details-grid">

<div class="details-item">
<div class="details-label">Author</div>
<div class="details-value">
<?php echo htmlspecialchars($book['autor']); ?>
</div>
</div>

<div class="details-item">
<div class="details-label">Title</div>
<div class="details-value">
<?php echo htmlspecialchars($book['titel']); ?>
</div>
</div>

<div class="details-item">
<div class="details-label">Series</div>
<div class="details-value">
<?php
$reihe = trim((string)($book['reihe'] ?? ''));
echo $reihe !== '' ? htmlspecialchars($reihe) : '—';
?>
</div>
</div>

<div class="details-item">
<div class="details-label">Series number</div>
<div class="details-value">
<?php
$teil = $book['teil_der_reihe'] ?? null;
echo ($teil !== null && $teil !== '' && (string)$teil !== '0')
? htmlspecialchars((string)$teil)
: '—';
?>
</div>
</div>

<div class="details-item">
<div class="details-label">Genres</div>
<div class="details-value">
<?php
$genresDisplay = trim((string)($book['genres_display'] ?? ''));
echo $genresDisplay !== '' ? htmlspecialchars($genresDisplay) : '—';
?>
</div>
</div>

<div class="details-item">
<div class="details-label">Publication year</div>
<div class="details-value">
<?php
$jahr = (string)($book['erscheinungsjahr'] ?? '');
echo ($jahr !== '' && $jahr !== '0000')
? htmlspecialchars($jahr)
: '—';
?>
</div>
</div>

</div>

<div class="form-actions">

<a class="btn btn-primary" href="edit.php?id=<?php echo (int)$book['id']; ?>">
Edit book
</a>

<a class="btn btn-secondary" href="index.php">
Back to list
</a>

</div>

</section>

<?php require __DIR__ . '/footer.php'; ?>