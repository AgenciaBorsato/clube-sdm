<?php
// ============================================================
// CLUBE SDM - Conexao e Utilitarios Base
// ============================================================

// Sessao persistente e explicita (corrige logout ao atualizar a pagina).
// Atras do proxy HTTPS do Railway, detecta HTTPS via X-Forwarded-Proto.
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
    ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 7); // 7 dias
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7, // 7 dias (cookie persistente, sobrevive a refresh/fechar aba)
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $databaseUrl = getenv('DATABASE_URL');
            if ($databaseUrl) {
                $parsed = parse_url($databaseUrl);
                $host = $parsed['host'];
                $port = $parsed['port'] ?? 5432;
                $dbname = ltrim($parsed['path'], '/');
                $user = $parsed['user'];
                $pass = $parsed['pass'];
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
            } else {
                $host = getenv('DB_HOST') ?: 'localhost';
                $port = getenv('DB_PORT') ?: '5432';
                $dbname = getenv('DB_NAME') ?: 'clube_sdm';
                $user = getenv('DB_USER') ?: 'postgres';
                $pass = getenv('DB_PASS') ?: '';
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            }
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['erro' => 'Erro de conexao com o banco de dados']);
            exit;
        }
    }
    return $pdo;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?? [];
}

function getClientIP() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ===== INICIALIZACAO =====
function inicializarBanco() {
    $db = getDB();
    try {
        $db->query("SELECT 1 FROM clubs LIMIT 1");
    } catch (PDOException $e) {
        $sql = file_get_contents(__DIR__ . '/../database.sql');
        $db->exec($sql);
    }
    // Criar super admin padrao se nao existe
    $stmt = $db->query("SELECT COUNT(*) as c FROM users WHERE role = 'SUPER_ADMIN'");
    if ($stmt->fetch()['c'] == 0) {
        $hash = password_hash('clubesdm2026', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (nome, email, password_hash, role, club_id) VALUES (?, ?, ?, 'SUPER_ADMIN', NULL)")
           ->execute(['Administrador', 'admin@clubesdm.com', $hash]);
    }
}
inicializarBanco();
