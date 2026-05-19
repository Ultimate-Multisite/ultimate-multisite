const { cleanMinified } = require('./build-utils');
// Exclude `lib/` — vendored libraries (Vue 2.7.x, vuedraggable, v-money, etc.)
// have their `.min.js` artifacts produced by `scripts/build-vue.js` from
// node_modules. Re-deleting them here would force the uglify step to
// re-minify the dev source and silently strip Vue's production-mode flags
// (see issue #1221).
cleanMinified('assets/js', 'js', ['lib']);
