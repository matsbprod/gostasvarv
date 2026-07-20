<?php
/**
 * boka.php — Bokningshanterare för Gösta Johanssons Varv
 * Placera denna fil i rotkatalogen på servern.
 * Bokningar sparas i data/bokningar.json
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('DATA_FILE', __DIR__ . '/data/bokningar.json');
define('ADMIN_EMAIL', 'info@gostasvarv.se');

// ── Skapa datakatalog om den saknas ──────────────────────────
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode(['bokningar' => []], JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── GET: hämta bokade datum för en båt ───────────────────────
if ($method === 'GET' && $action === 'hamta') {
    $bat = $_GET['bat'] ?? '';
    $data = json_decode(file_get_contents(DATA_FILE), true);
    $bokade = [];
    foreach ($data['bokningar'] as $b) {
        if ($b['bat'] === $bat && $b['status'] !== 'avvisad') {
            $bokade[] = $b['datum'];
        }
    }
    echo json_encode(['bokade' => $bokade]);
    exit;
}

// ── POST: ny bokning ──────────────────────────────────────────
if ($method === 'POST' && $action === 'boka') {
    $input = json_decode(file_get_contents('php://input'), true);

    $bat    = trim($input['bat'] ?? '');
    $datum  = trim($input['datum'] ?? '');
    $namn   = trim($input['namn'] ?? '');
    $telefon= trim($input['telefon'] ?? '');
    $epost  = trim($input['epost'] ?? '');

    // Validering
    if (!$bat || !$datum || !$namn || !$telefon || !$epost) {
        http_response_code(400);
        echo json_encode(['fel' => 'Fyll i alla obligatoriska fält.']);
        exit;
    }
    if (!in_array($bat, ['Josefin', 'Myra'])) {
        http_response_code(400);
        echo json_encode(['fel' => 'Ogiltig båt.']);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        http_response_code(400);
        echo json_encode(['fel' => 'Ogiltigt datum.']);
        exit;
    }

    // Kolla om datumet redan är bokat
    $data = json_decode(file_get_contents(DATA_FILE), true);
    foreach ($data['bokningar'] as $b) {
        if ($b['bat'] === $bat && $b['datum'] === $datum && $b['status'] !== 'avvisad') {
            http_response_code(409);
            echo json_encode(['fel' => 'Det datumet är redan bokat för ' . $bat . '.']);
            exit;
        }
    }

    // Spara bokning
    $id = uniqid('bok_');
    $bokning = [
        'id'      => $id,
        'bat'     => $bat,
        'datum'   => $datum,
        'namn'    => $namn,
        'telefon' => $telefon,
        'epost'   => $epost,
        'status'  => 'väntar',
        'skapad'  => date('Y-m-d H:i:s'),
    ];
    $data['bokningar'][] = $bokning;
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Mejl till föreningen
    $amne_forening = "=?UTF-8?B?" . base64_encode("Ny bokningsförfrågan: $bat $datum") . "?=";
    $meddelande_forening =
        "Ny bokningsförfrågan har inkommit.\n\n" .
        "Båt:     $bat\n" .
        "Datum:   $datum\n" .
        "Namn:    $namn\n" .
        "Telefon: $telefon\n" .
        "E-post:  $epost\n\n" .
        "Hantera bokningen i adminpanelen:\n" .
        "https://gostasvarv.se/admin.php\n\n" .
        "Boknings-ID: $id";
    $headers_forening = "From: noreply@gostasvarv.se\r\nContent-Type: text/plain; charset=UTF-8";
    mail(ADMIN_EMAIL, $amne_forening, $meddelande_forening, $headers_forening);

    // Bekräftelsemejl till bokaren
    $amne_bokare = "=?UTF-8?B?" . base64_encode("Bokningsförfrågan mottagen – $bat $datum") . "?=";
    $meddelande_bokare =
        "Hej $namn,\n\n" .
        "Vi har mottagit din bokningsförfrågan och återkommer med bekräftelse.\n\n" .
        "Båt:   $bat\n" .
        "Datum: $datum\n\n" .
        "Vid frågor, kontakta oss på " . ADMIN_EMAIL . "\n\n" .
        "Kulturföreningen Gösta Johanssons Varv\n" .
        "Gröna Varvet · Kungsviken · Orust";
    $headers_bokare = "From: " . ADMIN_EMAIL . "\r\nContent-Type: text/plain; charset=UTF-8";
    mail($epost, $amne_bokare, $meddelande_bokare, $headers_bokare);

    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

http_response_code(400);
echo json_encode(['fel' => 'Ogiltig förfrågan.']);
