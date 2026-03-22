const fs = require('fs');
const path = require('path');

const dir = 'c:/Users/win/Desktop/hospital-management/www';
const files = fs.readdirSync(dir).filter(f => f.startsWith('dashboard_') && f.endsWith('.php'));

const js = `
        async function silentRefresh() {
            try {
                const activeSection = document.querySelector('.dashboard-section:not(.d-none)');
                if (activeSection) {
                    const html = await fetch(location.href).then(r => r.text());
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newSection = doc.getElementById(activeSection.id);
                    if (newSection) activeSection.innerHTML = newSection.innerHTML;
                } else {
                    location.reload();
                }
            } catch (e) { location.reload(); }
        }`;

for (const file of files) {
    const fullPath = path.join(dir, file);
    let content = fs.readFileSync(fullPath, 'utf8');
    
    // Replace reloads
    content = content.replace(/window\.location\.reload\(\);/g, 'silentRefresh();');
    content = content.replace(/location\.reload\(\);/g, 'silentRefresh();');
    
    // Inject function if not present
    if (!content.includes('function silentRefresh')) {
        content = content.replace('</script>', js + '\n    </script>');
    }
    
    fs.writeFileSync(fullPath, content);
    console.log('Updated ' + file);
}
