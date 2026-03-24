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
    // Cette route intercepte les appels vers /api/index.php pour que la prévisualisation fonctionne
    app.all('/api/index.php', async (req, res) => {
        const action = req.query.action || req.body.action;
        
        if (action === 'overview') {
            const stats = await getStats("WHERE 1=1", []);
            return res.json(stats);
        }
        
        if (action === 'ai_analyze') {
            return res.json({ analysis: "Analyse simulée (le serveur de test ne supporte pas le PHP en natif)." });
        }

        res.json({ error: "Action non reconnue dans le simulateur PHP" });
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
