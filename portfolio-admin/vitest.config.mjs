import { defineConfig } from 'vitest/config'
import path from 'node:path'

/**
 * Vitest for the admin panel's pure-logic modules and the one component test.
 *
 * Two projects, because the environments differ:
 * - "logic" (node): the zod form schemas and the modules duplicated from
 *   portfolio-frontend. These read/import files across the repo root, which
 *   breaks under jsdom.
 * - "components" (jsdom): a shallow render test of the dashboard's system
 *   health card.
 *
 * React component coverage stays intentionally minimal: every admin form bug
 * in this project's history was either a validation rule that blocked a
 * legitimately-blank field or a layout/containing-block problem, and neither
 * is something a shallow component render would surface. The first is covered
 * here, the second by the Playwright suite in ../tests.
 */
export default defineConfig({
  resolve: {
    alias: { '@': path.resolve(import.meta.dirname) },
  },
  test: {
    projects: [
      {
        resolve: {
          alias: { '@': path.resolve(import.meta.dirname) },
        },
        test: {
          name: 'logic',
          environment: 'node',
          include: ['tests/unit/**/*.test.js'],
          exclude: ['node_modules/**'],
        },
      },
      {
        resolve: {
          alias: { '@': path.resolve(import.meta.dirname) },
        },
        test: {
          name: 'components',
          environment: 'jsdom',
          include: ['tests/unit/**/*.test.jsx'],
          exclude: ['node_modules/**'],
        },
      },
    ],
  },
})
