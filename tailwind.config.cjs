module.exports = {
    content: [
        "./assets/**/*.{js,jsx,ts,tsx}",
        "./templates/**/*.html.twig",
        "./node_modules/flowbite/**/*.js",
    ],
    theme: {
        extend: {},
    },
    plugins: [require("flowbite/plugin")],
};
