import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { vue: 'vue/dist/vue.esm-bundler.js' }
  },
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: { attendance: 'src/attendance/main.js', faceAttendance: 'src/attendance/face.js', faceEnrollment: 'src/attendance/enrollment.js', login: 'src/auth/login.js', shared: 'src/shared/main.js', issueStatus: 'src/admin/issue-status.js', tailwind: 'src/tailwind.css' },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => assetInfo.name === 'tailwind.css'
          ? '[name][extname]'
          : 'assets/[name]-[hash][extname]'
      }
    }
  },
  server: { host: 'localhost', port: 5173 }
});
