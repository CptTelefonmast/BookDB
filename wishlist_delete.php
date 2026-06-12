<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/books.php';
require_once __DIR__ . '/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$errors = [];

if ($id <= 0)
{
    die('Invalid wish list ID.');
}

try
{
    $mysqli = getDatabaseConnection();
    $item = getWishlistItemById($mysqli, $id);

    if ($item === null)
    {
        $mysqli->close();
        die('Wish list entry not found.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST')
    {
        $confirm = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1';

        if (!$confirm)
        {
            $errors[] = 'Please confirm deletion.';
        }
        else
        {
            deleteWishlistItem($mysqli, $id);
            $mysqli->close();

            header('Location: wishlist.php?deleted=1');
            exit;
        }
    }

    $mysqli->close();
}
catch (Throwable $e)
{
    if (isset($mysqli) && $mysqli instanceof mysqli)
    {
        $mysqli->close();
    }

    $errors[] = 'The wish list entry could not be deleted: ' . $e->getMessage();
}

$pageTitle = 'Delete wish list entry';
require __DIR__ . '/header.php';
?>

<section class="hero">

    <button id="themeToggle" class="btn btn-secondary theme-toggle" type="button" aria-label="Toggle color scheme">
        🌙
    </button>

    <div class="eyebrow">Wish list</div>

    <h1>Delete wish list entry</h1>

    <p class="subtitle">
        Please check once more whether this entry should really be removed.
    </p>

</section>

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

<section class="danger-card">

    <div class="details-grid">

        <div class="details-item">
            <div class="details-label">Author</div>
            <div class="details-value"><?php echo htmlspecialchars((string)$item['autor']); ?></div>
        </div>

        <div class="details-item">
            <div class="details-label">Title</div>
            <div class="details-value"><?php echo htmlspecialchars((string)$item['titel']); ?></div>
        </div>

    </div>

    <form class="book-form" method="post" action="">

        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">

        <div class="checkbox-row" style="margin-top: 18px;">
            <label class="checkbox-label" for="confirm_delete">
                <input type="checkbox" id="confirm_delete" name="confirm_delete" value="1">
                I want to permanently delete this wish list entry.
            </label>
        </div>

        <div class="form-actions" style="margin-top: 18px;">
            <button class="btn btn-danger" type="submit">Delete entry</button>
            <a class="btn btn-secondary" href="wishlist_details.php?id=<?php echo (int)$id; ?>">Cancel</a>
        </div>

    </form>

</section>

<?php require __DIR__ . '/footer.php'; ?>
