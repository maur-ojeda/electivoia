const Encore = require("@symfony/webpack-encore");

Encore
    // carpeta donde se generan los assets compilados
    .setOutputPath("public/build/")
    .setPublicPath("/build")
    .addEntry("app", "./assets/app.js")
    .splitEntryChunks()
    .enableSingleRuntimeChunk()

    // limpieza automática de la carpeta build
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // Babel (por si quieres usar JS moderno)
    .configureBabel((babelConfig) => {
        // babelConfig.plugins.push('@babel/plugin-proposal-class-properties');
    })

    // PostCSS loader (usa postcss.config.cjs)
    .enablePostCssLoader();

// ⚠️ Prevenir bucle de watch
const config = Encore.getWebpackConfig();
config.watchOptions = {
    ignored: /public\/build/,
};

module.exports = config;
