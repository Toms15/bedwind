import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import globals from 'globals';

export default defineConfig([
  globalIgnores(['public/', 'vendor/', 'node_modules/']),

  js.configs.recommended,

  // Codice del browser (bundle Vite): frontend ed editor a blocchi.
  {
    files: ['resources/js/**/*.js'],
    languageOptions: {
      globals: {
        ...globals.browser,
        // API globale del block editor (wp-blocks, wp-dom-ready, ...).
        wp: 'readonly',
      },
    },
  },

  // Script Node: config degli strumenti e script npm.
  {
    files: ['*.config.js', 'resources/scripts/**/*.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
]);
