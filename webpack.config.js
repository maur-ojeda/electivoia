const Encore = require("@symfony/webpack-encore");
const path = require("path");

Encore
    .setOutputPath("public/build/")
    .setPublicPath("/build")
    .addEntry("app", "./assets/app.js")
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .enableStimulusBridge("./assets/controllers.json")

    // limpieza automática de la carpeta build
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .copyFiles({
        // El origen DEBE ser la carpeta de imágenes que quieres copiar.
        // La ruta es relativa a la raíz de tu proyecto.
        from: "./assets/theme/images",

        // El destino DENTRO de public/build.
        // Esto mantendrá la estructura de carpetas: logo/logo.png
        to: "images/[path][name].[ext]",
    })

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

// Alias for symfony/stimulus-bundle PHP package JS loader
config.resolve = config.resolve || {};
config.resolve.alias = {
    ...(config.resolve.alias || {}),
    '@symfony/stimulus-bundle': path.resolve(
        __dirname,
        'vendor/symfony/stimulus-bundle/assets/dist/loader.js'
    ),
};

module.exports = config;
