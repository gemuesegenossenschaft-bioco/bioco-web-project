/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        'bioco': {
          'green': '#39A933',
          'orange': '#F29200',
          'beet': '#87213D',
          'dark-green': '#285A19',
          'bg': '#F6F9F5',
          'bg-alt': '#E2E8E0',
          'text': '#1F2A1B',
          'grey': '#A4B1A0',
        },
      },
      borderRadius: {
        'bioco-sm': '6px',
        'bioco-md': '12px',
        'bioco-lg': '18px',
      },
      fontFamily: {
        'heading': ['DM Sans', 'sans-serif'],
        'body': ['DM Sans', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
