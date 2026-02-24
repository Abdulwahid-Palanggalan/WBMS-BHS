<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    health: {
                        50: '#f0f9f9',
                        100: '#d9f2f2',
                        200: '#b8e5e5',
                        300: '#8cd2d2',
                        400: '#5ab6b6',
                        500: '#3f9a9a',
                        600: '#0D9488', // Primary Teal
                        700: '#2d6a6a',
                        800: '#285656',
                        900: '#254a4a',
                        950: '#112b2b',
                    },
                    medical: {
                        blue: '#0284C7',
                        red: '#BE123C',
                    }
                },
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'Noto Sans', 'sans-serif'],
                },
            }
        }
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-slate-50 text-slate-900;
        }
    }
    @layer components {
        .btn-health {
            @apply px-4 py-2 bg-health-600 text-white rounded-lg hover:bg-health-700 transition-colors duration-200 font-medium shadow-sm;
        }
        .card-health {
            @apply bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden;
        }
        .input-health {
            @apply w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-health-500 focus:border-health-500 outline-none transition-all;
        }
    }
</style>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
