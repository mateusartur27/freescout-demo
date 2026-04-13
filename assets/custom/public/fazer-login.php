<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if (mb_strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Nome inválido']);
    exit;
}

// Parse primeiro e último nome
$parts     = preg_split('/\s+/', $name, 2);
$firstName = mb_convert_case($parts[0], MB_CASE_TITLE, 'UTF-8');
$lastName  = isset($parts[1]) ? mb_convert_case($parts[1], MB_CASE_TITLE, 'UTF-8') : 'Demo';

// Lê credenciais do .env do Laravel
$envPath = '/www/html/.env';
$env     = @file_get_contents($envPath);
if (!$env) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro de configuração']);
    exit;
}
preg_match('/^DB_HOST=(.+)$/m',     $env, $m); $host   = trim($m[1] ?? 'freescout-db');
preg_match('/^DB_PORT=(.+)$/m',     $env, $m); $port   = trim($m[1] ?? '5432');
preg_match('/^DB_DATABASE=(.+)$/m', $env, $m); $dbname = trim($m[1] ?? 'freescout');
preg_match('/^DB_USERNAME=(.+)$/m', $env, $m); $dbuser = trim($m[1] ?? 'freescout');
preg_match('/^DB_PASSWORD=(.+)$/m', $env, $m); $dbpass = trim($m[1] ?? '');

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $dbuser, $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados']);
    exit;
}

// E-mail único derivado do nome (só para autenticação interna da demo)
$email         = strtolower($firstName) . '.' . strtolower(str_replace(' ', '', $lastName)) . '@demo.local';
$demoPassword  = 'Demo@2026!';

// Verifica se já existe
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$userId = $stmt->fetchColumn();

if (!$userId) {
    // Cria conta de agente nova
    $hash = password_hash($demoPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    $now  = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        "INSERT INTO users
            (first_name, last_name, email, password, role, timezone, type,
             invite_state, time_format, enable_kb_shortcuts, locked, status, created_at, updated_at)
         VALUES
            (?, ?, ?, ?, 1, 'America/Sao_Paulo', 1, 1, 2, true, false, 1, ?, ?)
         RETURNING id"
    );
    $stmt->execute([$firstName, $lastName, $email, $hash, $now, $now]);
    $userId = $stmt->fetchColumn();

    // Associa ao mailbox "Suporte Geral" (id=1)
    $stmt = $pdo->prepare(
        "INSERT INTO mailbox_user (mailbox_id, user_id, after_send, hide, mute)
         VALUES (1, ?, 2, false, false)"
    );
    $stmt->execute([$userId]);

    // Cria pastas pessoais Mine (type=20) e Starred (type=25) para o novo agente
    $stmt = $pdo->prepare(
        "INSERT INTO folders (mailbox_id, user_id, type, total_count, active_count)
         VALUES (1, ?, 20, 0, 0), (1, ?, 25, 0, 0)"
    );
    $stmt->execute([$userId, $userId]);
}

echo json_encode(['success' => true, 'email' => $email, 'password' => $demoPassword]);
