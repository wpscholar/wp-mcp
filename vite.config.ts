import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
      '@/components': resolve(__dirname, './components'),
    },
  },
  build: {
    // Output to dist directory for WordPress asset loading
    outDir: 'dist',
    
    // Generate manifest for WordPress asset handling
    manifest: true,
    
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/main.tsx'),
      },
      output: {
        // WordPress-friendly asset naming
        entryFileNames: '[name].[hash].js',
        chunkFileNames: '[name].[hash].js',
        assetFileNames: '[name].[hash].[ext]',
      },
    },
    
    // Optimize for WordPress environment
    target: 'es2020',
    minify: 'esbuild',
    
    // Source maps for debugging
    sourcemap: true,
  },
  
  // Development server configuration
  server: {
    port: 3000,
    host: true,
    cors: true,
    // Proxy WordPress requests if needed
    proxy: {
      '/wp-json': {
        target: 'http://localhost',
        changeOrigin: true,
      }
    }
  },
  
  // Define global constants for WordPress integration
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development'),
  },
  
  // CSS configuration
  css: {
    postcss: './postcss.config.js',
  },
})