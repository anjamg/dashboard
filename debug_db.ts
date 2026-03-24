import mysql from 'mysql2/promise';

async function debug() {
    const pool = mysql.createPool({
        host: process.env.DB_HOST || 'localhost',
        user: process.env.DB_USER || 'vita',
        password: process.env.DB_PASSWORD || 'V1tapasSDB26',
        database: process.env.DB_NAME || 'vita',
        waitForConnections: true,
        connectionLimit: 10,
        queueLimit: 0
    });

    try {
        const [columns] = await pool.execute("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME LIKE '%tag%' AND TABLE_SCHEMA = DATABASE()");
        console.log("FOUND TAG COLUMNS:");
        console.table(columns);

        const [tables] = await pool.execute("SHOW TABLES");
        console.log("\nALL TABLES:");
        console.table(tables);

        const [dataSample] = await pool.execute("SELECT * FROM data LIMIT 1");
        console.log("\nDATA TABLE SAMPLE:");
        console.table(dataSample);

    } catch (err) {
        console.error("ERROR:", err);
    } finally {
        await pool.end();
    }
}

debug();
