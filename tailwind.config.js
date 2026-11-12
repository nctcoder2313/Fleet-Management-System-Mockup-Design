/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./**/*.php",
    "./**/*.html",
    "./**/*.js"
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
      },
      colors: {
        fleet: {
          50: '#EFF6FF',       // Sky Ice (Subtle Tint / Surface)
          100: '#DBEAFE',
          200: '#BFDBFE',      // Soft Sky (Border / Focus)
          500: '#3B82F6',
          600: '#2563EB',      // Royal Blue (Primary Action)
          700: '#1D4ED8',      // Deep Blue (Action Hover)
          800: '#1E40AF',
          900: '#1E3A8A',      // Fleet Navy (Primary Base / Sidebar)
          navy: '#1E3A8A',
          primary: '#2563EB',
          hover: '#1D4ED8',
          subtle: '#EFF6FF',
          border: '#BFDBFE',
        },
        app: {
          bg: '#F8FAFC',       // Slate 50 - Main App Background
          surface: '#FFFFFF',  // Pure White - Card & Surface Background
          text: '#0F172A',     // Slate 900 - Primary Text
          muted: '#64748B',    // Slate 500 - Secondary / Muted Text
          border: '#E2E8F0',   // Slate 200 - Borders & Dividers
        }
      },
      fontSize: {
        'badge': ['0.75rem', { lineHeight: '0.875rem', fontWeight: '500' }],      // 12px / 14px (Status Badges / Tags)
        'table-meta': ['0.75rem', { lineHeight: '1rem', fontWeight: '600' }],     // 12px / 16px (Table Headers / Meta)
        'body': ['0.875rem', { lineHeight: '1.25rem', fontWeight: '400' }],       // 14px / 20px (Body / Inputs)
        'subheader': ['1rem', { lineHeight: '1.375rem', fontWeight: '500' }],     // 16px / 22px (Subheader H3)
        'section': ['1.125rem', { lineHeight: '1.5rem', fontWeight: '600' }],     // 18px / 24px (Section Header H2)
        'page-title': ['1.5rem', { lineHeight: '2rem', fontWeight: '600' }],      // 24px / 32px (Page Title H1)
      }
    }
  },
  plugins: []
};
