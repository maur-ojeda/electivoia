module.exports = {
    content: [
        "./assets/**/*.{js,jsx,ts,tsx}",
        "./templates/**/*.html.twig",
        "./node_modules/flowbite/**/*.js",
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#1a56db',
                    700: '#1e40af',
                    800: '#1e3a8a',
                    900: '#1e3a8a',
                    950: '#172554',
                },
            },
        },
    },
    plugins: [require("flowbite/plugin")],
};
