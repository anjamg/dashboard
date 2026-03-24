import https from 'https';
import fs from 'fs';

function get(url: string) {
    https.get(url, (res) => {
        if (res.statusCode === 301 || res.statusCode === 302) {
            console.log('Redirecting to:', res.headers.location);
            get(res.headers.location!);
            return;
        }
        let data = '';
        res.on('data', (chunk) => { data += chunk; });
        res.on('end', () => {
            console.log(data);
        });
    }).on('error', (err) => {
        console.error('Error:', err.message);
    });
}

get('https://ais-dev-dj4jin4a36lxyydtjs2iyq-692170605448.europe-west2.run.app/index.php?action=debug_data');
