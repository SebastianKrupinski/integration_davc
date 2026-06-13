<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Service } from '../types/Service.ts'

import { translate as t } from '@nextcloud/l10n'
import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import CheckIcon from 'vue-material-design-icons/Check.vue'

const props = defineProps<{
	service: Service
}>()

const emit = defineEmits<{
	(event: 'connect', service: Service): void
}>()

const configureManually = ref(false)
const editableService = ref<Service>(cloneService(props.service))

watch(() => props.service, (service) => {
	editableService.value = cloneService(service)
})

function cloneService(service: Service): Service {
	return { ...service }
}
</script>

<template>
	<div class="davc-section__fresh">
		<h3 class="title">
			{{ t('integration_davc', 'Connection') }}
		</h3>
		<div class="description">
			{{ t('integration_davc', 'Enter your server and account information then press connect.') }}
		</div>
		<div class="parameter">
			<label for="davc-account-description">
				{{ t('integration_davc', 'Account description') }}
			</label>
			<NcTextField
				id="davc-account-description"
				v-model="editableService.label"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:labelOutside="true"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Description for this account')" />
		</div>
		<div v-if="editableService.auth === 'BA'" class="parameter">
			<label for="davc-account-bauth-id">
				{{ t('integration_davc', 'Account ID') }}
			</label>
			<NcTextField
				id="davc-account-bauth-id"
				v-model="editableService.bauth_id"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Authentication ID for your account')" />
		</div>
		<div v-if="editableService.auth === 'BA'" class="parameter">
			<label for="davc-account-bauth-secret">
				{{ t('integration_davc', 'Account secret') }}
			</label>
			<NcPasswordField
				id="davc-account-bauth-secret"
				v-model="editableService.bauth_secret"
				type="password"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Authentication secret for your account')" />
		</div>
		<div v-if="editableService.auth === 'OA'" class="parameter">
			<label for="davc-account-oauth-id">
				{{ t('integration_davc', 'Account ID') }}
			</label>
			<NcTextField
				id="davc-account-oauth-id"
				v-model="editableService.oauth_id"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Authentication ID for your account')" />
		</div>
		<div v-if="editableService.auth === 'OA'" class="parameter">
			<label for="davc-account-oauth-token">
				{{ t('integration_davc', 'Account token') }}
			</label>
			<NcPasswordField
				id="davc-account-oauth-token"
				v-model="editableService.oauth_access_token"
				type="password"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Authentication secret for your account')" />
		</div>
		<div class="parameter">
			<label for="davc-service-authentication">
				{{ t('integration_davc', 'Authentication type') }}
			</label>
			<div class="radio-group">
				<NcCheckboxRadioSwitch
					v-model="editableService.auth"
					name="service_auth"
					type="radio"
					value="BA"
					buttonVariantGrouped="horizontal"
					:buttonVariant="true">
					{{ t('integration_davc', 'Basic') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="editableService.auth"
					name="service_auth"
					type="radio"
					value="OA"
					buttonVariantGrouped="horizontal"
					:buttonVariant="true">
					{{ t('integration_davc', 'OAuth') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="davc-service-address">
				{{ t('integration_davc', 'Service address') }}
			</label>
			<NcTextField
				id="davc-service-address"
				v-model="editableService.location_host"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Domain or IP address')" />
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="davc-service-protocol">
				{{ t('integration_davc', 'Service protocol') }}
			</label>
			<div class="radio-group">
				<NcCheckboxRadioSwitch
					v-model="editableService.location_protocol"
					name="service_protocol"
					type="radio"
					value="http"
					buttonVariantGrouped="horizontal"
					:buttonVariant="true">
					{{ t('integration_davc', 'http') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch
					v-model="editableService.location_protocol"
					name="service_protocol"
					type="radio"
					value="https"
					buttonVariantGrouped="horizontal"
					:buttonVariant="true">
					{{ t('integration_davc', 'https') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<div v-if="configureManually" class="parameter">
			<NcCheckboxRadioSwitch v-model="editableService.location_security" type="switch">
				{{ t('integration_davc', 'Secure Transport Verification (SSL Certificate Verification). Should always be ON, unless connecting to a service over a secure internal network') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="davc-service-port">
				{{ t('integration_davc', 'Service port') }}
			</label>
			<NcTextField
				id="davc-service-port"
				v-model="editableService.location_port"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Leave empty for default. http (80) https (443)')" />
		</div>
		<div v-if="configureManually" class="parameter">
			<label for="davc-service-path">
				{{ t('integration_davc', 'Service path') }}
			</label>
			<NcTextField
				id="davc-service-path"
				v-model="editableService.location_path"
				type="text"
				autocomplete="off"
				autocorrect="off"
				autocapitalize="none"
				:style="{ width: '48ch' }"
				:placeholder="t('integration_davc', 'Leave empty for default path (/.well-known/caldav)')" />
		</div>
		<div>
			<NcCheckboxRadioSwitch v-model="configureManually" type="switch">
				{{ t('integration_davc', 'Configure server manually') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div class="actions">
			<NcButton @click="emit('connect', editableService)">
				<template #icon>
					<CheckIcon />
				</template>
				{{ t('integration_davc', 'Connect') }}
			</NcButton>
		</div>
	</div>
</template>

<style scoped lang="scss">
.davc-section__fresh {
	.title {
		margin-bottom: 16px;
	}

	.description {
		margin-bottom: 20px;
	}

	.parameter {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 16px;

		label {
			min-width: 200px;
			font-weight: 500;
		}

		.radio-group {
			display: flex;
		}
	}

	.actions {
		display: flex;
		gap: 12px;
		margin-top: 24px;
		padding-top: 20px;
		border-top: 1px solid var(--color-border);
	}
}
</style>
