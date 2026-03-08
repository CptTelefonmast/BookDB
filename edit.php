<?php

/*
|--------------------------------------------------------------------------
| Bearbeitungsseite für ein einzelnes Buch
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/functions.php';

function normalizeGenreKey(string $value): string
{
    if (function_exists('mb_strtolower'))
    {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function renderGenreItem(array $availableGenres, string $selectedValue, string $newGenreValue, int $index): void
{
    ?>
    <div class="genre-item" data-index="<?php echo $index; ?>">

        <div class="form-field">
            <label for="genre_selection_<?php echo $index; ?>">
                Genre <?php echo $index + 1; ?>
            </label>

            <select
                id="genre_selection_<?php echo $index; ?>"
                name="genre_selections[]"
                class="genre-selection"
            >
                <option value="">Bitte wählen</option>

                <?php foreach ($availableGenres as $availableGenre): ?>
                    <option
                        value="<?php echo (int)$availableGenre['id']; ?>"
                        <?php echo $selectedValue === (string)$availableGenre['id'] ? 'selected' : ''; ?>
                    >
                        <?php echo htmlspecialchars($availableGenre['name']); ?>
                    </option>
                <?php endforeach; ?>

                <option value="__new__" <?php echo $selectedValue === '__new__' ? 'selected' : ''; ?>>
                    Neues Genre hinzufügen...
                </option>
            </select>
        </div>

        <div
            class="form-field genre-new-field"
            style="<?php echo $selectedValue === '__new__' ? '' : 'display:none;'; ?>"
        >
            <label for="new_genre_value_<?php echo $index; ?>">
                Neues Genre <?php echo $index + 1; ?>
            </label>

            <input
                type="text"
                id="new_genre_value_<?php echo $index; ?>"
                name="new_genre_values[]"
                class="new-genre-value"
                value="<?php echo htmlspecialchars($newGenreValue); ?>"
            >
        </div>

    </div>
    <?php
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0)
{
    die('Ungültige Buch-ID.');
}

$errors = [];
$showDeleteConfirmation = false;
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
}
catch (mysqli_sql_exception $e)
{
    renderDatabaseErrorPage($e);
    exit;
}

try
{
    $book = getBookById($mysqli, $id);
    $availableGenres = getAllGenres($mysqli);

    $validGenreIds = array_map(
        static function (array $genre): string
        {
            return (string)$genre['id'];
        },
        $availableGenres
    );

    if ($book === null)
    {
        $mysqli->close();
        die('Buch nicht gefunden.');
    }
}
catch (mysqli_sql_exception $e)
{
    $mysqli->close();
    renderQueryErrorPage($e);
    exit;
}

$autor = (string)($book['autor'] ?? '');
$titel = (string)($book['titel'] ?? '');
$reihe = (string)($book['reihe'] ?? '');
$teilDerReihe = ($book['teil_der_reihe'] !== null && (string)$book['teil_der_reihe'] !== '0')
    ? (string)$book['teil_der_reihe']
    : '';
$erscheinungsjahr = ($book['erscheinungsjahr'] !== null && (string)$book['erscheinungsjahr'] !== '0000')
    ? (string)$book['erscheinungsjahr']
    : '';

$existingGenreIds = array_map('strval', $book['genre_ids'] ?? []);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = $_POST['action'] ?? 'save';

    if ($action === 'confirm_delete')
    {
        $showDeleteConfirmation = true;
    }
    elseif ($action === 'delete')
    {
        $deleteToken = $_POST['delete_token'] ?? '';

        if ($deleteToken !== 'confirmed')
        {
            $errors[] = 'Die Löschung wurde nicht korrekt bestätigt.';
            $showDeleteConfirmation = true;
        }
        else
        {
            try
            {
                deleteBook($mysqli, $id);
                $mysqli->close();

                header('Location: index.php?deleted=1');
                exit;
            }
            catch (mysqli_sql_exception $e)
            {
                $errors[] = 'Das Buch konnte nicht gelöscht werden: ' . $e->getMessage();
                $showDeleteConfirmation = true;
            }
        }
    }
    else
    {
        $autor = trim($_POST['autor'] ?? '');
        $titel = trim($_POST['titel'] ?? '');
        $reihe = trim($_POST['reihe'] ?? '');
        $teilDerReihe = trim($_POST['teil_der_reihe'] ?? '');
        $erscheinungsjahr = trim($_POST['erscheinungsjahr'] ?? '');

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
            $errors[] = 'Bitte einen Autor eingeben.';
        }

        if ($titel === '')
        {
            $errors[] = 'Bitte einen Titel eingeben.';
        }

        if ($teilDerReihe !== '' && !ctype_digit($teilDerReihe))
        {
            $errors[] = 'Teil der Reihe muss eine ganze Zahl sein.';
        }

        if ($erscheinungsjahr !== '' && !preg_match('/^\d{1,4}$/', $erscheinungsjahr))
        {
            $errors[] = 'Erscheinungsjahr darf maximal vierstellig sein.';
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
                $errors[] = 'Für Genre ' . $position . ' wurde ein ungültiger Wert gewählt.';
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
                    $errors[] = 'Bitte für Genre ' . $position . ' einen neuen Genre-Namen eingeben.';
                    continue;
                }

                $newValueKey = normalizeGenreKey($newValue);

                if (isset($newGenreNamesSeen[$newValueKey]))
                {
                    $errors[] = 'Das neue Genre „' . htmlspecialchars($newValue) . '“ wurde mehrfach eingetragen.';
                    continue;
                }

                $newGenreNamesSeen[$newValueKey] = true;
                $newGenreNames[] = $newValue;

                continue;
            }

            if (isset($selectedGenreIdsSeen[$selection]))
            {
                $errors[] = 'Bitte nicht mehrfach dasselbe vorhandene Genre auswählen.';
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
                updateBook(
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

                header('Location: details.php?id=' . $id . '&updated=1');
                exit;
            }
            catch (mysqli_sql_exception $e)
            {
                $errors[] = 'Das Buch konnte nicht gespeichert werden: ' . $e->getMessage();
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
}

$mysqli->close();

$pageTitle = $titel . ' – Bearbeiten';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Farbschema umschalten">
        🌙
    </button>

    <div class="eyebrow">BookDB</div>

    <h1>Buch bearbeiten</h1>

    <p class="subtitle">
        Daten des Buches bearbeiten.
    </p>

</section>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Bitte prüfe deine Eingaben:</strong>
        <ul class="alert-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="form-card">

    <form class="book-form" method="post" action="">

        <input type="hidden" name="action" value="save">

        <div class="form-grid">

            <div class="form-field">
                <label for="autor">Autor *</label>
                <input
                    type="text"
                    id="autor"
                    name="autor"
                    value="<?php echo htmlspecialchars($autor); ?>"
                    required
                >
            </div>

            <div class="form-field">
                <label for="titel">Titel *</label>
                <input
                    type="text"
                    id="titel"
                    name="titel"
                    value="<?php echo htmlspecialchars($titel); ?>"
                    required
                >
            </div>

            <div class="form-field">
                <label for="reihe">Reihe</label>
                <input
                    type="text"
                    id="reihe"
                    name="reihe"
                    value="<?php echo htmlspecialchars($reihe); ?>"
                >
            </div>

            <div class="form-field">
                <label for="teil_der_reihe">Teil der Reihe</label>
                <input
                    type="number"
                    id="teil_der_reihe"
                    name="teil_der_reihe"
                    min="1"
                    step="1"
                    value="<?php echo htmlspecialchars($teilDerReihe); ?>"
                >
            </div>

            <div style="grid-column: 1 / -1;">

                <div id="genre-rows" class="genre-grid">
                    <?php foreach ($genreRows as $index => $genreRow): ?>
                        <?php renderGenreItem($availableGenres, $genreRow['selection'], $genreRow['new_value'], $index); ?>
                    <?php endforeach; ?>
                </div>

            </div>

            <div class="form-field">
                <label for="erscheinungsjahr">Erscheinungsjahr</label>
                <input
                    type="number"
                    id="erscheinungsjahr"
                    name="erscheinungsjahr"
                    min="0"
                    max="9999"
                    step="1"
                    value="<?php echo htmlspecialchars($erscheinungsjahr); ?>"
                >
            </div>

        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                Änderungen speichern
            </button>

            <a class="btn btn-secondary" href="details.php?id=<?php echo $id; ?>">
                Zurück zur Detailseite
            </a>
        </div>

    </form>

</section>

<section class="danger-card">

    <div class="danger-title">Gefahrenzone</div>

    <p class="danger-text">
        Das Löschen eines Buches kann nicht rückgängig gemacht werden.
    </p>

    <?php if (!$showDeleteConfirmation): ?>

        <form method="post" action="">
            <input type="hidden" name="action" value="confirm_delete">
            <button class="btn btn-danger" type="submit">
                Buch löschen
            </button>
        </form>

    <?php else: ?>

        <div class="alert alert-error">
            <strong>Bitte bestätigen:</strong>
            Wenn du jetzt fortfährst, wird dieses Buch endgültig gelöscht.
        </div>

        <div class="form-actions">

            <form method="post" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="delete_token" value="confirmed">
                <button class="btn btn-danger" type="submit">
                    Ja, Buch endgültig löschen
                </button>
            </form>

            <a class="btn btn-secondary" href="edit.php?id=<?php echo $id; ?>">
                Abbrechen
            </a>

        </div>

    <?php endif; ?>

</section>

<script>
document.addEventListener('DOMContentLoaded', function ()
{
    const genreRowsContainer = document.getElementById('genre-rows');

    function createLabel(text, htmlFor)
    {
        const label = document.createElement('label');
        label.setAttribute('for', htmlFor);
        label.textContent = text;
        return label;
    }

    function createGenreItem(index)
    {
        const item = document.createElement('div');
        item.className = 'genre-item';
        item.dataset.index = String(index);

        const selectField = document.createElement('div');
        selectField.className = 'form-field';

        const selectId = 'genre_selection_' + index;
        const inputId = 'new_genre_value_' + index;

        selectField.appendChild(createLabel('Genre ' + (index + 1), selectId));

        const select = document.createElement('select');
        select.id = selectId;
        select.name = 'genre_selections[]';
        select.className = 'genre-selection';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'Bitte wählen';
        select.appendChild(emptyOption);

        <?php foreach ($availableGenres as $availableGenre): ?>
            {
                const option = document.createElement('option');
                option.value = '<?php echo (int)$availableGenre['id']; ?>';
                option.textContent = <?php echo json_encode($availableGenre['name']); ?>;
                select.appendChild(option);
            }
        <?php endforeach; ?>

        {
            const newOption = document.createElement('option');
            newOption.value = '__new__';
            newOption.textContent = 'Neues Genre hinzufügen...';
            select.appendChild(newOption);
        }

        selectField.appendChild(select);

        const newField = document.createElement('div');
        newField.className = 'form-field genre-new-field';
        newField.style.display = 'none';

        newField.appendChild(createLabel('Neues Genre ' + (index + 1), inputId));

        const input = document.createElement('input');
        input.type = 'text';
        input.id = inputId;
        input.name = 'new_genre_values[]';
        input.className = 'new-genre-value';

        newField.appendChild(input);

        item.appendChild(selectField);
        item.appendChild(newField);

        return item;
    }

    function getItems()
    {
        return Array.from(genreRowsContainer.querySelectorAll('.genre-item'));
    }

    function updateLabels()
    {
        getItems().forEach(function (item, index)
        {
            item.dataset.index = String(index);

            const select = item.querySelector('.genre-selection');
            const input = item.querySelector('.new-genre-value');
            const labels = item.querySelectorAll('label');

            const selectId = 'genre_selection_' + index;
            const inputId = 'new_genre_value_' + index;

            labels[0].setAttribute('for', selectId);
            labels[0].textContent = 'Genre ' + (index + 1);

            labels[1].setAttribute('for', inputId);
            labels[1].textContent = 'Neues Genre ' + (index + 1);

            select.id = selectId;
            input.id = inputId;
        });
    }

    function updateNewGenreVisibility()
    {
        getItems().forEach(function (item)
        {
            const select = item.querySelector('.genre-selection');
            const newField = item.querySelector('.genre-new-field');

            newField.style.display = select.value === '__new__' ? '' : 'none';
        });
    }

    function ensureTrailingEmptyItem()
    {
        const items = getItems();

        if (items.length === 0)
        {
            genreRowsContainer.appendChild(createGenreItem(0));
            return;
        }

        const lastItem = items[items.length - 1];
        const lastSelect = lastItem.querySelector('.genre-selection');
        const lastInput = lastItem.querySelector('.new-genre-value');

        if (lastSelect.value !== '' || lastInput.value.trim() !== '')
        {
            genreRowsContainer.appendChild(createGenreItem(items.length));
        }
    }

    function cleanupTrailingEmptyItems()
    {
        let items = getItems();

        while (items.length > 1)
        {
            const lastItem = items[items.length - 1];
            const previousItem = items[items.length - 2];

            const lastSelect = lastItem.querySelector('.genre-selection');
            const lastInput = lastItem.querySelector('.new-genre-value');

            const previousSelect = previousItem.querySelector('.genre-selection');
            const previousInput = previousItem.querySelector('.new-genre-value');

            const lastIsEmpty = lastSelect.value === '' && lastInput.value.trim() === '';
            const previousIsEmpty = previousSelect.value === '' && previousInput.value.trim() === '';

            if (!(lastIsEmpty && previousIsEmpty))
            {
                break;
            }

            lastItem.remove();
            items = getItems();
        }
    }

    function syncGenreItems()
    {
        updateNewGenreVisibility();
        cleanupTrailingEmptyItems();
        ensureTrailingEmptyItem();
        updateLabels();
    }

    genreRowsContainer.addEventListener('change', function (event)
    {
        if (event.target.classList.contains('genre-selection'))
        {
            syncGenreItems();
        }
    });

    genreRowsContainer.addEventListener('input', function (event)
    {
        if (event.target.classList.contains('new-genre-value'))
        {
            syncGenreItems();
        }
    });

    syncGenreItems();
});
</script>

<?php require __DIR__ . '/footer.php'; ?>