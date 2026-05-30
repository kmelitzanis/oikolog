/** @type {import('tailwindcss').Config} */
export default {
    // Enable class-based dark mode so the `.dark` class controls dark styles
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './resources/css/**/*.css',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['var(--font-sans)'],
            },
        },
    },
    plugins: [],
}
