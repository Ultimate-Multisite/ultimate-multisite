const { copyFile } = require('./build-utils');

copyFile('node_modules/apexcharts/dist/apexcharts.js', 'assets/js/lib/apexcharts.js');
copyFile('node_modules/shepherd.js/dist/js/shepherd.mjs', 'assets/js/lib/shepherd.js');
copyFile('node_modules/shepherd.js/dist/css/shepherd.css', 'assets/css/lib/shepherd.css');
