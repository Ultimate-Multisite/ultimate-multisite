const glob = require("glob");
const { execSync } = require("child_process");

const files = glob.sync("assets/css/**/*.css").filter(f => !f.endsWith(".min.css"));

console.log(`🔧 Starting minifying process for .css files`);

files.forEach((file) => {
  const outFile = file.replace(/\.css$/, ".min.css");
  console.log(`Minifying: ${file} → ${outFile}`);
  execSync(`cleancss -o "${outFile}" "${file}"`);
});

console.log(`✅ DONE creating minified .css files`);
