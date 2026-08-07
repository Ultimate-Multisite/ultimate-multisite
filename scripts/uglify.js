const glob = require("glob");
const { execSync } = require("child_process");

// Skip assets/js/lib/ — those files are vendored from node_modules via
// scripts/build-vue.js (Vue ecosystem) and scripts/copy-libs.js. Running
// uglify-js against them would re-minify the dev source of libraries like
// Vue 2.7.x and silently strip out production-only flags such as
// `process.env.NODE_ENV === 'production'`, leaving a minified development
// build that still emits the "Vue is running in development mode" warning.
// See issue #1221 for the regression history.
const files = glob
  .sync("assets/js/**/*.js")
  .filter((f) => !f.endsWith(".min.js"))
  .filter((f) => !f.startsWith("assets/js/lib/"));

console.log(`🔧 Starting minifying process for .js files`);

files.forEach((file) => {
  const outFile = file.replace(/\.js$/, ".min.js");
  console.log(`Uglifying: ${file} → ${outFile}`);
	execSync(`uglifyjs "${file}" -c -m -o "${outFile}"`);
});

console.log(`✅ DONE creating minified .js files`);
