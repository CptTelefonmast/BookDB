<?php
$pageTitle = 'BookDB';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color theme">
        🌙
    </button>

    <div class="eyebrow">BookDB</div>

    <h1>Book Database</h1>

    <p class="subtitle">
        A simple web application for managing a personal book collection.
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
                    placeholder="Search by author, title, series or genre…"
                    value="<?php echo htmlspecialchars($q); ?>"
                >

                <button class="btn btn-primary search-submit" type="submit" aria-label="Search">
                    🔎
                </button>

            </div>

            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
            <input type="hidden" name="per_page" value="<?php echo htmlspecialchars($perPage); ?>">

            <?php if ($q !== '' || $perPage !== '25'): ?>
                <a class="btn btn-secondary search-reset" href="index.php">Reset</a>
            <?php endif; ?>

        </form>

    </div>

    <div class="toolbar-side">

        <a class="btn btn-primary btn-full desktop-add-button" href="insert.php">
            Add New Book
        </a>

        <div class="meta-card">

            <div class="meta-label">
                <?php if ($q !== ''): ?>
                    Books Found
                <?php else: ?>
                    Books in Database
                <?php endif; ?>
            </div>

            <div class="meta-value"><?php echo $anzahl; ?></div>

        </div>

    </div>

</div>

<?php if ($anzahl > 0): ?>

    <section class="table-card desktop-only">

        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('autor', $sort, $dir, $q, $perPage)); ?>">
                                <span>Author</span>
                                <?php echo sortIndicator('autor', $sort, $dir); ?>
                            </a>
                        </th>

                        <th>
                            <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('titel', $sort, $dir, $q, $perPage)); ?>">
                                <span>Title</span>
                                <?php echo sortIndicator('titel', $sort, $dir); ?>
                            </a>
                        </th>

                        <th>
                            <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('reihe', $sort, $dir, $q, $perPage)); ?>">
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
                            <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('genres', $sort, $dir, $q, $perPage)); ?>">
                                <span>Genres</span>
                                <?php echo sortIndicator('genres', $sort, $dir); ?>
                            </a>
                        </th>

                        <th>
                            <a class="th-link" href="<?php echo htmlspecialchars(buildSortLink('erscheinungsjahr', $sort, $dir, $q, $perPage)); ?>">
                                <span>Publication Year</span>
                                <?php echo sortIndicator('erscheinungsjahr', $sort, $dir); ?>
                            </a>
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td><?php echo htmlspecialchars($row['autor']); ?></td>

                            <td class="title-cell">
                                <a class="title-link" href="details.php?id=<?php echo (int)$row['id']; ?>">
                                    <?php echo htmlspecialchars($row['titel']); ?>
                                </a>
                            </td>

                            <td>
                                <?php
                                $reihe = trim((string)($row['reihe'] ?? ''));
                                echo $reihe !== '' ? htmlspecialchars($reihe) : '<span class="muted">—</span>';
                                ?>
                            </td>

                            <td>
                                <?php
                                $teil = $row['teil_der_reihe'];
                                echo ($teil !== null && $teil !== '' && (string)$teil !== '0')
                                    ? htmlspecialchars((string)$teil)
                                    : '<span class="muted">—</span>';
                                ?>
                            </td>

                            <td>
                                <?php
                                $genres = trim((string)($row['genres'] ?? ''));
                                echo $genres !== '' ? htmlspecialchars($genres) : '<span class="muted">—</span>';
                                ?>
                            </td>

                            <td>
                                <?php
                                $jahr = (string)($row['erscheinungsjahr'] ?? '');
                                echo ($jahr !== '' && $jahr !== '0000')
                                    ? htmlspecialchars($jahr)
                                    : '<span class="muted">—</span>';
                                ?>
                            </td>

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
                    <a class="mobile-book-title-link" href="details.php?id=<?php echo (int)$row['id']; ?>">
                        <?php echo htmlspecialchars($row['titel']); ?>
                    </a>
                </div>

                <div class="mobile-book-author">
                    <?php echo htmlspecialchars($row['autor']); ?>
                </div>

                <?php
                $genres = trim((string)($row['genres'] ?? ''));
                ?>

                <?php if ($genres !== ''): ?>
                    <div class="mobile-book-author">
                        <?php echo htmlspecialchars($genres); ?>
                    </div>
                <?php endif; ?>

            </article>

        <?php endwhile; ?>

    </section>

<?php else: ?>

    <section class="table-card">

        <div class="empty">
            No books found.
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
                    <option value="all" <?php echo $perPage === 'all' ? 'selected' : ''; ?>>All</option>
                </select>

            </form>

            <?php if ($gesamtseiten > 1): ?>

                <div class="pagination-nav">

                    <?php if ($page > 1): ?>
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars(buildPageLink($page - 1, $sort, $dir, $q, $perPage)); ?>">
                            ← Previous
                        </a>
                    <?php endif; ?>

                    <span class="btn btn-secondary pagination-status">
                        Page <?php echo $page; ?> of <?php echo $gesamtseiten; ?>
                    </span>

                    <?php if ($page < $gesamtseiten): ?>
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars(buildPageLink($page + 1, $sort, $dir, $q, $perPage)); ?>">
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

if ($q !== '')
{
    $footerParts[] = 'Search: "' . htmlspecialchars($q) . '"';
}

$footerParts[] = 'Sorting: ' . htmlspecialchars($sort) . ' (' . htmlspecialchars($dir) . ')';

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