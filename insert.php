<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/functions.php';

$errors = [];

$autor = '';
$titel = '';
$reihe = '';
$teilDerReihe = '';
$erscheinungsjahr = '';
$gekauftBei = '';
$gelesen = false;

$wishlistId = isset($_GET['wishlist_id']) ? (int)$_GET['wishlist_id'] : 0;
$wishlistImported = false;

$availableGenres = [];
$validGenreIds = [];
$genreRows = [
    [
        'selection' => '',
        'new_value' => ''
    ]
];

$availableRegale = [];
$availableFaecherMap = [];
$availableSchuber = [];

$regalSelection = '';
$newRegalValue = '';
$regalfachSelection = '';
$newRegalfachValue = '';

$schuberChecked = false;
$schuberSelection = '';
$newSchuberValue = '';

try
{
    $mysqli = getDatabaseConnection();
    $referenceData = getBookFormReferenceData($mysqli);

    $availableGenres = $referenceData['availableGenres'];
    $validGenreIds = $referenceData['validGenreIds'];
    $availableRegale = $referenceData['availableRegale'];
    $availableFaecherMap = $referenceData['availableFaecherMap'];
    $availableSchuber = $referenceData['availableSchuber'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $wishlistId > 0)
    {
        $wishlistItem = getWishlistItemById($mysqli, $wishlistId);

        if ($wishlistItem === null)
        {
            $errors[] = 'The wish list entry was not found.';
        }
        else
        {
            $autor = (string)($wishlistItem['autor'] ?? '');
            $titel = (string)($wishlistItem['titel'] ?? '');
            $reihe = (string)($wishlistItem['reihe'] ?? '');
            $teilDerReihe = isset($wishlistItem['teil_der_reihe']) && $wishlistItem['teil_der_reihe'] !== null
                ? (string)$wishlistItem['teil_der_reihe']
                : '';
            $erscheinungsjahr = isset($wishlistItem['erscheinungsjahr']) && $wishlistItem['erscheinungsjahr'] !== null
                ? (string)$wishlistItem['erscheinungsjahr']
                : '';

            $genreRows = [];
            $genreIds = $wishlistItem['genre_ids'] ?? [];

            foreach ($genreIds as $genreId)
            {
                $genreRows[] = [
                    'selection' => (string)$genreId,
                    'new_value' => ''
                ];
            }

            $genreRows[] = [
                'selection' => '',
                'new_value' => ''
            ];

            $wishlistImported = true;
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
    $wishlistId = isset($_POST['wishlist_id']) ? (int)$_POST['wishlist_id'] : 0;

    $autor = trim((string)($_POST['autor'] ?? ''));
    $titel = trim((string)($_POST['titel'] ?? ''));
    $reihe = trim((string)($_POST['reihe'] ?? ''));
    $teilDerReihe = trim((string)($_POST['teil_der_reihe'] ?? ''));
    $erscheinungsjahr = trim((string)($_POST['erscheinungsjahr'] ?? ''));
    $gekauftBei = trim((string)($_POST['gekauft_bei'] ?? ''));
    $gelesen = isset($_POST['gelesen']) && $_POST['gelesen'] === '1';

    $regalSelection = trim((string)($_POST['regal_selection'] ?? ''));
    $newRegalValue = trim((string)($_POST['new_regal_value'] ?? ''));
    $regalfachSelection = trim((string)($_POST['regalfach_selection'] ?? ''));
    $newRegalfachValue = trim((string)($_POST['new_regalfach_value'] ?? ''));

    $schuberChecked = isset($_POST['ist_im_schuber']) && $_POST['ist_im_schuber'] === '1';
    $schuberSelection = trim((string)($_POST['schuber_selection'] ?? ''));
    $newSchuberValue = trim((string)($_POST['new_schuber_value'] ?? ''));

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

    $allowedRegalSelections = array_merge(['', '__new__'], $availableRegale);
    $allowedSchuberSelections = array_merge(['', '__new__'], $availableSchuber);

    if (!in_array($regalSelection, $allowedRegalSelections, true))
    {
        $errors[] = 'An invalid shelf was selected.';
    }

    if ($regalSelection === '__new__' && $newRegalValue === '')
    {
        $errors[] = 'Please enter a name for the new shelf.';
    }

    $resolvedRegal = '';

    if ($regalSelection === '__new__')
    {
        $resolvedRegal = $newRegalValue;
    }
    elseif ($regalSelection !== '')
    {
        $resolvedRegal = $regalSelection;
    }

    $resolvedRegal = trim($resolvedRegal);
    $availableFaecherForResolvedRegal = $resolvedRegal !== '' && isset($availableFaecherMap[$resolvedRegal])
        ? $availableFaecherMap[$resolvedRegal]
        : [];
    $allowedFachSelections = array_merge(['', '__new__'], $availableFaecherForResolvedRegal);

    if ($resolvedRegal === '')
    {
        if ($regalfachSelection !== '' || $newRegalfachValue !== '')
        {
            $errors[] = 'A shelf compartment can only be selected when a shelf is specified.';
        }
    }
    else
    {
        if (!in_array($regalfachSelection, $allowedFachSelections, true))
        {
            $errors[] = 'An invalid shelf compartment was selected.';
        }

        if ($regalfachSelection === '__new__' && $newRegalfachValue === '')
        {
            $errors[] = 'Please enter a name for the new compartment.';
        }
    }

    $resolvedRegalfach = '';

    if ($regalfachSelection === '__new__')
    {
        $resolvedRegalfach = $newRegalfachValue;
    }
    elseif ($regalfachSelection !== '')
    {
        $resolvedRegalfach = $regalfachSelection;
    }

    $resolvedRegalfach = trim($resolvedRegalfach);

    if ($schuberChecked)
    {
        if (!in_array($schuberSelection, $allowedSchuberSelections, true))
        {
            $errors[] = 'An invalid slipcase was selected.';
        }

        if ($schuberSelection === '')
        {
            $errors[] = 'Please select a slipcase or create a new one.';
        }

        if ($schuberSelection === '__new__' && $newSchuberValue === '')
        {
            $errors[] = 'Please enter a name for the new slipcase.';
        }
    }

    $resolvedSchuber = '';

    if ($schuberChecked)
    {
        $resolvedSchuber = $schuberSelection === '__new__'
            ? trim($newSchuberValue)
            : trim($schuberSelection);
    }

    if (empty($errors))
    {
        $reiheValue = $reihe !== '' ? $reihe : null;
        $teilValue = $teilDerReihe !== '' ? (int)$teilDerReihe : null;
        $jahrValue = $erscheinungsjahr !== '' ? (int)$erscheinungsjahr : null;
        $gekauftBeiValue = $gekauftBei !== '' ? $gekauftBei : null;
        $newGenresInput = implode(', ', $newGenreNames);
        $location = normalizeLocationPayload($resolvedRegal, $resolvedRegalfach, $schuberChecked, $resolvedSchuber);

        try
        {
            $mysqli = getDatabaseConnection();

            $bookId = createBook(
                $mysqli,
                $autor,
                $titel,
                $reiheValue,
                $teilValue,
                $jahrValue,
                $gekauftBeiValue,
                $selectedGenreIds,
                $newGenresInput,
                $gelesen,
                $location['regal'],
                $location['regalfach'],
                $location['ist_im_schuber'],
                $location['schuber'],
                date('Y-m-d')
            );

            if ($wishlistId > 0)
            {
                deleteWishlistItem($mysqli, $wishlistId);
            }

            $mysqli->close();

            header('Location: index.php?added=1');
            exit;
        }
        catch (Throwable $e)
        {
            if (isset($mysqli) && $mysqli instanceof mysqli)
            {
                $mysqli->close();
            }

            $errors[] = 'The book could not be saved: ' . $e->getMessage();
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

$pageTitle = $wishlistImported ? 'Add book from wish list' : 'Add new book';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
        🌙
    </button>

    <div class="eyebrow">BookDB</div>

    <h1>
        <?php echo $wishlistImported ? 'Add book from wish list' : 'Add new book'; ?>
    </h1>

    <p class="subtitle">
        <?php if ($wishlistImported): ?>
            The wish list data has been copied and can still be adjusted before saving.
        <?php else: ?>
            Enter the data for the new book.
        <?php endif; ?>
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

        <?php if ($wishlistId > 0): ?>
            <input type="hidden" name="wishlist_id" value="<?php echo $wishlistId; ?>">
        <?php endif; ?>

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

            <div class="form-field">
                <label for="gekauft_bei">Purchased from</label>
                <input type="text" id="gekauft_bei" name="gekauft_bei" value="<?php echo htmlspecialchars($gekauftBei); ?>">
            </div>

            <div class="form-field">
                <label for="gelesen">Read status</label>
                <select id="gelesen" name="gelesen">
                    <option value="0" <?php echo !$gelesen ? 'selected' : ''; ?>>Unread</option>
                    <option value="1" <?php echo $gelesen ? 'selected' : ''; ?>>Read</option>
                </select>
            </div>

            <div class="form-divider"></div>

            <div class="form-field">
                <label for="regal_selection">Shelf</label>
                <select id="regal_selection" name="regal_selection">
                    <option value="">No shelf</option>

                    <?php foreach ($availableRegale as $regal): ?>
                        <option value="<?php echo htmlspecialchars($regal); ?>" <?php echo $regalSelection === $regal ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($regal); ?>
                        </option>
                    <?php endforeach; ?>

                    <option value="__new__" <?php echo $regalSelection === '__new__' ? 'selected' : ''; ?>>
                        Add new shelf...
                    </option>
                </select>
            </div>

            <div class="form-field" id="new_regal_field" style="<?php echo $regalSelection === '__new__' ? '' : 'display:none;'; ?>">
                <label for="new_regal_value">New shelf</label>
                <input type="text" id="new_regal_value" name="new_regal_value" value="<?php echo htmlspecialchars($newRegalValue); ?>">
            </div>

            <div class="form-field" id="regalfach_select_field" style="<?php echo ($regalSelection !== '' || $newRegalValue !== '') ? '' : 'display:none;'; ?>">
                <label for="regalfach_selection">Shelf compartment</label>
                <select id="regalfach_selection" name="regalfach_selection">
                    <option value="">No compartment</option>
                </select>
            </div>

            <div class="form-field" id="new_regalfach_field" style="<?php echo $regalfachSelection === '__new__' ? '' : 'display:none;'; ?>">
                <label for="new_regalfach_value">New compartment</label>
                <input type="text" id="new_regalfach_value" name="new_regalfach_value" value="<?php echo htmlspecialchars($newRegalfachValue); ?>">
            </div>

            <div class="form-field" style="grid-column: 1 / -1;">
                <div class="checkbox-row">
                    <label class="checkbox-label" for="ist_im_schuber">
                        <input type="checkbox" id="ist_im_schuber" name="ist_im_schuber" value="1" <?php echo $schuberChecked ? 'checked' : ''; ?>>
                        Slipcase?
                    </label>
                </div>
            </div>

            <div class="form-field" id="schuber_select_field" style="<?php echo $schuberChecked ? '' : 'display:none;'; ?>">
                <label for="schuber_selection">Slipcase</label>
                <select id="schuber_selection" name="schuber_selection">
                    <option value="">Please select</option>

                    <?php foreach ($availableSchuber as $schuber): ?>
                        <option value="<?php echo htmlspecialchars($schuber); ?>" <?php echo $schuberSelection === $schuber ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($schuber); ?>
                        </option>
                    <?php endforeach; ?>

                    <option value="__new__" <?php echo $schuberSelection === '__new__' ? 'selected' : ''; ?>>
                        Add new slipcase...
                    </option>
                </select>
            </div>

            <div class="form-field" id="new_schuber_field" style="<?php echo $schuberChecked && $schuberSelection === '__new__' ? '' : 'display:none;'; ?>">
                <label for="new_schuber_value">New slipcase</label>
                <input type="text" id="new_schuber_value" name="new_schuber_value" value="<?php echo htmlspecialchars($newSchuberValue); ?>">
            </div>

        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save book</button>
            <a class="btn btn-secondary" href="<?php echo $wishlistId > 0 ? 'wishlist_details.php?id=' . $wishlistId : 'index.php'; ?>">Cancel</a>
        </div>

    </form>

</section>

<?php renderBookFormInitScript($availableGenres, $availableFaecherMap, $regalfachSelection); ?>
<?php require __DIR__ . '/footer.php'; ?>
