<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name    = trim($_POST['name']    ?? '');
$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['body']    ?? '');

if (!$name || !$subject || !$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Preencha todos os campos.']);
    exit;
}

// Ler credenciais do .env do FreeScout
$env = [];
foreach (file('/www/html/.env') as $line) {
    $line = trim($line);
    if ($line && $line[0] !== '#' && strpos($line, '=') !== false) {
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B'\"");
    }
}

$dbHost = $env['DB_HOST']     ?? 'freescout-db';
$dbPort = $env['DB_PORT']     ?? '5432';
$dbName = $env['DB_DATABASE'] ?? 'freescout';
$dbUser = $env['DB_USERNAME'] ?? 'freescout';
$dbPass = $env['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}",
        $dbUser, $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro de conexão com banco.']);
    exit;
}

$now = date('Y-m-d H:i:s');

// Email fictício baseado no nome
$emailBase = strtolower(preg_replace('/\s+/', '.', preg_replace('/[^a-zA-Z\s]/', '', $name)));
if (!$emailBase) $emailBase = 'usuario';
$email = $emailBase . '@demo.local';

// Nome dividido
$parts     = explode(' ', $name, 2);
$firstName = $parts[0];
$lastName  = isset($parts[1]) ? $parts[1] : '';

// Criar ou reutilizar cliente
$stmt = $pdo->prepare('SELECT customer_id FROM emails WHERE email = ?');
$stmt->execute([$email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $customerId = $row['customer_id'];
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO customers (first_name, last_name, created_at, updated_at) VALUES (?, ?, ?, ?) RETURNING id'
    );
    $stmt->execute([$firstName, $lastName, $now, $now]);
    $customerId = $stmt->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO emails (customer_id, email, type) VALUES (?, ?, 1)');
    $stmt->execute([$customerId, $email]);
}

// Pasta Unassigned do mailbox 1
$stmt = $pdo->prepare('SELECT id FROM folders WHERE mailbox_id = 1 AND type = 1 LIMIT 1');
$stmt->execute();
$folder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$folder) {
    http_response_code(500);
    echo json_encode(['error' => 'Mailbox não configurado.']);
    exit;
}
$folderId = $folder['id'];

// Próximo número de ticket
$maxNum = $pdo->query('SELECT COALESCE(MAX(number), 0) FROM conversations')->fetchColumn();
$number = (int)$maxNum + 1;

$preview = mb_substr(strip_tags($body), 0, 255);

// Criar conversa
$stmt = $pdo->prepare(
    'INSERT INTO conversations
     (number, type, folder_id, status, state, subject, customer_email, preview,
      mailbox_id, customer_id, created_by_customer_id, source_via, source_type,
      threads_count, last_reply_at, last_reply_from, created_at, updated_at)
     VALUES (?,1,?,1,2,?,?,?,1,?,?,1,2,1,?,1,?,?)
     RETURNING id'
);
$stmt->execute([$number, $folderId, $subject, $email, $preview, $customerId, $customerId, $now, $now, $now]);
$convId = $stmt->fetchColumn();

// Corpo HTML seguro
$bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

// Criar thread
$stmt = $pdo->prepare(
    'INSERT INTO threads
     (conversation_id, type, status, state, body, "from", source_via, source_type,
      customer_id, created_by_customer_id, first, created_at, updated_at)
     VALUES (?,1,1,2,?,?,1,2,?,?,true,?,?)'
);
$stmt->execute([$convId, $bodyHtml, $email, $customerId, $customerId, $now, $now]);

// Associar à pasta
$stmt = $pdo->prepare('INSERT INTO conversation_folder (folder_id, conversation_id) VALUES (?, ?)');
$stmt->execute([$folderId, $convId]);

// Atualizar contador da pasta
$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM conversation_folder cf
     JOIN conversations c ON cf.conversation_id = c.id
     WHERE cf.folder_id = ? AND c.status = 1 AND c.state = 2'
);
$stmt->execute([$folderId]);
$count = $stmt->fetchColumn();
$pdo->prepare('UPDATE folders SET active_count = ? WHERE id = ?')->execute([$count, $folderId]);

echo json_encode(['success' => true, 'ticket' => $number]);

