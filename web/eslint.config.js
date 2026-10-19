import { defineConfig } from 'eslint/config';
import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

export default defineConfig(
    {
        ignores: ['bootstrap/ssr/**', 'node_modules/**', 'public/**', 'storage/**', 'vendor/**'],
    },
    tseslint.configs.recommended,
    pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
            },
        },
    },
    {
        // Inertia pages are named after the screen they render (Welcome, Schedule), not as reusable components.
        files: ['resources/js/pages/**/*.vue'],
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
    {
        rules: {
            // Matches the 4-space indent from .editorconfig.
            'vue/html-indent': ['error', 4],
        },
    },
);
