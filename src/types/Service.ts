/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export interface Service {
	id: string
	label: string
	connected: number
	auth: 'BA' | 'OA' | 'JB'
	bauth_id?: string
	bauth_secret?: string
	oauth_id?: string
	oauth_access_token?: string
	location_host?: string
	location_protocol?: string
	location_security?: boolean
	location_port?: string
	location_path?: string
	harmonization_start?: number
	harmonization_end?: number
}
