<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$created = isset($_GET['created']) && $_GET['created'] === '1';
$updated = isset($_GET['updated']) && $_GET['updated'] === '1';

if ($id <= 0)
{
    die('Invalid wish list ID.');
}

try
{
    $mysqli = getDatabaseConnection();
    $item = getWishlistItemById($mysqli, $id);
    $mysqli->close();
}
catch (mysqli_sql_exception $e)
{
    if (isset($mysqli) && $mysqli instanceof mysqli)
    {
        $mysqli->close();
    }

    renderQueryErrorPage($e);
    exit;
}

if ($item === null)
{
    $pageTitle = 'Wish list entry not found';
    require __DIR__ . '/header.php';
    ?>
    <section class="hero">

        <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
            🌙
        </button>

        <div class="eyebrow">Wish list</div>

        <h1>Wish list entry not found</h1>

        <p class="subtitle">
            No entry exists for the given ID.
        </p>

    </section>

    <section class="details-card">
        <div class="empty">
            The requested wish list entry could not be found.
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="wishlist.php">Back to wish list</a>
        </div>
    </section>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

$pageTitle = $item['titel'] . ' – Wish list';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
        🌙
    </button>

    <div class="eyebrow">Wish list</div>

    <h1><?php echo htmlspecialchars((string)$item['titel']); ?></h1>

    <p class="subtitle">
        of <?php echo htmlspecialchars((string)$item['autor']); ?>
    </p>

</section>

<?php if ($created): ?>
    <div class="alert alert-success">
        The wish list entry was created successfully.
    </div>
<?php endif; ?>

<?php if ($updated): ?>
    <div class="alert alert-success">
        The wish list entry was saved successfully.
    </div>
<?php endif; ?>

<section class="details-card">

    <div class="details-grid">

        <div class="details-item">
            <div class="details-label">Author</div>
            <div class="details-value"><?php echo htmlspecialchars((string)$item['autor']); ?></div>
        </div>

        <div class="details-item">
            <div class="details-label">Title</div>
            <div class="details-value"><?php echo htmlspecialchars((string)$item['titel']); ?></div>
        </div>

        <div class="details-item">
            <div class="details-label">Series</div>
            <div class="details-value">
                <?php
                $reihe = trim((string)($item['reihe'] ?? ''));
                echo $reihe !== '' ? htmlspecialchars($reihe) : '—';
                ?>
            </div>
        </div>

        <div class="details-item">
            <div class="details-label">Series part</div>
            <div class="details-value">
                <?php
                $teil = $item['teil_der_reihe'] ?? null;
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
                $genres = $item['genres'] ?? [];

                if (!empty($genres))
                {
                    echo htmlspecialchars(implode(', ', array_map(
                        static function (array $genre): string
                        {
                            return (string)$genre['name'];
                        },
                        $genres
                    )));
                }
                else
                {
                    echo '—';
                }
                ?>
            </div>
        </div>

        <div class="details-item">
            <div class="details-label">Publication year</div>
            <div class="details-value">
                <?php
                $jahr = trim((string)($item['erscheinungsjahr'] ?? ''));
                echo ($jahr !== '' && $jahr !== '0000') ? htmlspecialchars($jahr) : '—';
                ?>
            </div>
        </div>

    </div>

    <div class="form-actions">
        <a class="btn btn-primary" href="insert.php?wishlist_id=<?php echo (int)$item['id']; ?>">Add to BookDB</a>
        <a class="btn btn-secondary" href="wishlist_form.php?id=<?php echo (int)$item['id']; ?>">Edit</a>
        <a class="btn btn-secondary" href="wishlist.php">Back to wish list</a>
        <a class="btn btn-danger" href="wishlist_delete.php?id=<?php echo (int)$item['id']; ?>">Delete</a>
    </div>

</section>

<?php require __DIR__ . '/footer.php'; ?>
