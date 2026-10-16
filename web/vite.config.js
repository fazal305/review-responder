import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'
import { SITE_URL } from './src/lib/site.js'

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    // Social crawlers don't run JavaScript, so the absolute URLs in index.html are written at build time.
    { name: 'site-url', transformIndexHtml: (html) => html.replaceAll('%SITE_URL%', SITE_URL) },
  ],
  server: { port: 5472, strictPort: true },
})
