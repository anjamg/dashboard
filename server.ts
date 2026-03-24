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
        const params: any[] = [];
        if (from) { where += " AND date >= ?"; params.push(from); }
        if (to) { where += " AND date <= ?"; params.push(to); }
        if (team) { where += " AND team_name = ?"; params.push(team); }
        if (agent) { where += " AND user = ?"; params.push(agent); }

        try {
            if (action === 'overview') {
                const stats = await getStats(where, params);
                return res.json(stats);
            }
            
            if (action === 'tags') {
                const [rows] = await pool.execute(`SELECT tags as tag, COUNT(*) as count FROM v_stats_all ${where} AND tags IS NOT NULL AND tags != '' GROUP BY tags ORDER BY count DESC LIMIT 10`, params);
                return res.json(rows);
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
                const [rows] = await pool.execute(`SELECT date, time, user as agent, line_name as line, direction, answered, duration_total as duration, waiting_time, tags FROM v_stats_all ${where} ORDER BY date DESC, time DESC LIMIT 100`, params);
                return res.json(rows);
            }

            if (action === 'tags_by_team') {
                const [rows] = await pool.execute(`SELECT team_name, tags as tag, COUNT(*) as count FROM v_stats_all ${where} AND tags IS NOT NULL AND tags != '' GROUP BY team_name, tags ORDER BY team_name, count DESC`, params);
                const result: any = {};
                (rows as any[]).forEach(row => {
                    if (!result[row.team_name]) result[row.team_name] = [];
                    if (result[row.team_name].length < 5) result[row.team_name].push({ tag: row.tag, count: row.count });
                });
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
