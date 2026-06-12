<?php

/*
|--------------------------------------------------------------------------
| Detail page for a single book
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/lent.php';
require_once __DIR__ . '/functions.php';

function buildLocationLabel(?array $location): string
{
    if ($location === null)
    {
        return 'No location saved';
    }

    $parts = [];

    $regal = trim((string)($location['regal'] ?? ''));
    $regalfach = trim((string)($location['regalfach'] ?? ''));

    if ($regal !== '')
    {
        $parts[] = $regal;
    }

    if ($regalfach !== '')
    {
        $parts[] = $regalfach;
    }

    if (empty($parts))
    {
        return 'No location saved';
    }

    return implode(' · ', $parts);
}

function getBookNavigationContext(
    mysqli $mysqli,
    int $currentBookId,
    string $q,
    string $sort,
    string $dir,
    bool $onlyLent
): array
{
    $allowedSorts = getAllowedSorts();

    if (!array_key_exists($sort, $allowedSorts))
    {
        $sort = 'autor';
    }

    if ($dir !== 'asc' && $dir !== 'desc')
    {
        $dir = 'asc';
    }

    $orderBy = buildOrderBy($sort, $dir, $allowedSorts);
    $result = getBooks($mysqli, $q, $orderBy, null, 0, $onlyLent);

    $bookIds = [];

    while ($row = $result->fetch_assoc())
    {
        $bookIds[] = (int)$row['id'];
    }

    $result->free();

    $currentIndex = array_search($currentBookId, $bookIds, true);

    if ($currentIndex === false)
    {
        return [
            'previous_id' => null,
            'next_id' => null
        ];
    }

    return [
        'previous_id' => $currentIndex > 0 ? $bookIds[$currentIndex - 1] : null,
        'next_id' => $currentIndex < count($bookIds) - 1 ? $bookIds[$currentIndex + 1] : null
    ];
}

function buildDetailsNavigationLink(
    int $id,
    string $q,
    string $sort,
    string $dir,
    string $perPage,
    int $page,
    bool $onlyLent
): string
{
    $params = [
        'id' => $id,
        'q' => $q,
        'sort' => $sort,
        'dir' => $dir,
        'per_page' => $perPage,
        'page' => $page
    ];

    if ($onlyLent)
    {
        $params['lent'] = '1';
    }

    return 'details.php?' . http_build_query($params);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$updated = isset($_GET['updated']) && $_GET['updated'] === '1';
$loanStatus = isset($_GET['loan_status']) ? trim((string)$_GET['loan_status']) : '';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'autor';
$dir = isset($_GET['dir']) ? strtolower(trim((string)$_GET['dir'])) : 'asc';
$perPage = isset($_GET['per_page']) ? strtolower(trim((string)$_GET['per_page'])) : '25';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$onlyLent = isset($_GET['lent']) && $_GET['lent'] === '1';

if ($id <= 0)
{
    die('Invalid book ID.');
}

if ($page < 1)
{
    $page = 1;
}

$errors = [];
$loanPerson = '';
$loanDate = date('Y-m-d');
$returnDate = date('Y-m-d');

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

    if ($book === null)
    {
        $mysqli->close();

        $pageTitle = 'Book not found';
        require __DIR__ . '/header.php';
        ?>
        <section class="hero">

            <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        $action = $_POST['action'] ?? '';

        if ($action === 'lend_book')
        {
            $loanPerson = trim((string)($_POST['loan_person'] ?? ''));
            $loanDate = trim((string)($_POST['loan_date'] ?? date('Y-m-d')));

            if ($loanPerson === '')
            {
                $errors[] = 'Please enter who the book was lent to.';
            }

            if (!isValidDateString($loanDate))
            {
                $errors[] = 'Please enter a valid lending date.';
            }

            if (empty($errors))
            {
                lendBook($mysqli, $id, $loanPerson, $loanDate);

                $redirectParams = [
                    'id' => $id,
                    'loan_status' => 'lent',
                    'q' => $q,
                    'sort' => $sort,
                    'dir' => $dir,
                    'per_page' => $perPage,
                    'page' => $page
                ];

                if ($onlyLent)
                {
                    $redirectParams['lent'] = '1';
                }

                $mysqli->close();

                header('Location: details.php?' . http_build_query($redirectParams));
                exit;
            }
        }
        elseif ($action === 'return_book')
        {
            $returnDate = trim((string)($_POST['return_date'] ?? date('Y-m-d')));

            if (!isValidDateString($returnDate))
            {
                $errors[] = 'Please enter a valid return date.';
            }

            if (empty($errors))
            {
                returnBook($mysqli, $id, $returnDate);

                $redirectParams = [
                    'id' => $id,
                    'loan_status' => 'returned',
                    'q' => $q,
                    'sort' => $sort,
                    'dir' => $dir,
                    'per_page' => $perPage,
                    'page' => $page
                ];

                if ($onlyLent)
                {
                    $redirectParams['lent'] = '1';
                }

                $mysqli->close();

                header('Location: details.php?' . http_build_query($redirectParams));
                exit;
            }
        }
    }

    $currentLocation = getCurrentLocationByBookId($mysqli, $id);
    $currentLoan = getCurrentLoanByBookId($mysqli, $id);
    $loanHistory = getLoanHistoryByBookId($mysqli, $id);
    $navigation = getBookNavigationContext($mysqli, $id, $q, $sort, $dir, $onlyLent);
}
catch (RuntimeException $e)
{
    $errors[] = $e->getMessage();

    try
    {
        $currentLocation = getCurrentLocationByBookId($mysqli, $id);
        $currentLoan = getCurrentLoanByBookId($mysqli, $id);
        $loanHistory = getLoanHistoryByBookId($mysqli, $id);
        $navigation = getBookNavigationContext($mysqli, $id, $q, $sort, $dir, $onlyLent);
    }
    catch (mysqli_sql_exception $innerException)
    {
        $mysqli->close();
        renderQueryErrorPage($innerException);
        exit;
    }
}
catch (mysqli_sql_exception $e)
{
    $mysqli->close();
    renderQueryErrorPage($e);
    exit;
}

$mysqli->close();

$previousBookId = $navigation['previous_id'] ?? null;
$nextBookId = $navigation['next_id'] ?? null;

$pageTitle = $book['titel'] . ' – Details';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
        🌙
    </button>

    <div class="eyebrow">BookDB</div>

    <h1><?php echo htmlspecialchars($book['titel']); ?></h1>

    <p class="subtitle">
        by
        <a href="index.php?q=<?php echo urlencode($book['autor']); ?>">
            <?php echo htmlspecialchars($book['autor']); ?>
        </a>
    </p>

</section>

<?php if ($updated): ?>
    <div class="alert alert-success">
        The changes were saved successfully.
    </div>
<?php endif; ?>

<?php if ($loanStatus === 'lent'): ?>
    <div class="alert alert-success">
        The book was marked as lent.
    </div>
<?php endif; ?>

<?php if ($loanStatus === 'returned'): ?>
    <div class="alert alert-success">
        The return was saved.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please check your input:</strong>
        <ul class="alert-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


<section class="details-card">

    <div class="details-grid">

        <div class="details-item">
            <div class="details-label">Author</div>
            <div class="details-value">
                <a href="index.php?q=<?php echo urlencode($book['autor']); ?>">
                    <?php echo htmlspecialchars($book['autor']); ?>
                </a>
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
                    echo '—';
                }
                ?>
            </div>
        </div>

        <div class="details-item">
            <div class="details-label">Series part</div>
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
                $genres = $book['genres'] ?? [];

                if (!empty($genres))
                {
                    $genreLinks = [];

                    foreach ($genres as $genre)
                    {
                        $genreName = trim((string)($genre['name'] ?? ''));

                        if ($genreName === '')
                        {
                            continue;
                        }

                        $genreLinks[] = '<a href="index.php?q=' . urlencode($genreName) . '">' . htmlspecialchars($genreName) . '</a>';
                    }

                    echo !empty($genreLinks) ? implode(', ', $genreLinks) : '—';
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
                $jahr = (string)($book['erscheinungsjahr'] ?? '');

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
                    echo '—';
                }
                ?>
            </div>
        </div>

        <div class="details-item">
            <div class="details-label">Purchased from</div>
            <div class="details-value">
                <?php
                $gekauftBei = trim((string)($book['gekauft_bei'] ?? ''));

                echo $gekauftBei !== ''
                    ? htmlspecialchars($gekauftBei)
                    : '—';
                ?>
            </div>
        </div>

    </div>

    <div class="form-actions">

        <a
            class="btn btn-primary"
            href="edit.php?id=<?php echo (int)$book['id']; ?>"
        >
            Edit book
        </a>

        <a
            class="btn btn-secondary"
            href="<?php echo htmlspecialchars(buildPageLink($page, $sort, $dir, $q, $perPage, $onlyLent)); ?>"
        >
            Back to list
        </a>

    </div>

</section>

<section class="details-card">

    <div class="details-grid">

        <div class="details-item">
            <div class="details-label">Location</div>
            <div class="details-value">
                <?php echo htmlspecialchars(buildLocationLabel($currentLocation)); ?>
            </div>
        </div>

        <?php
        $schuberName = '';
        $istImSchuber = false;

        if ($currentLocation !== null)
        {
            $schuberName = trim((string)($currentLocation['schuber'] ?? ''));
            $istImSchuber = (int)($currentLocation['ist_im_schuber'] ?? 0) === 1 && $schuberName !== '';
        }
        ?>

        <?php if ($istImSchuber): ?>
            <div class="details-item">
                <div class="details-label">Slipcase</div>
                <div class="details-value">
                    <a href="schuber.php?name=<?php echo urlencode($schuberName); ?>">
                        <?php echo htmlspecialchars($schuberName); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

</section>

<section class="details-card">

    <div class="details-grid">

        <div class="details-item">
            <div class="details-label">Lending status</div>
            <div class="details-value">
                <?php if ($currentLoan !== null): ?>
                    Lent to
                    <?php echo htmlspecialchars((string)$currentLoan['person']); ?>
                    since
                    <?php echo htmlspecialchars((string)$currentLoan['verliehen_am']); ?>
                <?php else: ?>
                    Available
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php if ($currentLoan === null): ?>

        <form class="book-form" method="post" action="">

            <input type="hidden" name="action" value="lend_book">

            <div class="form-grid">

                <div class="form-field">
                    <label for="loan_person">Lent to</label>
                    <input
                        type="text"
                        id="loan_person"
                        name="loan_person"
                        value="<?php echo htmlspecialchars($loanPerson); ?>"
                        required
                    >
                </div>

                <div class="form-field">
                    <label for="loan_date">Lent on</label>
                    <input
                        type="date"
                        id="loan_date"
                        name="loan_date"
                        value="<?php echo htmlspecialchars($loanDate); ?>"
                        required
                    >
                </div>

            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    Mark as lent
                </button>
            </div>

        </form>

    <?php else: ?>

        <form class="book-form" method="post" action="">

            <input type="hidden" name="action" value="return_book">

            <div class="form-grid">

                <div class="form-field">
                    <label for="return_date">Returned on</label>
                    <input
                        type="date"
                        id="return_date"
                        name="return_date"
                        value="<?php echo htmlspecialchars($returnDate); ?>"
                        required
                    >
                </div>

            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    Mark as returned
                </button>
            </div>

        </form>

    <?php endif; ?>

</section>

<section class="table-card">

    <div class="table-wrap">

        <?php if (!empty($loanHistory)): ?>

            <table>

                <thead>
                    <tr>
                        <th>
                            <div class="th-link th-static">
                                <span>Person</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Lent on</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                        <th>
                            <div class="th-link th-static">
                                <span>Returned on</span>
                                <span class="sort-indicator inactive"></span>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($loanHistory as $loan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$loan['person']); ?></td>
                            <td><?php echo htmlspecialchars((string)$loan['verliehen_am']); ?></td>
                            <td>
                                <?php
                                $returnedAt = trim((string)($loan['zurueckgegeben_am'] ?? ''));

                                echo $returnedAt !== ''
                                    ? htmlspecialchars($returnedAt)
                                    : '<span class="muted">Not yet returned</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty">
                There is no lending history for this book yet.
            </div>

        <?php endif; ?>

    </div>

</section>

<?php require __DIR__ . '/footer.php'; ?>
