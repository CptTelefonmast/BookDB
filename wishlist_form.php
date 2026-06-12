<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$errors = [];

$autor = '';
$titel = '';
$reihe = '';
$teilDerReihe = '';
$erscheinungsjahr = '';

$availableGenres = [];
$validGenreIds = [];
$genreRows = [
    [
        'selection' => '',
        'new_value' => ''
    ]
];

try
{
    $mysqli = getDatabaseConnection();
    $referenceData = getWishlistFormReferenceData($mysqli);

    $availableGenres = $referenceData['availableGenres'];
    $validGenreIds = $referenceData['validGenreIds'];

    if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST')
    {
        $wishlistItem = getWishlistItemById($mysqli, $id);

        if ($wishlistItem === null)
        {
            $mysqli->close();
            die('Wish list entry not found.');
        }

        $autor = (string)($wishlistItem['autor'] ?? '');
        $titel = (string)($wishlistItem['titel'] ?? '');
        $reihe = (string)($wishlistItem['reihe'] ?? '');
        $teilDerReihe = ($wishlistItem['teil_der_reihe'] !== null && (string)$wishlistItem['teil_der_reihe'] !== '0')
            ? (string)$wishlistItem['teil_der_reihe']
            : '';
        $erscheinungsjahr = ($wishlistItem['erscheinungsjahr'] !== null && (string)$wishlistItem['erscheinungsjahr'] !== '0000')
            ? (string)$wishlistItem['erscheinungsjahr']
            : '';

        $existingGenreIds = array_map('strval', $wishlistItem['genre_ids'] ?? []);

        if (!empty($existingGenreIds))
        {
            $genreRows = [];

            foreach ($existingGenreIds as $existingGenreId)
            {
                $genreRows[] = [
                    'selection' => $existingGenreId,
                    'new_value' => ''
                ];
            }

            $genreRows[] = [
                'selection' => '',
                'new_value' => ''
            ];
        }
    }

    $mysqli->close();
}
catch (mysqli_sql_exception $e)
{
    if (isset($mysqli) && $mysqli instanceof mysqli)
    {
        $mysqli->close();
    }

    $errors[] = 'The selection lists could not be loaded: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $autor = trim((string)($_POST['autor'] ?? ''));
    $titel = trim((string)($_POST['titel'] ?? ''));
    $reihe = trim((string)($_POST['reihe'] ?? ''));
    $teilDerReihe = trim((string)($_POST['teil_der_reihe'] ?? ''));
    $erscheinungsjahr = trim((string)($_POST['erscheinungsjahr'] ?? ''));

    $postedSelections = $_POST['genre_selections'] ?? [];
    $postedNewValues = $_POST['new_genre_values'] ?? [];

    if (!is_array($postedSelections))
    {
        $postedSelections = [];
    }

    if (!is_array($postedNewValues))
    {
        $postedNewValues = [];
    }

    $maxRowCount = max(count($postedSelections), count($postedNewValues), 1);
    $genreRows = [];

    for ($i = 0; $i < $maxRowCount; $i++)
    {
        $genreRows[] = [
            'selection' => trim((string)($postedSelections[$i] ?? '')),
            'new_value' => trim((string)($postedNewValues[$i] ?? ''))
        ];
    }

    if ($autor === '')
    {
        $errors[] = 'Please enter an author.';
    }

    if ($titel === '')
    {
        $errors[] = 'Please enter a title.';
    }

    if ($teilDerReihe !== '' && !ctype_digit($teilDerReihe))
    {
        $errors[] = 'Series part must be a whole number.';
    }

    if ($erscheinungsjahr !== '' && !preg_match('/^\d{1,4}$/', $erscheinungsjahr))
    {
        $errors[] = 'Publication year may contain at most four digits.';
    }

    $allowedGenreValues = array_merge(['', '__new__'], $validGenreIds);
    $selectedGenreIds = [];
    $newGenreNames = [];
    $selectedGenreIdsSeen = [];
    $newGenreNamesSeen = [];

    foreach ($genreRows as $index => $genreRow)
    {
        $selection = $genreRow['selection'];
        $newValue = $genreRow['new_value'];
        $position = $index + 1;

        if (!in_array($selection, $allowedGenreValues, true))
        {
            $errors[] = 'For genre ' . $position . ', an invalid value was selected.';
            continue;
        }

        if ($selection === '')
        {
            continue;
        }

        if ($selection === '__new__')
        {
            if ($newValue === '')
            {
                $errors[] = 'Please enter a new genre name for genre ' . $position . '.';
                continue;
            }

            $newValueKey = normalizeGenreKey($newValue);

            if (isset($newGenreNamesSeen[$newValueKey]))
            {
                $errors[] = 'The new genre “' . htmlspecialchars($newValue) . '” was entered more than once.';
                continue;
            }

            $newGenreNamesSeen[$newValueKey] = true;
            $newGenreNames[] = $newValue;
            continue;
        }

        if (isset($selectedGenreIdsSeen[$selection]))
        {
            $errors[] = 'Please do not select the same existing genre more than once.';
            continue;
        }

        $selectedGenreIdsSeen[$selection] = true;
        $selectedGenreIds[] = (int)$selection;
    }

    if (empty($errors))
    {
        $reiheValue = $reihe !== '' ? $reihe : null;
        $teilValue = $teilDerReihe !== '' ? (int)$teilDerReihe : null;
        $jahrValue = $erscheinungsjahr !== '' ? (int)$erscheinungsjahr : null;
        $newGenresInput = implode(', ', $newGenreNames);

        try
        {
            $mysqli = getDatabaseConnection();

            if ($isEdit)
            {
                updateWishlistItem(
                    $mysqli,
                    $id,
                    $autor,
                    $titel,
                    $reiheValue,
                    $teilValue,
                    $jahrValue,
                    $selectedGenreIds,
                    $newGenresInput
                );

                $mysqli->close();

                header('Location: wishlist_details.php?id=' . $id . '&updated=1');
                exit;
            }

            $newWishlistId = createWishlistItem(
                $mysqli,
                $autor,
                $titel,
                $reiheValue,
                $teilValue,
                $jahrValue,
                $selectedGenreIds,
                $newGenresInput
            );

            $mysqli->close();

            header('Location: wishlist_details.php?id=' . $newWishlistId . '&created=1');
            exit;
        }
        catch (Throwable $e)
        {
            if (isset($mysqli) && $mysqli instanceof mysqli)
            {
                $mysqli->close();
            }

            $errors[] = 'The wish list entry could not be saved: ' . $e->getMessage();
        }
    }

    $hasNonEmptyRow = false;

    foreach ($genreRows as $genreRow)
    {
        if ($genreRow['selection'] !== '' || $genreRow['new_value'] !== '')
        {
            $hasNonEmptyRow = true;
            break;
        }
    }

    if ($hasNonEmptyRow)
    {
        $lastRow = end($genreRows);

        if ($lastRow['selection'] !== '' || $lastRow['new_value'] !== '')
        {
            $genreRows[] = [
                'selection' => '',
                'new_value' => ''
            ];
        }
    }
}

$pageTitle = $isEdit ? 'Edit wish list entry' : 'Add wish list entry';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
        🌙
    </button>

    <div class="eyebrow">Wish list</div>

    <h1><?php echo $isEdit ? 'Edit wish list entry' : 'Create new wish list entry'; ?></h1>

    <p class="subtitle">
        Only the core book data is stored here.
    </p>

</section>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Please check your input:</strong>
        <ul class="alert-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="form-card">

    <form class="book-form" method="post" action="">

        <div class="form-grid">

            <div class="form-field">
                <label for="autor">Author *</label>
                <input type="text" id="autor" name="autor" value="<?php echo htmlspecialchars($autor); ?>" required>
            </div>

            <div class="form-field">
                <label for="titel">Title *</label>
                <input type="text" id="titel" name="titel" value="<?php echo htmlspecialchars($titel); ?>" required>
            </div>

            <div class="form-field">
                <label for="reihe">Series</label>
                <input type="text" id="reihe" name="reihe" value="<?php echo htmlspecialchars($reihe); ?>">
            </div>

            <div class="form-field">
                <label for="teil_der_reihe">Series part</label>
                <input type="number" id="teil_der_reihe" name="teil_der_reihe" min="1" step="1" value="<?php echo htmlspecialchars($teilDerReihe); ?>">
            </div>

            <div style="grid-column: 1 / -1;">
                <div id="genre-rows" class="genre-grid">
                    <?php foreach ($genreRows as $index => $genreRow): ?>
                        <?php renderGenreItem($availableGenres, $genreRow['selection'], $genreRow['new_value'], $index); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-field">
                <label for="erscheinungsjahr">Publication year</label>
                <input type="number" id="erscheinungsjahr" name="erscheinungsjahr" min="0" max="9999" step="1" value="<?php echo htmlspecialchars($erscheinungsjahr); ?>">
            </div>

        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save entry</button>
            <a class="btn btn-secondary" href="<?php echo $isEdit ? 'wishlist_details.php?id=' . (int)$id : 'wishlist.php'; ?>">Cancel</a>
        </div>

    </form>

</section>

<?php renderBookFormInitScript($availableGenres, [], ''); ?>
<?php require __DIR__ . '/footer.php'; ?>
