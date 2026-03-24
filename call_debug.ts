import https from 'https';

https.get('https://ais-dev-dj4jin4a36lxyydtjs2iyq-692170605448.europe-west2.run.app/index.php?action=debug_data', (res) => {
    let data = '';
    res.on('data', (chunk) => { data += chunk; });
    res.on('end', () => {
        console.log(data);
    });
}).on('error', (err) => {
    console.error('Error:', err.message);
});
