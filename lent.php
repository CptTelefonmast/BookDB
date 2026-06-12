<?php

/*
|--------------------------------------------------------------------------
| Loan logic
|--------------------------------------------------------------------------
|
| This file contains functions for loading and managing loan records.
|
*/

if (!function_exists('isValidDateString'))
{
    function isValidDateString(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
        {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}

if (!function_exists('getCurrentLoanByBookId'))
{
    function getCurrentLoanByBookId(mysqli $mysqli, int $bookId): ?array
    {
        $sql = "
            SELECT
                id,
                buch_id,
                person,
                verliehen_am,
                zurueckgegeben_am
            FROM ausleihen
            WHERE buch_id = ?
              AND zurueckgegeben_am IS NULL
            ORDER BY verliehen_am DESC, id DESC
            LIMIT 1
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $bookId);
        $stmt->execute();

        $result = $stmt->get_result();
        $loan = $result->fetch_assoc();

        $stmt->close();

        return $loan ?: null;
    }
}

if (!function_exists('getLoanHistoryByBookId'))
{
    function getLoanHistoryByBookId(mysqli $mysqli, int $bookId): array
    {
        $history = [];

        $sql = "
            SELECT
                id,
                buch_id,
                person,
                verliehen_am,
                zurueckgegeben_am
            FROM ausleihen
            WHERE buch_id = ?
            ORDER BY verliehen_am DESC, id DESC
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $bookId);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc())
        {
            $history[] = $row;
        }

        $stmt->close();

        return $history;
    }
}

if (!function_exists('lendBook'))
{
    function lendBook(mysqli $mysqli, int $bookId, string $person, string $lentAt): void
    {
        $person = trim($person);

        if ($person === '')
        {
            throw new RuntimeException('Please enter a person.');
        }

        if (!isValidDateString($lentAt))
        {
            throw new RuntimeException('Please enter a valid loan date.');
        }

        $currentLoan = getCurrentLoanByBookId($mysqli, $bookId);

        if ($currentLoan !== null)
        {
            throw new RuntimeException('This book is already lent out.');
        }

        $sql = "
            INSERT INTO ausleihen
            (
                buch_id,
                person,
                verliehen_am
            )
            VALUES
            (
                ?,
                ?,
                ?
            )
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("iss", $bookId, $person, $lentAt);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('returnBook'))
{
    function returnBook(mysqli $mysqli, int $bookId, string $returnedAt): void
    {
        if (!isValidDateString($returnedAt))
        {
            throw new RuntimeException('Please enter a valid return date.');
        }

        $currentLoan = getCurrentLoanByBookId($mysqli, $bookId);

        if ($currentLoan === null)
        {
            throw new RuntimeException('There is no open loan for this book.');
        }

        $lentAt = (string)$currentLoan['verliehen_am'];

        if ($returnedAt < $lentAt)
        {
            throw new RuntimeException('The return date must not be earlier than the loan date.');
        }

        $sql = "
            UPDATE ausleihen
            SET zurueckgegeben_am = ?
            WHERE id = ?
              AND zurueckgegeben_am IS NULL
            LIMIT 1
        ";

        $loanId = (int)$currentLoan['id'];

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("si", $returnedAt, $loanId);
        $stmt->execute();
        $stmt->close();
    }
}