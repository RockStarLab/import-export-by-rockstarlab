// Config
const mix = require("laravel-mix");
const projectPath = "./";
const resources = projectPath + "/src";

mix.options({
	processCssUrls: false,
	manifest: false
});

mix.setPublicPath(`${projectPath}/assets`);

mix.webpackConfig({
    externals: {
        "jquery": "jQuery"
    }
});

mix.sass(`${resources}/scss/app.scss`, `${projectPath}/assets/css`).sourceMaps(true, 'source-map');
mix.sass(`${resources}/scss/admin-wp7.scss`, `${projectPath}/assets/css`).sourceMaps(true, 'source-map');
mix.js(`${resources}/js/app.js`, `${projectPath}/assets/js`);
mix.js(`${resources}/js/media-library-export.js`, `${projectPath}/assets/js`);
mix.js(`${resources}/js/post-sync-standalone.js`, `${projectPath}/assets/js`);
mix.js(`${resources}/js/modules/gutenberg-sync.js`, `${projectPath}/assets/js`);
