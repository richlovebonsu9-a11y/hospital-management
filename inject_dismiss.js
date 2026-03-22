const fs = require('fs');
const path = require('path');
const dir = path.join(__dirname, 'www');

fs.readdirSync(dir).forEach(file => {
    if (file.endsWith('.php')) {
        const filePath = path.join(dir, file);
        let content = fs.readFileSync(filePath, 'utf8');
        if (!content.includes('auto_dismiss.js')) {
            content = content.replace('</body>', '    <script src="/assets/js/auto_dismiss.js"></script>\n</body>');
            fs.writeFileSync(filePath, content);
            console.log('Injected into ' + file);
        }
    }
});
