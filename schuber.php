<?php

/*
|--------------------------------------------------------------------------
| Overview of all books in one slipcase
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/functions.php';

$schuber = isset($_GET['name']) ? trim((string)$_GET['name']) : '';

if ($schuber === '')
{
    die('No slipcase specified.');
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
    $anzahl = countBooksBySchuber($mysqli, $schuber);
    $books = getBooksBySchuber($mysqli, $schuber);
}
catch (mysqli_sql_exception $e)
{
    $mysqli->close();
    renderQueryErrorPage($e);
    exit;
}

$mysqli->close();

$pageTitle = 'Slipcase: ' . $schuber;
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
        🌙
    </button>

    <div class="eyebrow">BookDB</div>

    <h1>Slipcase: <?php echo htmlspecialchars($schuber); ?></h1>

    <p class="subtitle">
        Overview of all books in this slipcase.
    </p>

</section>

<div class="toolbar">

    <div class="meta-card">
        <div class="meta-label">Books in slipcase</div>
        <div class="meta-value"><?php echo (int)$anzahl; ?></div>
    </div>

    <div class="toolbar-side">
        <a class="btn btn-secondary btn-full" href="index.php">
            Back to list
        </a>
    </div>

</div>

<?php if (!empty($books)): ?>

    <section class="table-card">

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>
                            <div class="th-link th-static">
                                <span>Author</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Title</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Series</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Part</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Genres</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Publication year</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Shelf</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Compartment</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($books as $row): ?>
                        <tr>
                            <td>
                                <a href="index.php?q=<?php echo urlencode((string)$row['autor']); ?>">
                                    <?php echo htmlspecialchars((string)$row['autor']); ?>
                                </a>
                            </td>

                            <td class="title-cell">
                                <a class="title-link" href="details.php?id=<?php echo (int)$row['id']; ?>">
                                    <?php echo htmlspecialchars((string)$row['titel']); ?>
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

                            <td>
                                <?php
                                $regal = trim((string)($row['regal'] ?? ''));
                                echo $regal !== '' ? htmlspecialchars($regal) : '<span class="muted">—</span>';
                                ?>
                            </td>

                            <td>
                                <?php
                                $regalfach = trim((string)($row['regalfach'] ?? ''));
                                echo $regalfach !== '' ? htmlspecialchars($regalfach) : '<span class="muted">—</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </section>

<?php else: ?>

    <section class="table-card">
        <div class="empty">
            There are currently no books in this slipcase.
        </div>
    </section>

<?php endif; ?>

<?php
$footerRightContent = 'Slipcase: ' . htmlspecialchars($schuber) . ' · Books: ' . (int)$anzahl;
require __DIR__ . '/footer.php';
?>