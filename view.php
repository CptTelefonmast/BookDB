<?php
$pageTitle = 'BookDB';
$onlyLent = isset($onlyLent) ? (bool)$onlyLent : false;
require __DIR__ . '/header.php';
?>

    <section class="hero">

        <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
            🌙
        </button>

        <div class="eyebrow">BookDB</div>

        <h1>Your personal book collection database.</h1>

        <p class="subtitle">
            Search, browse and manage your book collection.
        </p>

    </section>

    <?php if ($added): ?>
        <div class="alert alert-success">
            The book was added successfully.
        </div>
    <?php endif; ?>

    <?php if ($deleted): ?>
        <div class="alert alert-success">
            The book was deleted successfully.
        </div>
    <?php endif; ?>

    <div class="toolbar">

        <div class="search-card">

            <form class="search-form" method="get">

                <div class="search-input-group">

                    <input
                        type="text"
                        name="q"
                        placeholder="Search by author, title, series, year, genre or person …"
                        value="<?php echo htmlspecialchars($q); ?>"
                    >

                    <button class="btn btn-primary search-submit" type="submit" aria-label="Search">
                        🔎
                    </button>

                </div>

                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
                <input type="hidden" name="per_page" value="<?php echo htmlspecialchars($perPage); ?>">

                <?php if ($onlyLent): ?>
                    <input type="hidden" name="lent" value="1">
                <?php endif; ?>

                <?php
                $resetLink = $onlyLent ? 'index.php?lent=1' : 'index.php';
                ?>

                <?php if ($q !== '' || $perPage !== '25'): ?>
                    <a class="btn btn-secondary search-reset" href="<?php echo htmlspecialchars($resetLink); ?>">Reset</a>
                <?php endif; ?>

            </form>

        </div>

        <div class="toolbar-side">

            <a class="btn btn-primary btn-full desktop-add-button" href="insert.php">
                Add new book
            </a>

            <?php if ($onlyLent): ?>
                <a class="btn btn-primary btn-full" href="index.php">
                    Show all books
                </a>
            <?php else: ?>
                <a class="btn btn-primary btn-full" href="index.php?lent=1">
                    Show lent books
                </a>
            <?php endif; ?>

            <a class="btn btn-primary btn-full" href="wishlist.php">
                Wish list (<?php echo (int)($wishlistCount ?? 0); ?>)
            </a>

        </div>

        <div class="meta-card">

            <div class="meta-label">
                <?php if ($onlyLent && $q !== ''): ?>
                    Matching lent books
                <?php elseif ($onlyLent): ?>
                    Currently lent books
                <?php elseif ($q !== ''): ?>
                    Matching books
                <?php else: ?>
                    Books in the database
                <?php endif; ?>
            </div>

            <div class="meta-value"><?php echo $anzahl; ?></div>

        </div>

    </div>

    <?php if ($anzahl > 0): ?>

        <section class="table-card desktop-only">

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('autor', $sort, $dir, $q, $perPage, $onlyLent)); ?>">
                                    <span>Author</span>
                                    <?php echo sortIndicator('autor', $sort, $dir); ?>
                                </a>
                            </th>

                            <th>
                                <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('titel', $sort, $dir, $q, $perPage, $onlyLent)); ?>">
                                    <span>Title</span>
                                    <?php echo sortIndicator('titel', $sort, $dir); ?>
                                </a>
                            </th>

                            <th>
                                <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('reihe', $sort, $dir, $q, $perPage, $onlyLent)); ?>">
                                    <span>Series</span>
                                    <?php echo sortIndicator('reihe', $sort, $dir); ?>
                                </a>
                            </th>

                            <th>
                                <div class="th-link th-static">
                                    <span>Part</span>
                                    <span class="sort-indicator inactive"></span>
                                </div>
                            </th>

                            <th>
                                <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('genres', $sort, $dir, $q, $perPage, $onlyLent)); ?>">
                                    <span>Genres</span>
                                    <?php echo sortIndicator('genres', $sort, $dir); ?>
                                </a>
                            </th>

                            <th>
                                <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('erscheinungsjahr', $sort, $dir, $q, $perPage, $onlyLent)); ?>">
                                    <span>Publication year</span>
                                    <?php echo sortIndicator('erscheinungsjahr', $sort, $dir); ?>
                                </a>
                            </th>

                            <?php if ($onlyLent): ?>
                                <th>
                                    <div class="th-link th-static">
                                        <span>Lent to</span>
                                        <span class="sort-indicator inactive"></span>
                                    </div>
                                </th>
                            <?php endif; ?>

                        </tr>

                    </thead>

                    <tbody>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <a href="index.php?q=<?php echo urlencode($row['autor']); ?>">
                                        <?php echo htmlspecialchars($row['autor']); ?>
                                    </a>
                                </td>

                                <td class="title-cell">
                                    <a
                                        class="title-link"
                                        href="details.php?id=<?php echo (int)$row['id']; ?>&q=<?php echo urlencode($q); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&per_page=<?php echo urlencode($perPage); ?>&page=<?php echo (int)$page; ?><?php echo $onlyLent ? '&lent=1' : ''; ?>"
                                    >
                                        <?php echo htmlspecialchars($row['titel']); ?>
                                    </a>
                                </td>

                                <td>
                                    <?php
                                    $reihe = trim((string)($row['reihe'] ?? ''));

                                    if ($reihe !== '')
                                    {
                                        ?>
                                        <a href="index.php?q=<?php echo urlencode($reihe); ?>">
                                            <?php echo htmlspecialchars($reihe); ?>
                                        </a>
                                        <?php
                                    }
                                    else
                                    {
                                        echo '<span class="muted">—</span>';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    $teil = $row['teil_der_reihe'] ?? null;
                                    echo ($teil !== null && $teil !== '' && (string)$teil !== '0')
                                        ? htmlspecialchars((string)$teil)
                                        : '<span class="muted">—</span>';
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    $genres = trim((string)($row['genres'] ?? ''));

                                    if ($genres !== '')
                                    {
                                        $genreList = array_map('trim', explode(',', $genres));
                                        $genreLinks = [];

                                        foreach ($genreList as $genre)
                                        {
                                            if ($genre === '')
                                            {
                                                continue;
                                            }

                                            $genreLinks[] = '<a href="index.php?q=' . urlencode($genre) . '">' . htmlspecialchars($genre) . '</a>';
                                        }

                                        echo !empty($genreLinks) ? implode(', ', $genreLinks) : '<span class="muted">—</span>';
                                    }
                                    else
                                    {
                                        echo '<span class="muted">—</span>';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    $jahr = (string)($row['erscheinungsjahr'] ?? '');

                                    if ($jahr !== '' && $jahr !== '0000')
                                    {
                                        ?>
                                        <a href="index.php?q=<?php echo urlencode($jahr); ?>">
                                            <?php echo htmlspecialchars($jahr); ?>
                                        </a>
                                        <?php
                                    }
                                    else
                                    {
                                        echo '<span class="muted">—</span>';
                                    }
                                    ?>
                                </td>

                                <?php if ($onlyLent): ?>
                                    <td>
                                        <?php
                                        $verliehenAn = trim((string)($row['verliehen_an'] ?? ''));
                                        echo $verliehenAn !== ''
                                            ? htmlspecialchars($verliehenAn)
                                            : '<span class="muted">—</span>';
                                        ?>
                                    </td>
                                <?php endif; ?>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <?php $result->data_seek(0); ?>

        <section class="mobile-card-list mobile-only">

            <?php while ($row = $result->fetch_assoc()): ?>

                <article class="mobile-book-card">

                    <div class="mobile-book-title">
                        <a
                            class="mobile-book-title-link"
                            href="details.php?id=<?php echo (int)$row['id']; ?>&q=<?php echo urlencode($q); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&per_page=<?php echo urlencode($perPage); ?>&page=<?php echo (int)$page; ?><?php echo $onlyLent ? '&lent=1' : ''; ?>"
                        >
                            <?php echo htmlspecialchars($row['titel']); ?>
                        </a>
                    </div>

                    <div class="mobile-book-author">
                        <a href="index.php?q=<?php echo urlencode($row['autor']); ?>">
                            <?php echo htmlspecialchars($row['autor']); ?>
                        </a>
                    </div>

                    <?php
                    $reihe = trim((string)($row['reihe'] ?? ''));
                    $jahr = (string)($row['erscheinungsjahr'] ?? '');
                    $genres = trim((string)($row['genres'] ?? ''));
                    $verliehenAn = trim((string)($row['verliehen_an'] ?? ''));
                    ?>

                    <?php if ($reihe !== ''): ?>
                        <div class="mobile-book-author">
                            Series:
                            <a href="index.php?q=<?php echo urlencode($reihe); ?>">
                                <?php echo htmlspecialchars($reihe); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($jahr !== '' && $jahr !== '0000'): ?>
                        <div class="mobile-book-author">
                            Year:
                            <a href="index.php?q=<?php echo urlencode($jahr); ?>">
                                <?php echo htmlspecialchars($jahr); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($genres !== ''): ?>

                        <?php
                        $genreList = array_map('trim', explode(',', $genres));
                        $genreLinks = [];

                        foreach ($genreList as $genre)
                        {
                            if ($genre === '')
                            {
                                continue;
                            }

                            $genreLinks[] = '<a href="index.php?q=' . urlencode($genre) . '">' . htmlspecialchars($genre) . '</a>';
                        }
                        ?>

                        <?php if (!empty($genreLinks)): ?>
                            <div class="mobile-book-author">
                                <?php echo implode(', ', $genreLinks); ?>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                    <?php if ($onlyLent && $verliehenAn !== ''): ?>
                        <div class="mobile-book-author">
                            Lent to: <?php echo htmlspecialchars($verliehenAn); ?>
                        </div>
                    <?php endif; ?>

                </article>

            <?php endwhile; ?>

        </section>

    <?php else: ?>

        <section class="table-card">

            <div class="empty">
                <?php if ($onlyLent): ?>
                    No lent books found.
                <?php else: ?>
                    No books found.
                <?php endif; ?>
            </div>

        </section>

    <?php endif; ?>

    <?php if ($anzahl > 0): ?>
        <section class="pagination-card">

            <div class="pagination-bar">

                <form class="pagination-per-page-form" method="get" action="">

                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
                    <input type="hidden" name="page" value="1">

                    <?php if ($onlyLent): ?>
                        <input type="hidden" name="lent" value="1">
                    <?php endif; ?>

                    <label class="pagination-label" for="per_page_bottom">
                        Books per page:
                    </label>

                    <select
                        class="pagination-select"
                        id="per_page_bottom"
                        name="per_page"
                        onchange="this.form.submit()"
                    >
                        <option value="10" <?php echo $perPage === '10' ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $perPage === '25' ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $perPage === '50' ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $perPage === '100' ? 'selected' : ''; ?>>100</option>
                        <option value="all" <?php echo $perPage === 'all' ? 'selected' : ''; ?>>all</option>
                    </select>

                </form>

                <?php if ($gesamtseiten > 1): ?>

                    <div class="pagination-nav">

                        <?php if ($page > 1): ?>
                            <a class="btn btn-secondary" href="<?php echo htmlspecialchars(buildPageLink($page - 1, $sort, $dir, $q, $perPage, $onlyLent)); ?>">
                                ← Previous
                            </a>
                        <?php endif; ?>

                        <span class="btn btn-secondary pagination-status">
                            Page <?php echo $page; ?> of <?php echo $gesamtseiten; ?>
                        </span>

                        <?php if ($page < $gesamtseiten): ?>
                            <a class="btn btn-secondary" href="<?php echo htmlspecialchars(buildPageLink($page + 1, $sort, $dir, $q, $perPage, $onlyLent)); ?>">
                                Next →
                            </a>
                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </section>
    <?php endif; ?>

<?php
$footerParts = [];

if ($onlyLent)
{
    $footerParts[] = 'Filter: lent books only';
}

if ($q !== '')
{
    $footerParts[] = 'Search term: “' . htmlspecialchars($q) . '”';
}

$footerParts[] = 'Sort: ' . htmlspecialchars($sort) . ' (' . htmlspecialchars($dir) . ')';

if ($perPage === 'all')
{
    $footerParts[] = 'Display: all';
}
else
{
    $footerParts[] = 'Display: ' . htmlspecialchars($perPage) . ' per page';
    $footerParts[] = 'Page ' . $page . ' of ' . $gesamtseiten;
}

$footerRightContent = implode(' · ', $footerParts);
?>

<a class="mobile-fab mobile-only" href="insert.php">
    +
</a>

<?php require __DIR__ . '/footer.php'; ?>