/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './*.php',
    './api/**/*.php',
    './app/**/*.php',
    './src/**/*.{js,ts,vue}',
  ],
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {},
  },
  plugins: [],
};
