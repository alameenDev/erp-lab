const path = require('node:path');
const PORT = 17892;
const ORIGIN = `http://127.0.0.1:${PORT}`;
function paths(bundle, data) {
  return {bundle, data, backend: path.join(bundle, 'backend'), frontend: path.join(bundle, 'frontend'),
    php: path.join(bundle, 'php', 'php.exe'), cgi: path.join(bundle, 'php', 'php-cgi.exe'),
    maria: path.join(bundle, 'mariadb', 'bin'), storage: path.join(data, 'storage'),
    database: path.join(data, 'database'), cache: path.join(data, 'cache'), backups: path.join(data, 'backups')};
}
module.exports = {PORT, ORIGIN, paths};
