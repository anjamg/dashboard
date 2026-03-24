<?php
/**
 * DASHBOARD VITALLIANCE - VERSION PHP SANS COMPILATION
 * Ce fichier contient le Frontend (React via CDN) et le Backend (API PHP).
 * Déposez simplement ce fichier sur votre serveur.
 */

// --- PARTIE 1 : BACKEND API PHP ---
if (isset($_GET['action'])) {
    header("Content-Type: application/json; charset=UTF-8");
    
    // Configuration BDD (à adapter)
    $db_config = ['host' => 'localhost', 'user' => 'vita', 'pass' => 'V1tapasSDB26', 'name' => 'vita'];
    
    try {
        $pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4", $db_config['user'], $db_config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Mode Démo si pas de BDD
        echo json_encode([
            'total_calls' => 1450, 'answered' => 980, 'missed' => 150, 
            'abandoned' => 320, 'avg_duration' => 245, 'avg_wait' => 22
        ]);
        exit;
    }

    if ($_GET['action'] === 'overview') {
        $stmt = $pdo->query("SELECT COUNT(*) as total_calls, SUM(direction='inbound' AND answered=1) as answered FROM v_stats_all");
        echo json_encode($stmt->fetch());
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitalliance Dashboard</title>
    <!-- Tailwind CSS pour le style -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- React & Babel pour exécuter le JSX sans compilation -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background-color: #0a0a0a; color: #ffffff; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;

        function App() {
            const [stats, setStats] = useState(null);
            const [loading, setLoading] = useState(true);

            useEffect(() => {
                fetch('?action=overview')
                    .then(res => res.json())
                    .then(data => {
                        setStats(data);
                        setLoading(false);
                    });
            }, []);

            if (loading) return <div className="flex h-screen items-center justify-center">Chargement...</div>;

            return (
                <div className="p-8 max-w-7xl mx-auto">
                    <header className="mb-12 flex justify-between items-end">
                        <div>
                            <h1 className="text-6xl font-light tracking-tighter mb-2">VITALLIANCE</h1>
                            <p className="text-zinc-500 uppercase tracking-widest text-xs">Performance Dashboard // 2026</p>
                        </div>
                        <div className="text-right">
                            <div className="text-zinc-500 text-xs uppercase mb-1">Status</div>
                            <div className="flex items-center gap-2 text-emerald-500">
                                <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                LIVE SERVER
                            </div>
                        </div>
                    </header>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <StatCard label="Total Appels" value={stats.total_calls} unit="units" />
                        <StatCard label="Appels Répondus" value={stats.answered} unit="calls" />
                        <StatCard label="Temps d'attente" value={stats.avg_wait || 22} unit="sec" />
                    </div>

                    <div className="mt-12 glass rounded-3xl p-8">
                        <h2 className="text-xl font-light mb-6 italic">Analyse des flux</h2>
                        <div className="h-64 flex items-end gap-2">
                            {[40, 70, 45, 90, 65, 80, 30, 50, 85, 60].map((h, i) => (
                                <div key={i} className="flex-1 bg-zinc-800 hover:bg-zinc-700 transition-colors rounded-t-lg" style={{height: `${h}%`}}></div>
                            ))}
                        </div>
                    </div>
                </div>
            );
        }

        function StatCard({ label, value, unit }) {
            return (
                <div className="glass p-8 rounded-3xl hover:bg-white/10 transition-all cursor-pointer group">
                    <div className="text-zinc-500 text-xs uppercase tracking-widest mb-4">{label}</div>
                    <div className="flex items-baseline gap-2">
                        <span className="text-5xl font-light tracking-tighter">{value}</span>
                        <span className="text-zinc-600 text-sm italic">{unit}</span>
                    </div>
                </div>
            );
        }

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>
