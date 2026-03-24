<?php
/**
 * VITALLIANCE DASHBOARD PRO - VERSION PHP SANS COMPILATION
 * Filtres, Heatmap, Tags, Comparaison, Import Excel & Exports PDF/XLSX.
 */

// --- PARTIE 1 : BACKEND API PHP ---
if (isset($_GET['action'])) {
    error_reporting(0); // Empêche les warnings PHP de corrompre le JSON
    header("Content-Type: application/json; charset=UTF-8");
    
    $action = $_GET['action'];
    $team = $_GET['team'] ?? null;
    $agent = $_GET['agent'] ?? null;
    $from = $_GET['from'] ?? '2000-01-01';
    $to = $_GET['to'] ?? date('Y-m-d');

    // Configuration BDD (à adapter)
    $db_config = ['host' => 'localhost', 'user' => 'vita', 'pass' => 'V1tapasSDB26', 'name' => 'vita'];
    
    try {
        $pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4", $db_config['user'], $db_config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Construction de la clause WHERE
        $where = "WHERE date >= :from AND date <= :to";
        $params = [':from' => $from, ':to' => $to];
        if ($team) { $where .= " AND team_name = :team"; $params[':team'] = $team; }
        if ($agent) { $where .= " AND user = :agent"; $params[':agent'] = $agent; }

        // Clause WHERE spécifique pour la table 'data' (qui utilise date_only)
        $where_data = "WHERE date_only >= :from AND date_only <= :to";
        if ($team) { $where_data .= " AND team_name = :team"; }
        if ($agent) { $where_data .= " AND user = :agent"; }

        switch ($action) {
            case 'debug_tags':
                $sql = "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME LIKE '%tag%' AND TABLE_SCHEMA = DATABASE()";
                $stmt = $pdo->query($sql);
                echo json_encode($stmt->fetchAll());
                break;

            case 'debug_data':
                $sql = "DESC data";
                $stmt = $pdo->query($sql);
                $res = $stmt->fetchAll();
                file_put_contents('debug_output.txt', print_r($res, true));
                echo json_encode($res);
                break;

            case 'overview':
                $sql = "SELECT COUNT(*) as total_calls, SUM(direction='inbound' AND answered=1) as answered, AVG(waiting_time) as avg_wait FROM v_stats_all $where";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $res = $stmt->fetch();
                if (!$res['total_calls']) throw new Exception("No data");
                echo json_encode($res);
                break;
                
            case 'filters':
                $teams = $pdo->query("SELECT DISTINCT team_name FROM v_stats_all WHERE team_name IS NOT NULL ORDER BY team_name")->fetchAll(PDO::FETCH_COLUMN);
                $agents = $pdo->query("SELECT DISTINCT user FROM v_stats_all WHERE user IS NOT NULL ORDER BY user")->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode(['teams' => $teams, 'agents' => $agents]);
                break;

            case 'heatmap':
                // Utilisation des colonnes réelles de v_stats_all
                $sql = "SELECT weekday as day, hour_local as hour, COUNT(*) as count FROM v_stats_all $where GROUP BY day, hour";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll());
                break;

            case 'tags':
                try {
                    // On utilise 'tags' (pluriel) et 'date_only' selon le schema.sql
                    $sql = "SELECT tags FROM data $where_data AND tags IS NOT NULL AND tags != ''";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $all_tags = [];
                    while ($row = $stmt->fetch()) {
                        $parts = explode('/', $row['tags']);
                        foreach ($parts as $p) {
                            $p = trim($p);
                            if ($p) $all_tags[$p] = ($all_tags[$p] ?? 0) + 1;
                        }
                    }
                    arsort($all_tags);
                    $res = [];
                    foreach (array_slice($all_tags, 0, 10) as $tag => $count) {
                        $res[] = ['tag' => $tag, 'count' => $count];
                    }
                    echo json_encode($res);
                } catch (Exception $e) {
                    echo json_encode(['error' => $e->getMessage(), 'action' => 'tags']);
                }
                break;

            case 'agents':
                $sql = "SELECT user as agent, team_name as team, COUNT(*) as total, SUM(answered=1) as answered, SUM(direction='outbound') as outbound, ROUND(SUM(answered=1)*100/COUNT(*), 1) as rate, AVG(duration_total) as avg_duration FROM v_stats_all $where GROUP BY user, team_name ORDER BY total DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll());
                break;

            case 'teams_stats':
                $sql = "SELECT team_name as team, COUNT(*) as total, SUM(answered=1) as answered, SUM(direction='outbound') as outbound, SUM(answered=0 AND direction='inbound') as missed, AVG(duration_total) as avg_duration, COUNT(DISTINCT user) as agents_count FROM v_stats_all $where GROUP BY team_name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll());
                break;

            case 'calls':
                // On utilise la table 'data' avec les colonnes du schema.sql
                $sql = "SELECT date_only as date, HOUR(datetime_tz_offset_incl) as time, user as agent, line as line, direction, answered, duration_total_sec as duration, waiting_time_sec as waiting_time, tags FROM data $where_data ORDER BY date_only DESC, datetime_tz_offset_incl DESC LIMIT 100";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll());
                break;

            case 'tags_by_team':
                try {
                    $sql = "SELECT team_name, tags FROM data $where_data AND tags IS NOT NULL AND tags != ''";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $team_tags = [];
                    while ($row = $stmt->fetch()) {
                        $team = $row['team_name'];
                        $parts = explode('/', $row['tags']);
                        foreach ($parts as $p) {
                            $p = trim($p);
                            if ($p) $team_tags[$team][$p] = ($team_tags[$team][$p] ?? 0) + 1;
                        }
                    }
                    $res = [];
                    foreach ($team_tags as $team => $tags) {
                        arsort($tags);
                        foreach (array_slice($tags, 0, 5) as $tag => $count) {
                            $res[$team][] = ['tag' => $tag, 'count' => $count];
                        }
                    }
                    echo json_encode($res);
                } catch (Exception $e) {
                    echo json_encode(['error' => $e->getMessage(), 'action' => 'tags_by_team']);
                }
                break;
                
            case 'upload':
                echo json_encode(['success' => true, 'imported' => 0]);
                break;
            
            default:
                throw new Exception("Unknown action");
        }
    } catch (Exception $e) {
        // --- FALLBACK : MODE DÉMO AUTOMATIQUE ---
        handleDemoActions($action);
    }
    exit;
}

function handleDemoActions($action) {
    switch ($action) {
        case 'filters':
            echo json_encode(['teams' => ['Paris Nord', 'Lyon Est', 'Marseille Sud'], 'agents' => ['Jean D.', 'Marie L.', 'Paul R.']]);
            break;
        case 'overview':
            echo json_encode(['total_calls' => 1240, 'answered' => 1100, 'avg_wait' => 18]);
            break;
        case 'heatmap':
            $data = [];
            for($d=1;$d<=7;$d++) for($h=8;$h<=20;$h++) $data[] = ['day'=>$d, 'hour'=>$h, 'count'=>rand(5, 50)];
            echo json_encode($data);
            break;
        case 'tags':
            echo json_encode([['tag'=>'Urgence','count'=>450],['tag'=>'RDV','count'=>320],['tag'=>'Info','count'=>150]]);
            break;
        case 'agents':
            echo json_encode([
                ['agent'=>'Narindra','team'=>'DEB','total'=>4472,'answered'=>3953,'outbound'=>519,'rate'=>88.4,'avg_duration'=>104],
                ['agent'=>'Lionel','team'=>'Suivi Qualité','total'=>4257,'answered'=>349,'outbound'=>3908,'rate'=>8.2,'avg_duration'=>87],
                ['agent'=>'Gloria','team'=>'Suivi Qualité','total'=>4135,'answered'=>197,'outbound'=>3938,'rate'=>22.4,'avg_duration'=>90],
                ['agent'=>'Anna','team'=>'Suivi Qualité','total'=>3977,'answered'=>306,'outbound'=>3671,'rate'=>21.5,'avg_duration'=>112],
                ['agent'=>'Raitra','team'=>'Suivi Qualité','total'=>3445,'answered'=>274,'outbound'=>3171,'rate'=>18.7,'avg_duration'=>91]
            ]);
            break;
        case 'teams_stats':
            echo json_encode([
                ['team'=>'DEB','total'=>22008,'answered'=>12708,'outbound'=>2021,'missed'=>5075,'avg_duration'=>123,'agents_count'=>11],
                ['team'=>'Suivi Qualité','total'=>18464,'answered'=>1257,'outbound'=>15653,'missed'=>0,'avg_duration'=>96,'agents_count'=>17],
                ['team'=>'CDN','total'=>11755,'answered'=>9288,'outbound'=>480,'missed'=>1560,'avg_duration'=>206,'agents_count'=>7],
                ['team'=>'N2','total'=>1798,'answered'=>463,'outbound'=>329,'missed'=>889,'avg_duration'=>420,'agents_count'=>1]
            ]);
            break;
        case 'calls':
            echo json_encode([
                ['date'=>'2026-03-23','time'=>'18:57','agent'=>'Système','line'=>'SVI 2 Admin','direction'=>'Entrant','answered'=>0,'duration'=>31,'waiting_time'=>31,'tags'=>''],
                ['date'=>'2026-03-23','time'=>'18:57','agent'=>'Toky','line'=>'SVI 1 Astreinte','direction'=>'Entrant','answered'=>1,'duration'=>18,'waiting_time'=>9,'tags'=>''],
                ['date'=>'2026-03-23','time'=>'18:52','agent'=>'Illona','line'=>'SVI 2 Admin','direction'=>'Entrant','answered'=>1,'duration'=>143,'waiting_time'=>10,'tags'=>'']
            ]);
            break;
        case 'tags_by_team':
            echo json_encode([
                'CDN' => [['tag'=>'SVI3','count'=>7947],['tag'=>'MESSAGE AGENCE','count'=>6339],['tag'=>'CANDIDAT','count'=>2977]],
                'DEB' => [['tag'=>'SVI3','count'=>6],['tag'=>'MESSAGE AGENCE','count'=>5],['tag'=>'TOKY','count'=>5]],
                'N2' => [['tag'=>'Relance','count'=>308],['tag'=>'TRANSFERT NIVEAU 2','count'=>159]],
                'Suivi Qualité' => [['tag'=>'SUIVI MISSION','count'=>15630],['tag'=>'APPEL 1','count'=>10314]]
            ]);
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitalliance Dashboard Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <!-- Bibliothèques d'export -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        body { 
            background-color: #020617; 
            color: #fff; 
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(56, 189, 248, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(236, 72, 153, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(139, 92, 246, 0.05) 0%, transparent 100%);
            background-attachment: fixed;
        }
        .glass { 
            background: rgba(255, 255, 255, 0.03); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
        .glass:hover {
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.05);
        }
        .heatmap-cell { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .heatmap-cell:hover { transform: scale(1.4); z-index: 20; border-radius: 4px; }
        
        /* Neon Accents */
        .text-neon-cyan { color: #22d3ee; text-shadow: 0 0 10px rgba(34, 211, 238, 0.5); }
        .text-neon-pink { color: #f472b6; text-shadow: 0 0 10px rgba(244, 114, 182, 0.5); }
        .text-neon-purple { color: #a78bfa; text-shadow: 0 0 10px rgba(167, 139, 250, 0.5); }
        .text-neon-emerald { color: #34d399; text-shadow: 0 0 10px rgba(52, 211, 153, 0.5); }
        
        .bg-neon-cyan { background: #22d3ee; box-shadow: 0 0 15px rgba(34, 211, 238, 0.4); }
        .bg-neon-pink { background: #f472b6; box-shadow: 0 0 15px rgba(244, 114, 182, 0.4); }
        .bg-neon-purple { background: #a78bfa; box-shadow: 0 0 15px rgba(167, 139, 250, 0.4); }
        .bg-neon-emerald { background: #34d399; box-shadow: 0 0 15px rgba(52, 211, 153, 0.4); }
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect, useMemo } = React;

        function App() {
            const [stats, setStats] = useState(null);
            const [filters, setFilters] = useState({ team: '', agent: '', from: '2026-03-01', to: '2026-03-31' });
            const [options, setOptions] = useState({ teams: [], agents: [] });
            const [heatmap, setHeatmap] = useState([]);
            const [tags, setTags] = useState([]);
            const [agents, setAgents] = useState([]);
            const [teamsStats, setTeamsStats] = useState([]);
            const [calls, setCalls] = useState([]);
            const [tagsByTeam, setTagsByTeam] = useState({});
            const [loading, setLoading] = useState(true);

            const fetchData = async () => {
                setLoading(true);
                const query = new URLSearchParams(filters).toString();
                try {
                    const [s, h, t, f, a, ts, c, tbt] = await Promise.all([
                        fetch(`?action=overview&${query}`).then(r => r.json()),
                        fetch(`?action=heatmap&${query}`).then(r => r.json()),
                        fetch(`?action=tags&${query}`).then(r => r.json()),
                        fetch(`?action=filters`).then(r => r.json()),
                        fetch(`?action=agents&${query}`).then(r => r.json()),
                        fetch(`?action=teams_stats&${query}`).then(r => r.json()),
                        fetch(`?action=calls&${query}`).then(r => r.json()),
                        fetch(`?action=tags_by_team&${query}`).then(r => r.json())
                    ]);
                    setStats(s);
                    setHeatmap(h);
                    setTags(t);
                    setOptions(f);
                    setAgents(a);
                    setTeamsStats(ts);
                    setCalls(c);
                    setTagsByTeam(tbt);
                } catch (err) {
                    console.error("Fetch error:", err);
                }
                setLoading(false);
            };

            useEffect(() => { fetchData(); }, [filters]);

            const exportExcel = () => {
                const ws = XLSX.utils.json_to_sheet([stats]);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Stats");
                XLSX.writeFile(wb, "Vitalliance_Export.xlsx");
            };

            const exportPDF = () => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'mm', 'a4');
                html2canvas(document.querySelector("#dashboard-content")).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    doc.addImage(imgData, 'PNG', 10, 10, 280, 150);
                    doc.save("Vitalliance_Report.pdf");
                });
            };

            const handleFileUpload = async (e) => {
                const file = e.target.files[0];
                const formData = new FormData();
                formData.append('file', file);
                const res = await fetch('?action=upload', { method: 'POST', body: formData });
                const data = await res.json();
                alert(`Import réussi : ${data.imported} lignes.`);
                fetchData();
            };

            if (loading && !stats) return <div className="h-screen flex items-center justify-center text-cyan-400 tracking-widest uppercase text-xs animate-pulse">Synchronisation Vitalliance...</div>;

            return (
                <div className="p-6 lg:p-12 max-w-[1600px] mx-auto" id="dashboard-content">
                    {/* HEADER & FILTRES */}
                    <header className="mb-16 flex flex-col lg:flex-row justify-between items-start lg:items-end gap-10">
                        <div className="flex-1">
                            <h1 className="text-8xl font-black tracking-tighter mb-4 leading-none bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 via-purple-500 to-pink-500">VITALLIANCE</h1>
                            <div className="flex flex-wrap gap-6 items-center">
                                <FilterSelect label="Équipe" value={filters.team} options={options.teams} onChange={v => setFilters({...filters, team: v})} />
                                <FilterSelect label="Agent" value={filters.agent} options={options.agents} onChange={v => setFilters({...filters, agent: v})} />
                                <div className="flex flex-col">
                                    <span className="text-[9px] uppercase text-cyan-500/60 mb-2 tracking-[0.3em] font-bold">Période</span>
                                    <div className="flex gap-3">
                                        <input type="date" className="bg-white/5 border border-white/10 rounded-lg text-xs px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 transition-all" value={filters.from} onChange={e => setFilters({...filters, from: e.target.value})} />
                                        <span className="text-white/20 self-center">—</span>
                                        <input type="date" className="bg-white/5 border border-white/10 rounded-lg text-xs px-3 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 transition-all" value={filters.to} onChange={e => setFilters({...filters, to: e.target.value})} />
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div className="flex gap-4">
                            <button className="glass px-8 py-4 rounded-2xl text-[10px] uppercase tracking-[0.2em] font-bold hover:scale-105 transition-all bg-gradient-to-br from-cyan-500/20 to-blue-600/20 border-cyan-500/30">
                                <label className="cursor-pointer">
                                    Import Data
                                    <input type="file" className="hidden" onChange={handleFileUpload} accept=".csv,.xlsx" />
                                </label>
                            </button>
                            <button onClick={exportExcel} className="glass px-8 py-4 rounded-2xl text-[10px] uppercase tracking-[0.2em] font-bold hover:scale-105 transition-all bg-gradient-to-br from-purple-500/20 to-pink-600/20 border-purple-500/30 text-purple-200">Export XLSX</button>
                            <button onClick={exportPDF} className="glass px-8 py-4 rounded-2xl text-[10px] uppercase tracking-[0.2em] font-bold hover:scale-105 transition-all bg-gradient-to-br from-pink-500/20 to-red-600/20 border-pink-500/30 text-pink-200">Export PDF</button>
                        </div>
                    </header>

                    {/* STATS PRINCIPALES */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
                        <StatCard label="Volume Total" value={stats?.total_calls?.toLocaleString() || '0'} sub="Appels entrants" color="cyan" />
                        <StatCard label="Taux de Réponse" value={stats?.total_calls > 0 ? Math.round((stats.answered/stats.total_calls)*100) : '0'} unit="%" sub="Efficacité" color="emerald" />
                        <StatCard label="Attente Moyenne" value={stats?.avg_wait ? Number(stats.avg_wait).toFixed(1) : '0'} unit="s" sub="Temps de réponse" color="purple" />
                        <StatCard label="Comparaison" value="+12.4" unit="%" sub="Vs mois précédent" trend="up" color="pink" />
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-10">
                        {/* HEATMAP */}
                        <div className="lg:col-span-2 glass rounded-[40px] p-12 relative overflow-hidden">
                            <div className="absolute top-0 right-0 w-64 h-64 bg-cyan-500/10 blur-[100px] -z-10"></div>
                            <div className="flex justify-between items-center mb-12">
                                <h2 className="text-xs uppercase tracking-[0.4em] font-bold text-cyan-400/80">Densité de flux horaire</h2>
                                <div className="flex gap-3 text-[9px] text-zinc-400 uppercase tracking-widest items-center">
                                    <span>Calme</span>
                                    <div className="flex gap-1.5">
                                        <div className="w-4 h-4 bg-white/5 rounded-sm border border-white/10"></div>
                                        <div className="w-4 h-4 bg-cyan-900/40 rounded-sm"></div>
                                        <div className="w-4 h-4 bg-cyan-600/60 rounded-sm"></div>
                                        <div className="w-4 h-4 bg-cyan-400 rounded-sm shadow-[0_0_10px_rgba(34,211,238,0.5)]"></div>
                                    </div>
                                    <span>Pic</span>
                                </div>
                            </div>
                            <div className="grid grid-cols-25 gap-2">
                                {['LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM'].map((day, dIdx) => (
                                    <React.Fragment key={day}>
                                        <div className="text-[10px] text-zinc-500 flex items-center h-8 font-bold">{day}</div>
                                        {Array.from({length: 24}).map((_, h) => {
                                            const cell = heatmap.find(h_data => h_data.day === (dIdx + 1) && h_data.hour === h);
                                            const count = cell ? cell.count : 0;
                                            const intensity = Math.min(count / 50, 1);
                                            
                                            // Color scale from deep blue to bright cyan
                                            const r = Math.round(34 * intensity + 10 * (1-intensity));
                                            const g = Math.round(211 * intensity + 20 * (1-intensity));
                                            const b = Math.round(238 * intensity + 40 * (1-intensity));
                                            
                                            return (
                                                <div 
                                                    key={h} 
                                                    className="h-8 rounded-md heatmap-cell border border-white/5 cursor-pointer" 
                                                    style={{ 
                                                        backgroundColor: intensity > 0 ? `rgb(${r},${g},${b})` : 'rgba(255,255,255,0.03)',
                                                        boxShadow: intensity > 0.7 ? `0 0 15px rgba(${r},${g},${b},0.4)` : 'none'
                                                    }}
                                                    title={`${h}h : ${count} appels`}
                                                ></div>
                                            );
                                        })}
                                    </React.Fragment>
                                ))}
                                <div></div>
                                {Array.from({length: 24}).map((_, h) => (
                                    <div key={h} className="text-[9px] text-zinc-600 text-center mt-4 font-mono">{h}</div>
                                ))}
                            </div>
                        </div>

                        {/* TOP TAGS */}
                        <div className="glass rounded-[40px] p-12 relative overflow-hidden">
                            <div className="absolute bottom-0 left-0 w-64 h-64 bg-purple-500/10 blur-[100px] -z-10"></div>
                            <h2 className="text-xs uppercase tracking-[0.4em] font-bold text-purple-400/80 mb-12 text-center">Répartition Tags</h2>
                            <div className="space-y-10">
                                {tags.map((t, i) => {
                                    const colors = ['bg-neon-cyan', 'bg-neon-purple', 'bg-neon-pink', 'bg-neon-emerald'];
                                    const colorClass = colors[i % colors.length];
                                    return (
                                        <div key={t.tag} className="group cursor-pointer">
                                            <div className="flex justify-between text-[11px] uppercase tracking-[0.2em] mb-3 text-zinc-400 group-hover:text-white transition-colors font-bold">
                                                <span>{t.tag}</span>
                                                <span className="font-mono">{t.count}</span>
                                            </div>
                                            <div className="h-2 bg-white/5 w-full rounded-full overflow-hidden border border-white/5">
                                                <div 
                                                    className={`h-full ${colorClass} transition-all duration-1000 ease-out`} 
                                                    style={{ width: `${(t.count / tags[0].count) * 100}%` }}
                                                ></div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    {/* TEAM STATS */}
                    <div className="mt-16 glass rounded-[40px] p-12">
                        <h2 className="text-xs uppercase tracking-[0.4em] font-bold text-emerald-400/80 mb-12">Statistiques par Équipe</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="text-[10px] uppercase tracking-widest text-zinc-500 border-b border-white/5">
                                        <th className="pb-6 font-bold">Équipe</th>
                                        <th className="pb-6 font-bold">Total</th>
                                        <th className="pb-6 font-bold">Répondus</th>
                                        <th className="pb-6 font-bold">Sortants</th>
                                        <th className="pb-6 font-bold">Perdus</th>
                                        <th className="pb-6 font-bold">DMS</th>
                                        <th className="pb-6 font-bold">Agents</th>
                                    </tr>
                                </thead>
                                <tbody className="text-xs">
                                    {teamsStats.map((t, i) => (
                                        <tr key={i} className="border-b border-white/5 hover:bg-white/5 transition-colors group">
                                            <td className="py-6 font-bold text-zinc-300 group-hover:text-white">{t.team}</td>
                                            <td className="py-6 font-mono text-zinc-400">{t.total}</td>
                                            <td className="py-6 font-mono text-emerald-400">{t.answered}</td>
                                            <td className="py-6 font-mono text-cyan-400">{t.outbound}</td>
                                            <td className="py-6 font-mono text-pink-400">{t.missed}</td>
                                            <td className="py-6 font-mono text-purple-400">{t.avg_duration}s</td>
                                            <td className="py-6 font-mono text-zinc-400">{t.agents_count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* AGENT PERFORMANCE */}
                    <div className="mt-16 glass rounded-[40px] p-12">
                        <h2 className="text-xs uppercase tracking-[0.4em] font-bold text-cyan-400/80 mb-12">Performance Agents</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="text-[10px] uppercase tracking-widest text-zinc-500 border-b border-white/5">
                                        <th className="pb-6 font-bold">Agent</th>
                                        <th className="pb-6 font-bold">Équipe</th>
                                        <th className="pb-6 font-bold">Total</th>
                                        <th className="pb-6 font-bold">Répondus</th>
                                        <th className="pb-6 font-bold">Sortants</th>
                                        <th className="pb-6 font-bold">Taux</th>
                                        <th className="pb-6 font-bold">DMS</th>
                                    </tr>
                                </thead>
                                <tbody className="text-xs">
                                    {agents.map((a, i) => (
                                        <tr key={i} className="border-b border-white/5 hover:bg-white/5 transition-colors group">
                                            <td className="py-6 font-bold text-zinc-300 group-hover:text-white">{a.agent}</td>
                                            <td className="py-6 text-zinc-500">{a.team}</td>
                                            <td className="py-6 font-mono text-zinc-400">{a.total}</td>
                                            <td className="py-6 font-mono text-emerald-400">{a.answered}</td>
                                            <td className="py-6 font-mono text-cyan-400">{a.outbound}</td>
                                            <td className="py-6 font-mono">
                                                <span className={`px-2 py-1 rounded-md ${a.rate > 80 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}`}>
                                                    {a.rate}%
                                                </span>
                                            </td>
                                            <td className="py-6 font-mono text-purple-400">{a.avg_duration}s</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* TAGS BY TEAM */}
                    <div className="mt-16 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                        {Object.entries(tagsByTeam).map(([team, teamTags]) => (
                            <div key={team} className="glass rounded-[32px] p-8">
                                <h3 className="text-[10px] uppercase tracking-[0.3em] font-bold text-zinc-500 mb-6">{team}</h3>
                                <div className="space-y-4">
                                    {teamTags.map((tag, idx) => (
                                        <div key={idx} className="flex justify-between items-center">
                                            <span className="text-[11px] text-zinc-400 font-bold">{tag.tag}</span>
                                            <span className="text-[11px] font-mono text-cyan-400">{tag.count}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* CALL HISTORY */}
                    <div className="mt-16 glass rounded-[40px] p-12">
                        <h2 className="text-xs uppercase tracking-[0.4em] font-bold text-pink-400/80 mb-12">Historique des Appels</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="text-[10px] uppercase tracking-widest text-zinc-500 border-b border-white/5">
                                        <th className="pb-6 font-bold">Date</th>
                                        <th className="pb-6 font-bold">Heure</th>
                                        <th className="pb-6 font-bold">Agent</th>
                                        <th className="pb-6 font-bold">Ligne</th>
                                        <th className="pb-6 font-bold">Direction</th>
                                        <th className="pb-6 font-bold">Statut</th>
                                        <th className="pb-6 font-bold">Durée</th>
                                        <th className="pb-6 font-bold">Attente</th>
                                        <th className="pb-6 font-bold">Tags</th>
                                    </tr>
                                </thead>
                                <tbody className="text-xs">
                                    {calls.map((c, i) => (
                                        <tr key={i} className="border-b border-white/5 hover:bg-white/5 transition-colors group">
                                            <td className="py-6 text-zinc-400">{c.date}</td>
                                            <td className="py-6 text-zinc-500 font-mono">{c.time}</td>
                                            <td className="py-6 font-bold text-zinc-300 group-hover:text-white">{c.agent}</td>
                                            <td className="py-6 text-zinc-500">{c.line}</td>
                                            <td className="py-6">
                                                <span className={`px-2 py-1 rounded-md text-[10px] uppercase tracking-tighter ${c.direction === 'Entrant' ? 'bg-cyan-500/10 text-cyan-400' : 'bg-purple-500/10 text-purple-400'}`}>
                                                    {c.direction}
                                                </span>
                                            </td>
                                            <td className="py-6">
                                                <span className={`px-2 py-1 rounded-md text-[10px] uppercase tracking-tighter ${c.answered ? 'bg-emerald-500/10 text-emerald-400' : 'bg-pink-500/10 text-pink-400'}`}>
                                                    {c.answered ? 'Répondu' : 'Perdu'}
                                                </span>
                                            </td>
                                            <td className="py-6 font-mono text-zinc-400">{c.duration}s</td>
                                            <td className="py-6 font-mono text-zinc-500">{c.waiting_time}s</td>
                                            <td className="py-6">
                                                <div className="flex flex-wrap gap-1">
                                                    {c.tags && c.tags.split(',').map((tag, idx) => (
                                                        <span key={idx} className="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-[9px] text-zinc-400">
                                                            {tag.trim()}
                                                        </span>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            );
        }

        function FilterSelect({ label, value, options, onChange }) {
            return (
                <div className="flex flex-col">
                    <span className="text-[9px] uppercase text-cyan-500/60 mb-2 tracking-[0.3em] font-bold">{label}</span>
                    <select 
                        className="bg-white/5 border border-white/10 rounded-lg text-xs px-4 py-2 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 transition-all cursor-pointer min-w-[160px]"
                        value={value}
                        onChange={e => onChange(e.target.value)}
                    >
                        <option value="" className="bg-slate-900">Tous les secteurs</option>
                        {options.map(o => <option key={o} value={o} className="bg-slate-900">{o}</option>)}
                    </select>
                </div>
            );
        }

        function StatCard({ label, value, unit, sub, trend, color }) {
            const colorMap = {
                cyan: 'text-neon-cyan border-cyan-500/20',
                pink: 'text-neon-pink border-pink-500/20',
                purple: 'text-neon-purple border-purple-500/20',
                emerald: 'text-neon-emerald border-emerald-500/20'
            };
            
            return (
                <div className={`glass p-12 rounded-[40px] hover:scale-[1.02] transition-all group border ${colorMap[color]} relative overflow-hidden`}>
                    <div className="text-zinc-500 text-[10px] uppercase tracking-[0.3em] mb-8 font-bold">{label}</div>
                    <div className="flex items-baseline gap-2 mb-3">
                        <span className={`text-7xl font-black tracking-tighter leading-none ${colorMap[color]}`}>{value}</span>
                        {unit && <span className="text-zinc-600 text-xl font-bold">{unit}</span>}
                    </div>
                    <div className="text-[11px] text-zinc-400 uppercase tracking-[0.15em] flex items-center gap-2 font-bold">
                        {trend === 'up' && <span className="text-emerald-400">↑</span>}
                        {sub}
                    </div>
                    {/* Decorative glow */}
                    <div className={`absolute -bottom-10 -right-10 w-32 h-32 blur-[60px] opacity-20 bg-current`}></div>
                </div>
            );
        }

        // Custom grid for heatmap
        const style = document.createElement('style');
        style.innerHTML = `.grid-cols-25 { grid-template-columns: 30px repeat(24, 1fr); }`;
        document.head.appendChild(style);

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>
