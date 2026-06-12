<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/db_connect.php';

if (PHP_SAPI !== 'cli')
{
    fwrite(STDERR, "This script is intended for command-line use only.\n");
    exit(1);
}

$outputFile = $argv[1] ?? (__DIR__ . '/bookdb_export_' . date('Y-m-d_H-i-s') . '.csv');

try
{
    $mysqli = getDatabaseConnection();
    $mysqli->set_charset('utf8mb4');

    $hasBuchGenres = tableExists($mysqli, 'buch_genres');
    $hasGenres = tableExists($mysqli, 'genres');
    $hasBuchStandorte = tableExists($mysqli, 'buch_standorte');
    $hasAusleihen = tableExists($mysqli, 'ausleihen');

    $sql = buildExportQuery(
        $hasBuchGenres,
        $hasGenres,
        $hasBuchStandorte,
        $hasAusleihen
    );

    $result = $mysqli->query($sql);

    $handle = fopen($outputFile, 'wb');

    if ($handle === false)
    {
        throw new RuntimeException('The output file could not be opened: ' . $outputFile);
    }

    // UTF-8 BOM for better Excel compatibility
    fwrite($handle, "\xEF\xBB\xBF");

    $headerWritten = false;

    while ($row = $result->fetch_assoc())
    {
        if (!$headerWritten)
        {
            fputcsv($handle, array_keys($row), ';');
            $headerWritten = true;
        }

        $normalizedRow = [];

        foreach ($row as $value)
        {
            if (is_bool($value))
            {
                $normalizedRow[] = $value ? '1' : '0';
                continue;
            }

            if ($value === null)
            {
                $normalizedRow[] = '';
                continue;
            }

            $normalizedRow[] = (string)$value;
        }

        fputcsv($handle, $normalizedRow, ';');
    }

    if (!$headerWritten)
    {
        $header = [
            'id',
            'autor',
            'titel',
            'reihe',
            'teil_der_reihe',
            'erscheinungsjahr',
            'gelesen',
            'gekauft_bei'
        ];

        if ($hasBuchGenres && $hasGenres)
        {
            $header[] = 'genres';
        }

        if ($hasBuchStandorte)
        {
            $header[] = 'regal';
            $header[] = 'regalfach';
            $header[] = 'ist_im_schuber';
            $header[] = 'schuber';
            $header[] = 'standort_seit';
        }

        if ($hasAusleihen)
        {
            $header[] = 'verliehen_an';
            $header[] = 'verliehen_am';
        }

        fputcsv($handle, $header, ';');
    }

    fclose($handle);
    $result->free();
    $mysqli->close();

    fwrite(STDOUT, "Export created successfully: " . $outputFile . "\n");
    exit(0);
}
catch (Throwable $e)
{
    if (isset($handle) && is_resource($handle))
    {
        fclose($handle);
    }

    if (isset($mysqli) && $mysqli instanceof mysqli)
    {
        $mysqli->close();
    }

    fwrite(STDERR, "Export failed: " . $e->getMessage() . "\n");
    exit(1);
}

function tableExists(mysqli $mysqli, string $tableName): bool
{
    $sql = "
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $tableName);
    $stmt->execute();

    $result = $stmt->get_result();
    $exists = $result->fetch_row() !== null;

    $stmt->close();

    return $exists;
}

function buildExportQuery(
    bool $hasBuchGenres,
    bool $hasGenres,
    bool $hasBuchStandorte,
    bool $hasAusleihen
): string
{
    $select = [
        'b.id',
        'b.autor',
        'b.titel',
        'b.reihe',
        'b.teil_der_reihe',
        'b.erscheinungsjahr',
        'b.gelesen',
        'b.gekauft_bei'
    ];

    $joins = [];
    $groupBy = [
        'b.id',
        'b.autor',
        'b.titel',
        'b.reihe',
        'b.teil_der_reihe',
        'b.erscheinungsjahr',
        'b.gelesen',
        'b.gekauft_bei'
    ];

    if ($hasBuchGenres && $hasGenres)
    {
        $select[] = "COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') AS genres";

        $joins[] = "
            LEFT JOIN buch_genres bg
                ON bg.buch_id = b.id
        ";

        $joins[] = "
            LEFT JOIN genres g
                ON g.id = bg.genre_id
        ";
    }

    if ($hasBuchStandorte)
    {
        $select[] = 'bs.regal';
        $select[] = 'bs.regalfach';
        $select[] = 'bs.ist_im_schuber';
        $select[] = 'bs.schuber';
        $select[] = 'bs.standort_seit';

        $joins[] = "
            LEFT JOIN buch_standorte bs
                ON bs.buch_id = b.id
               AND bs.standort_bis IS NULL
        ";

        $groupBy[] = 'bs.regal';
        $groupBy[] = 'bs.regalfach';
        $groupBy[] = 'bs.ist_im_schuber';
        $groupBy[] = 'bs.schuber';
        $groupBy[] = 'bs.standort_seit';
    }

    if ($hasAusleihen)
    {
        $select[] = 'a.person AS verliehen_an';
        $select[] = 'a.verliehen_am';

        $joins[] = "
            LEFT JOIN ausleihen a
                ON a.buch_id = b.id
               AND a.zurueckgegeben_am IS NULL
        ";

        $groupBy[] = 'a.person';
        $groupBy[] = 'a.verliehen_am';
    }

    return "
        SELECT
            " . implode(",\n            ", $select) . "
        FROM buecher b
        " . implode("\n", $joins) . "
        GROUP BY
            " . implode(",\n            ", $groupBy) . "
        ORDER BY
            b.autor ASC,
            b.reihe ASC,
            b.teil_der_reihe ASC,
            b.titel ASC
    ";
}
