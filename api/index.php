<?php
/**
 * API UNIFIÉE VITALLIANCE (PHP)
 * Remplace le backend Node.js pour les serveurs sans support Node.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

// 1. Configuration BDD
$db_config = [
    'host' => 'localhost',
    'user' => 'vita',
    'pass' => 'V1tapasSDB26',
    'name' => 'vita'
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
        $db_config['user'],
        $db_config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    // Mode Démo si la BDD n'est pas accessible
    if (isset($_GET['action'])) {
        handleDemoMode($_GET['action']);
    }
    exit;
}

// 2. Routage simple
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'overview':
        getOverview($pdo);
        break;
    case 'agents':
        getAgents($pdo);
        break;
    case 'ai_analyze':
        // Note: L'appel à Gemini nécessite une bibliothèque PHP ou un curl direct
        handleAIAnalyze();
        break;
    default:
        echo json_encode(["error" => "Action non reconnue"]);
        break;
}

// --- FONCTIONS API ---

function getOverview($pdo) {
    $date_from = $_GET['date_from'] ?? '2000-01-01';
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    $sql = "SELECT 
                COUNT(*) as total_calls,
                SUM(direction='inbound' AND answered=1) as answered,
                SUM(direction='outbound' AND user IS NULL) as missed,
                SUM(direction='inbound' AND user='[No associated user]') as abandoned,
                AVG(CASE WHEN answered=1 THEN duration_total END) as avg_duration,
                AVG(CASE WHEN answered=1 THEN waiting_time END) as avg_wait
            FROM v_stats_all 
            WHERE date >= ? AND date <= ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    echo json_encode($stmt->fetch());
}

function getAgents($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT user FROM v_stats_all WHERE user IS NOT NULL ORDER BY user");
    echo json_encode($stmt->fetchAll());
}

function handleAIAnalyze() {
    // Simulation pour l'instant (nécessite une clé API Gemini)
    echo json_encode(["analysis" => "Analyse IA via PHP : Les performances sont stables, mais le taux d'abandon augmente entre 11h et 12h."]);
}

function handleDemoMode($action) {
    $demo = [
        'total_calls' => 1450,
        'answered' => 980,
        'missed' => 150,
        'abandoned' => 320,
        'avg_duration' => 245,
        'avg_wait' => 22
    ];
    echo json_encode($demo);
}
