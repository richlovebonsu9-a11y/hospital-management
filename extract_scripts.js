const fs = require('fs');
const content = fs.readFileSync('www/dashboard_admin.php', 'utf8');
const scripts = [...content.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/gi)];

scripts.forEach((match, index) => {
    fs.writeFileSync(`test_script_${index}.js`, match[1]);
    console.log(`Created test_script_${index}.js`);
});
