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
mix.js(`${resources}/js/app.js`, `${projectPath}/assets/js`);

