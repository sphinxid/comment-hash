// worker.js
async function sha256(message) {
    // encode as UTF-8
    const msgBuffer = new TextEncoder().encode(message);

    // hash the message
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);

    // convert ArrayBuffer to Array
    const hashArray = Array.from(new Uint8Array(hashBuffer));

    // convert bytes to hex string
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex;
}

async function findNonce(challenge, uniqueStr, timestamp, difficulty, nonceRange) {
    const target = '0'.repeat(difficulty);
    let nonce = Math.floor(Math.random() * nonceRange);
    let iterations = 0;

    while (true) {
        const data = `${challenge}${uniqueStr}${timestamp}${nonce}`;
        const hash = await sha256(data);

        if (hash.startsWith(target)) {
            // Log each component separately for better visibility
            console.log('=== Found Valid Hash ===');
            console.log('Nonce:', nonce);
            console.log('Hash:', hash);
            console.log('Challenge:', challenge);
            console.log('UniqueStr:', uniqueStr);
            console.log('Timestamp:', timestamp);
            console.log('Input Data:', data);
            console.log('Iterations needed:', iterations);
            console.log('=====================');
            
            return nonce;
        }

        nonce = (nonce + 1) % nonceRange;
        iterations++;

        // Every 1000 iterations, report progress to main thread
        if (iterations % 1000 === 0) {
            self.postMessage({
                type: 'progress', 
                nonce,
                iterations,
                lastHash: hash // Also send the last attempted hash for debugging
            });
        }
    }
}

self.addEventListener('message', async function(e) {
    const { challenge, uniqueStr, timestamp, difficulty, nonceRange } = e.data;
    
    console.log('Starting proof-of-work calculation with:');
    console.log('Difficulty:', difficulty, '(requiring', difficulty, 'leading zeros)');
    console.log('Nonce Range:', nonceRange);
    console.log('Timestamp:', timestamp);
    
    try {
        const nonce = await findNonce(challenge, uniqueStr, timestamp, difficulty, nonceRange);
        self.postMessage({
            type: 'success',
            nonce: nonce
        });
    } catch (error) {
        console.error('Worker error:', error);
        self.postMessage({
            type: 'error',
            error: error.message
        });
    }
});
