<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/books.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';

try
{
    $mysqli = getDatabaseConnection();
    $items = getWishlistItems($mysqli, $q);
    $anzahl = countWishlistItems($mysqli, $q);
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

usort(
    $items,
    static function (array $a, array $b): int
    {
        $autorA = trim((string)($a['autor'] ?? ''));
        $autorB = trim((string)($b['autor'] ?? ''));

        $cmp = strcasecmp($autorA, $autorB);

        if ($cmp !== 0)
        {
            return $cmp;
        }

        $reiheA = trim((string)($a['reihe'] ?? ''));
        $reiheB = trim((string)($b['reihe'] ?? ''));

        if ($reiheA === '' && $reiheB !== '')
        {
            return 1;
        }

        if ($reiheA !== '' && $reiheB === '')
        {
            return -1;
        }

        $cmp = strcasecmp($reiheA, $reiheB);

        if ($cmp !== 0)
        {
            return $cmp;
        }

        $teilA = $a['teil_der_reihe'] ?? null;
        $teilB = $b['teil_der_reihe'] ?? null;

        $teilA = ($teilA === '' || $teilA === null) ? PHP_INT_MAX : (int)$teilA;
        $teilB = ($teilB === '' || $teilB === null) ? PHP_INT_MAX : (int)$teilB;

        if ($teilA !== $teilB)
        {
            return $teilA <=> $teilB;
        }

        $titelA = trim((string)($a['titel'] ?? ''));
        $titelB = trim((string)($b['titel'] ?? ''));

        return strcasecmp($titelA, $titelB);
    }
);

$pageTitle = 'Wish list';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
        🌙
    </button>

    <div class="eyebrow">Wish list</div>

    <h1>Wish list</h1>

    <p class="subtitle">
        Keep track of books you want to add to your collection later.
    </p>

</section>

<?php if ($deleted): ?>
    <div class="alert alert-success">
        The wish list entry was deleted.
    </div>
<?php endif; ?>

<div class="toolbar">

    <div class="search-card">

        <form class="search-form" method="get">

            <div class="search-input-group">

                <input
                    type="text"
                    name="q"
                    placeholder="Search by author, title, series, year or genre …"
                    value="<?php echo htmlspecialchars($q); ?>"
                >

                <button class="btn btn-primary search-submit" type="submit" aria-label="Search">
                    🔎
                </button>

            </div>

            <?php if ($q !== ''): ?>
                <a class="btn btn-secondary search-reset" href="wishlist.php">
                    Reset
                </a>
            <?php endif; ?>

        </form>

    </div>

    <div class="toolbar-side wishlist-toolbar-side">

        <a class="btn btn-primary btn-full wishlist-toolbar-button" href="wishlist_form.php">
            Add wish list entry
        </a>

        <a class="btn btn-primary btn-full wishlist-toolbar-button" href="index.php">
            Back to BookDB
        </a>

    </div>

    <div class="meta-card wishlist-meta-card">

        <div class="meta-label">
            <?php if ($q !== ''): ?>
                Found wish list entries
            <?php else: ?>
                Wish list entries
            <?php endif; ?>
        </div>

        <div class="meta-value"><?php echo $anzahl; ?></div>

    </div>

</div>

<?php if (!empty($items)): ?>

    <section class="table-card desktop-only wishlist-table-card">

        <div class="table-wrap">

            <table class="wishlist-table">

                <thead>
                    <tr>
                        <th>Author</th>
                        <th>Title</th>
                        <th>Series</th>
                        <th>Part</th>
                        <th>Genres</th>
                        <th>Publication year</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="wishlist-col-author">
                                <?php echo htmlspecialchars((string)$item['autor']); ?>
                            </td>

                            <td class="title-cell wishlist-col-title">
                                <a class="title-link" href="wishlist_details.php?id=<?php echo (int)$item['id']; ?>">
                                    <?php echo htmlspecialchars((string)$item['titel']); ?>
                                </a>
                            </td>

                            <td class="wishlist-col-series">
                                <?php
                                $reihe = trim((string)($item['reihe'] ?? ''));
                                echo $reihe !== '' ? htmlspecialchars($reihe) : '<span class="muted">—</span>';
                                ?>
                            </td>

                            <td class="wishlist-col-part">
                                <?php
                                $teil = $item['teil_der_reihe'] ?? null;
                                echo ($teil !== null && $teil !== '' && (string)$teil !== '0')
                                    ? htmlspecialchars((string)$teil)
                                    : '<span class="muted">—</span>';
                                ?>
                            </td>

                            <td class="wishlist-col-genres">
                                <?php
                                $genres = trim((string)($item['genres'] ?? ''));
                                echo $genres !== '' ? htmlspecialchars($genres) : '<span class="muted">—</span>';
                                ?>
                            </td>

                            <td class="wishlist-col-year">
                                <?php
                                $jahr = trim((string)($item['erscheinungsjahr'] ?? ''));
                                echo ($jahr !== '' && $jahr !== '0000') ? htmlspecialchars($jahr) : '<span class="muted">—</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>

    <div class="mobile-only section-stack wishlist-mobile-list">
        <?php foreach ($items as $item): ?>
            <article class="mobile-book-card wishlist-mobile-card">

                <div class="mobile-book-author">
                    <?php echo htmlspecialchars((string)$item['autor']); ?>
                </div>

                <h2 class="mobile-book-title">
                    <a class="title-link" href="wishlist_details.php?id=<?php echo (int)$item['id']; ?>">
                        <?php echo htmlspecialchars((string)$item['titel']); ?>
                    </a>
                </h2>

                <div class="mobile-book-meta">
                    <?php if (trim((string)($item['reihe'] ?? '')) !== ''): ?>
                        <div><strong>Series:</strong> <?php echo htmlspecialchars((string)$item['reihe']); ?></div>
                    <?php endif; ?>

                    <?php if (($item['teil_der_reihe'] ?? null) !== null && (string)$item['teil_der_reihe'] !== '0' && (string)$item['teil_der_reihe'] !== ''): ?>
                        <div><strong>Part:</strong> <?php echo htmlspecialchars((string)$item['teil_der_reihe']); ?></div>
                    <?php endif; ?>

                    <?php if (trim((string)($item['genres'] ?? '')) !== ''): ?>
                        <div><strong>Genres:</strong> <?php echo htmlspecialchars((string)$item['genres']); ?></div>
                    <?php endif; ?>

                    <?php if (trim((string)($item['erscheinungsjahr'] ?? '')) !== '' && (string)$item['erscheinungsjahr'] !== '0000'): ?>
                        <div><strong>Publication year:</strong> <?php echo htmlspecialchars((string)$item['erscheinungsjahr']); ?></div>
                    <?php endif; ?>
                </div>

            </article>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <section class="details-card">
        <div class="empty">
            <?php if ($q !== ''): ?>
                No matching wish list entry was found.
            <?php else: ?>
                The wish list is currently empty.
            <?php endif; ?>
        </div>
    </section>

<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>