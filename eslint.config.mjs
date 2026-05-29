/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		name: 'integration-davc/ignored-files',
		ignores: ['package.json'],
	},
	{
		files: ['**/*.js', '**/*.vue', '**/*.ts'],
		rules: {
			// Relax some rules for now. Can be improved later on (baseline).
			'no-console': 'warn',
			'@typescript-eslint/no-unused-vars': 'off',
			'vue/multi-word-component-names': 'off',
			'jsdoc/require-jsdoc': 'off',
			'jsdoc/require-param': 'off',
			// Forbid empty JSDocs
			'jsdoc/no-blank-blocks': ['error', { enableFixer: true }],
		},
	},
]
