/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./assets/**/*.js",
        "./templates/**/*.html.twig",
        "./src/**/*.php",
    ],
    safelist: [
        'h-80',
        'text-violet-400',
        'w-6', 'h-6',
        'relative',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}
