import express from 'express';
import { createServer as createViteServer } from 'vite';
import mysql from 'mysql2/promise';
import path from 'path';
import { GoogleGenAI } from "@google/genai";
import dotenv from 'dotenv';

dotenv.config();

const app = express();
const PORT = 3000;

// Configuration BDD (à adapter avec vos secrets)
const dbConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'vita',
    password: process.env.DB_PASSWORD || 'V1tapasSDB26',
    database: process.env.DB_NAME || 'vita',
};

async function startServer() {
    let pool: mysql.Pool;
    try {
        pool = mysql.createPool(dbConfig);
    } catch (e) {
        console.error("Erreur connexion BDD:", e);
    }

    app.use(express.json());

    // Middleware pour vérifier la BDD ou utiliser des données de test
    const getStats = async (where: string, params: any[]) => {
        try {
            const [rows] = await pool.execute(`
                SELECT 
                    COUNT(*) as total_calls,
                    SUM(direction='inbound' AND answered=1) as answered,
                    SUM(direction='outbound' AND user IS NULL) as missed,
                    SUM(direction='inbound' AND user='[No associated user]') as abandoned,
                    AVG(CASE WHEN answered=1 THEN duration_total END) as avg_duration,
                    AVG(CASE WHEN answered=1 THEN waiting_time END) as avg_wait
                FROM v_stats_all
                ${where}
            `, params);
            return (rows as any)[0];
        } catch (e) {
            console.warn("BDD non configurée, passage en mode DEMO");
            return {
                total_calls: 1250,
                answered: 840,
                missed: 120,
                abandoned: 290,
                avg_duration: 252,
                avg_wait: 24
            };
        }
    };

    // --- MOCK PHP API FOR PREVIEW ---
    app.all('/api/index.php', async (req, res) => {
        const action = req.query.action || req.body.action;
        const { team, agent, from, to } = req.query;
        
        let where = "WHERE 1=1";
        let where_data = "WHERE 1=1";
        const params: any[] = [];
        const params_data: any[] = [];

        if (from) { 
            where += " AND date >= ?"; params.push(from); 
            where_data += " AND date_only >= ?"; params_data.push(from);
        }
        if (to) { 
            where += " AND date <= ?"; params.push(to); 
            where_data += " AND date_only <= ?"; params_data.push(to);
        }
        if (team) { 
            where += " AND team_name = ?"; params.push(team); 
            where_data += " AND team_name = ?"; params_data.push(team);
        }
        if (agent) { 
            where += " AND user = ?"; params.push(agent); 
            where_data += " AND user = ?"; params_data.push(agent);
        }

        try {
            if (action === 'debug_data') {
                const [rows] = await pool.execute("DESC data");
                return res.json(rows);
            }

            if (action === 'debug') {
                const debugInfo: any = {};
                try {
                    const [columns] = await pool.execute("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME LIKE '%tag%' AND TABLE_SCHEMA = DATABASE()");
                    debugInfo.columns = columns;
                } catch (e: any) { debugInfo.columns_error = e.message; }

                try {
                    const [tables] = await pool.execute("SHOW TABLES");
                    debugInfo.tables = tables;
                } catch (e: any) { debugInfo.tables_error = e.message; }

                try {
                    const [dataSample] = await pool.execute("SELECT * FROM data LIMIT 1");
                    debugInfo.dataSample = dataSample;
                } catch (e: any) { debugInfo.dataSample_error = e.message; }

                return res.json(debugInfo);
            }

            if (action === 'overview') {
                const stats = await getStats(where, params);
                return res.json(stats);
            }
            
            if (action === 'tags') {
                const [rows] = await pool.execute(`SELECT tags FROM data ${where_data} AND tags IS NOT NULL AND tags != ''`, params_data);
                const allTags: Record<string, number> = {};
                (rows as any[]).forEach(row => {
                    if (row.tags) {
                        const parts = row.tags.split('/');
                        parts.forEach((p: string) => {
                            const trimmed = p.trim();
                            if (trimmed) allTags[trimmed] = (allTags[trimmed] || 0) + 1;
                        });
                    }
                });
                const sorted = Object.entries(allTags)
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 10)
                    .map(([tag, count]) => ({ tag, count }));
                return res.json(sorted);
            }

            if (action === 'agents') {
                const [rows] = await pool.execute(`SELECT user as agent, team_name as team, COUNT(*) as total, SUM(answered=1) as answered, SUM(direction='outbound') as outbound, ROUND(SUM(answered=1)*100/COUNT(*), 1) as rate, AVG(duration_total) as avg_duration FROM v_stats_all ${where} GROUP BY user, team_name ORDER BY total DESC`, params);
                return res.json(rows);
            }

            if (action === 'teams_stats') {
                const [rows] = await pool.execute(`SELECT team_name as team, COUNT(*) as total, SUM(answered=1) as answered, SUM(direction='outbound') as outbound, SUM(answered=0 AND direction='inbound') as missed, AVG(duration_total) as avg_duration, COUNT(DISTINCT user) as agents_count FROM v_stats_all ${where} GROUP BY team_name`, params);
                return res.json(rows);
            }

            if (action === 'calls') {
                const [rows] = await pool.execute(`SELECT date_only as date, HOUR(datetime_tz_offset_incl) as time, user as agent, line as line, direction, answered, duration_total_sec as duration, waiting_time_sec as waiting_time, tags FROM data ${where_data} ORDER BY date_only DESC, datetime_tz_offset_incl DESC LIMIT 100`, params_data);
                return res.json(rows);
            }

            if (action === 'tags_by_team') {
                const [rows] = await pool.execute(`SELECT team_name, tags FROM data ${where_data} AND tags IS NOT NULL AND tags != ''`, params_data);
                const teamTags: Record<string, Record<string, number>> = {};
                (rows as any[]).forEach(row => {
                    const team = row.team_name;
                    if (!teamTags[team]) teamTags[team] = {};
                    if (row.tags) {
                        const parts = row.tags.split('/');
                        parts.forEach((p: string) => {
                            const trimmed = p.trim();
                            if (trimmed) teamTags[team][trimmed] = (teamTags[team][trimmed] || 0) + 1;
                        });
                    }
                });
                const result: any = {};
                for (const [team, tags] of Object.entries(teamTags)) {
                    result[team] = Object.entries(tags)
                        .sort((a, b) => b[1] - a[1])
                        .slice(0, 5)
                        .map(([tag, count]) => ({ tag, count }));
                }
                return res.json(result);
            }

            if (action === 'filters') {
                const [teams] = await pool.execute("SELECT DISTINCT team_name FROM v_stats_all WHERE team_name IS NOT NULL ORDER BY team_name");
                const [agents] = await pool.execute("SELECT DISTINCT user FROM v_stats_all WHERE user IS NOT NULL ORDER BY user");
                return res.json({ 
                    teams: (teams as any[]).map(t => t.team_name), 
                    agents: (agents as any[]).map(a => a.user) 
                });
            }
            
            if (action === 'ai_analyze') {
                return res.json({ analysis: "Analyse IA disponible uniquement avec une clé API configurée." });
            }

            res.json({ error: "Action non reconnue : " + action });
        } catch (e) {
            console.error("Erreur API Simulator:", e);
            res.status(500).json({ error: "Erreur serveur BDD" });
        }
    });

    // --- API ROUTES (Node.js native) ---
    app.get('/api/stats/overview', async (req, res) => {
        const { date_from, date_to, team_name } = req.query;
        let where = "WHERE 1=1";
        const params: any[] = [];

        if (date_from) { where += " AND date >= ?"; params.push(date_from); }
        if (date_to) { where += " AND date <= ?"; params.push(date_to); }
        if (team_name) { where += " AND team_name = ?"; params.push(team_name); }

        const stats = await getStats(where, params);
        res.json(stats);
    });

    app.post('/api/ai/analyze', async (req, res) => {
        const { stats } = req.body;
        
        if (!process.env.GEMINI_API_KEY) {
            return res.json({ analysis: "Mode démo : L'IA nécessite une clé API Gemini pour fonctionner." });
        }

        const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });
        // ... reste du code
    });

    // 3. Liste des agents
    app.get('/api/agents', async (req, res) => {
        const [rows] = await pool.execute("SELECT DISTINCT user FROM v_stats_all WHERE user IS NOT NULL ORDER BY user");
        res.json(rows);
    });

    // Vite middleware pour le frontend
    if (process.env.NODE_ENV !== "production") {
        const vite = await createViteServer({
            server: { middlewareMode: true },
            appType: "spa",
        });
        app.use(vite.middlewares);
    } else {
        const distPath = path.join(process.cwd(), 'dist');
        app.use(express.static(distPath));
        app.get('*', (req, res) => res.sendFile(path.join(distPath, 'index.html')));
    }

    app.listen(PORT, "0.0.0.0", () => {
        console.log(`🚀 Serveur optimisé sur http://localhost:${PORT}`);
    });
}

startServer();
