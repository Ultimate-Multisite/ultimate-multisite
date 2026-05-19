/**
 * Build script: copy Vue.js and Vue ecosystem libraries from node_modules
 * into assets/js/lib/, producing reproducible production builds.
 *
 * This replaces the legacy committed `assets/js/lib/vue*.js` files that were
 * historically clobbered by `scripts/uglify.js` (which minified the dev source
 * and produced a minified DEV build, leaving production sites with Vue's
 * "running in development mode" warning and full devtools enabled).
 *
 * Output:
 *   assets/js/lib/vue.js          – Vue 2.7.x dev build + wu_vue wrapper
 *   assets/js/lib/vue.min.js      – Vue 2.7.x production build + wu_vue wrapper
 *   assets/js/lib/v-money.{js,min.js}
 *   assets/js/lib/vue-the-mask.{js,min.js}
 *   assets/js/lib/vue-draggable.{js,min.js}
 *   assets/js/lib/vue-apexcharts.{js,min.js}
 *
 * @package WP_Ultimo
 * @since 2.12.0
 */
const fs = require('fs');
const path = require('path');
const UglifyJS = require('uglify-js');

const LIB_DIR = path.resolve(__dirname, '..', 'assets', 'js', 'lib');
const NODE_MODULES = path.resolve(__dirname, '..', 'node_modules');

/**
 * Wu_vue wrapper appended to Vue dev build.
 *
 * Exposes Vue and defineComponent on `window.wu_vue` so internal modules can
 * destructure them without depending on the bundler. The wrapper reads from
 * `window.Vue` (set by Vue's UMD bundle) rather than from a closure variable,
 * so it works appended to either the unminified or minified Vue UMD bundle.
 */
// Uses 2-space indentation to match the upstream Vue dev bundle's whitespace
// style; this keeps `assets/js/lib/vue.js` free of mixed-tab/space lint noise.
const WU_VUE_WRAPPER_DEV = `

/* Ultimate Multisite wu_vue wrapper */
if (typeof window !== 'undefined' && window.Vue) {
  window.wu_vue = {
    Vue: window.Vue,
    defineComponent: window.Vue.defineComponent
  };
}
`;

/** Minified equivalent of the wrapper above. */
const WU_VUE_WRAPPER_MIN = `;window.Vue&&(window.wu_vue={Vue:window.Vue,defineComponent:window.Vue.defineComponent});`;

/**
 * Copy a source file to a destination, optionally appending an extra string.
 *
 * @param {string} src       Absolute source path.
 * @param {string} dest      Absolute destination path.
 * @param {string} [append]  Optional suffix appended to the copied content.
 */
function copyWithSuffix(src, dest, append) {
	if (!fs.existsSync(src)) {
		throw new Error(`Source file not found: ${src}`);
	}
	fs.mkdirSync(path.dirname(dest), { recursive: true });
	const content = fs.readFileSync(src, 'utf8');
	const suffix = append || '';
	fs.writeFileSync(dest, content + suffix);
	const bytes = Buffer.byteLength(content + suffix);
	console.log(`  → ${path.relative(process.cwd(), dest)} (${bytes} bytes)`);
}

/**
 * Minify a source file with uglify-js, writing to dest.
 *
 * @param {string} src  Absolute source path.
 * @param {string} dest Absolute destination path.
 */
function minifyTo(src, dest) {
	if (!fs.existsSync(src)) {
		throw new Error(`Source file not found: ${src}`);
	}
	const code = fs.readFileSync(src, 'utf8');
	const result = UglifyJS.minify(code, {
		compress: true,
		mangle: true,
	});
	if (result.error) {
		throw result.error;
	}
	fs.mkdirSync(path.dirname(dest), { recursive: true });
	fs.writeFileSync(dest, result.code);
	const bytes = Buffer.byteLength(result.code);
	console.log(`  → ${path.relative(process.cwd(), dest)} (${bytes} bytes, minified)`);
}

/**
 * Read package version from node_modules.
 *
 * @param {string} pkg npm package name.
 * @returns {string} Version string.
 */
function pkgVersion(pkg) {
	const pkgJsonPath = path.join(NODE_MODULES, pkg, 'package.json');
	if (!fs.existsSync(pkgJsonPath)) {
		throw new Error(`Package not installed: ${pkg} (looked at ${pkgJsonPath})`);
	}
	const meta = JSON.parse(fs.readFileSync(pkgJsonPath, 'utf8'));
	return meta.version;
}

console.log('Building Vue ecosystem lib files from node_modules…');

// Vue itself: copy dev + prod builds and append wu_vue wrapper to each.
const vueVersion = pkgVersion('vue');
console.log(`vue@${vueVersion}`);
copyWithSuffix(
	path.join(NODE_MODULES, 'vue', 'dist', 'vue.js'),
	path.join(LIB_DIR, 'vue.js'),
	WU_VUE_WRAPPER_DEV
);
copyWithSuffix(
	path.join(NODE_MODULES, 'vue', 'dist', 'vue.min.js'),
	path.join(LIB_DIR, 'vue.min.js'),
	WU_VUE_WRAPPER_MIN
);

// vue-the-mask: npm package only ships dist/vue-the-mask.js; minify locally.
const vueTheMaskVersion = pkgVersion('vue-the-mask');
console.log(`vue-the-mask@${vueTheMaskVersion}`);
const vueTheMaskSrc = path.join(NODE_MODULES, 'vue-the-mask', 'dist', 'vue-the-mask.js');
copyWithSuffix(vueTheMaskSrc, path.join(LIB_DIR, 'vue-the-mask.js'));
minifyTo(vueTheMaskSrc, path.join(LIB_DIR, 'vue-the-mask.min.js'));

// v-money: npm package only ships dist/v-money.js; minify locally.
const vMoneyVersion = pkgVersion('v-money');
console.log(`v-money@${vMoneyVersion}`);
const vMoneySrc = path.join(NODE_MODULES, 'v-money', 'dist', 'v-money.js');
copyWithSuffix(vMoneySrc, path.join(LIB_DIR, 'v-money.js'));
minifyTo(vMoneySrc, path.join(LIB_DIR, 'v-money.min.js'));

// vuedraggable: ships both UMD dev and UMD min in dist/. Rename to vue-draggable
// to preserve the existing asset filenames consumed by inc/class-scripts.php.
const vueDraggableVersion = pkgVersion('vuedraggable');
console.log(`vuedraggable@${vueDraggableVersion}`);
copyWithSuffix(
	path.join(NODE_MODULES, 'vuedraggable', 'dist', 'vuedraggable.umd.js'),
	path.join(LIB_DIR, 'vue-draggable.js')
);
copyWithSuffix(
	path.join(NODE_MODULES, 'vuedraggable', 'dist', 'vuedraggable.umd.min.js'),
	path.join(LIB_DIR, 'vue-draggable.min.js')
);

// vue-apexcharts: npm package only ships dist/vue-apexcharts.js; minify locally.
const vueApexVersion = pkgVersion('vue-apexcharts');
console.log(`vue-apexcharts@${vueApexVersion}`);
const vueApexSrc = path.join(NODE_MODULES, 'vue-apexcharts', 'dist', 'vue-apexcharts.js');
copyWithSuffix(vueApexSrc, path.join(LIB_DIR, 'vue-apexcharts.js'));
minifyTo(vueApexSrc, path.join(LIB_DIR, 'vue-apexcharts.min.js'));

console.log('Vue ecosystem lib build complete.');
