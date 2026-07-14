import https from 'https';

https.get('https://ch.alamin.se/accounting?tab=review', (res) => {
  let data = '';
  res.on('data', d => data += d);
  res.on('end', () => {
    console.log('HTML loaded, length:', data.length);
    const scriptMatches = data.match(/<script src="(\/_next\/static\/chunks\/app\/accounting\/[^>]+\.js)"/g);
    if (scriptMatches) {
        scriptMatches.forEach(m => {
            const url = 'https://ch.alamin.se' + m.match(/src="([^"]+)"/)[1];
            console.log('Fetching', url);
            https.get(url, (res2) => {
                let js = '';
                res2.on('data', d => js += d);
                res2.on('end', () => {
                    if (js.includes('item.category_name')) {
                        console.log('FOUND item.category_name IN CHUNK!', url);
                    } else if (js.includes('item.type==="expense"?"\\u0645\\u0635\\u0631\\u0648\\u0641"')) {
                        console.log('FOUND OLD HARDCODED STRING in chunk', url);
                    } else {
                        console.log('Done scanning', url, 'found none');
                    }
                });
            });
        });
    }
  });
});
