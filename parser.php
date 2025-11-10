#!/usr/bin/env php
<?php
/**
 * CORRECT CSV Importer
 * Handles literal \n in email content
 */

if ($argc < 2) {
    echo "Usage: php import-correct.php <csv_file> [limit]\n";
    exit(1);
}

$csvFile = $argv[1];
$limit = isset($argv[2]) ? (int)$argv[2] : 0;

if (!file_exists($csvFile)) {
    echo "Error: File not found!\n";
    exit(1);
}

echo "====================================\n";
echo "CSV Importer (Fixes literal \\n)\n";
echo "====================================\n\n";

// Create database
$dbFile = 'database/database.sqlite';


$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create table
$db->exec("
    CREATE TABLE successful_emails (
        id INTEGER PRIMARY KEY,
        affiliate_id INTEGER NOT NULL,
        envelope TEXT NOT NULL,
        `from` VARCHAR(255) NOT NULL,
        subject TEXT NOT NULL,
        dkim VARCHAR(255) DEFAULT NULL,
        SPF VARCHAR(255) DEFAULT NULL,
        spam_score REAL DEFAULT NULL,
        email LONGTEXT NOT NULL,
        raw_text TEXT DEFAULT '',
        sender_ip VARCHAR(50) DEFAULT NULL,
        `to` TEXT NOT NULL,
        timestamp INTEGER NOT NULL
    )
");

$db->exec("CREATE INDEX idx_affiliate ON successful_emails(affiliate_id)");
echo "✓ Table created\n\n";

// Prepare statement
$stmt = $db->prepare("
    INSERT INTO successful_emails (
        id, affiliate_id, envelope, `from`, subject, dkim, SPF,
        spam_score, email, raw_text, sender_ip, `to`, timestamp
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// Open CSV
$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle, 0, "\t", '"', ""); // TAB separated
echo "Reading CSV...\n\n";

$db->beginTransaction();

$imported = 0;
$skipped = 0;

while (($row = fgetcsv($handle, 0, "\t", '"', "")) !== false) {
    if (count($row) < 13) {
        $skipped++;
        continue;
    }

    try {
        // FIX: Convert literal \n and \r to real newlines in email content
        $emailContent = $row[8];
        $emailContent = str_replace('\\n', "\n", $emailContent);
        $emailContent = str_replace('\\r', "\r", $emailContent);

        $data = [
            (int)$row[0],              // id
            (int)$row[1],              // affiliate_id
            $row[2],                   // envelope
            $row[3],                   // from
            $row[4],                   // subject
            $row[5] ?: null,           // dkim
            $row[6] ?: null,           // SPF
            $row[7] ? (float)$row[7] : null, // spam_score
            $emailContent,             // email (FIXED!)
            '',                        // raw_text
            $row[10] ?: null,          // sender_ip
            $row[11],                  // to
            (int)$row[12]              // timestamp
        ];

        $stmt->execute($data);
        $imported++;

        if ($imported % 100 === 0) {
            echo "\rImported: {$imported}...";
        }

        if ($limit > 0 && $imported >= $limit) {
            break;
        }

    } catch (Exception $e) {
        $skipped++;
    }
}

$db->commit();
fclose($handle);

echo "\n\n====================================\n";
echo "Import Complete!\n";
echo "====================================\n";
echo "Imported: {$imported}\n";
echo "Skipped: {$skipped}\n\n";

// Verify
$result = $db->query("
    SELECT id, subject, length(email) as len,
    substr(email, 1, 100) as preview
    FROM successful_emails LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "--- Sample Check ---\n";
    echo "ID: {$result['id']}\n";
    echo "Subject: {$result['subject']}\n";
    echo "Email length: {$result['len']} chars\n";
    echo "Preview: " . substr($result['preview'], 0, 80) . "\n\n";

    // Check if it has real newlines now
    $fullEmail = $db->query("SELECT email FROM successful_emails WHERE id = {$result['id']}")->fetchColumn();
    $hasNewlines = (strpos($fullEmail, "\n") !== false);
    $hasHeaders = (stripos($fullEmail, "Received:") !== false);

    echo "Has real newlines: " . ($hasNewlines ? "✓ YES" : "✗ NO") . "\n";
    echo "Has email headers: " . ($hasHeaders ? "✓ YES" : "✗ NO") . "\n";

    if ($hasNewlines && $hasHeaders && $result['len'] > 500) {
        echo "\n✓✓✓ Email format looks CORRECT! ✓✓✓\n";
    } else {
        echo "\n⚠️  Email format may have issues\n";
    }
}

echo "\n";
