<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Collection } from '../types/Collection.ts'
import type { Service } from '../types/Service.ts'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { onMounted, reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import AccountRemoveIcon from 'vue-material-design-icons/AccountMinus.vue'
import AccountAddIcon from 'vue-material-design-icons/AccountPlus.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import SettingsConnectedService from '../components/SettingsConnectedService.vue'
import SettingsEmptyState from '../components/SettingsEmptyState.vue'
import SettingsFreshService from '../components/SettingsFreshService.vue'
import DavIcon from '../icons/DavIcon.vue'

// Types
interface SystemConfiguration {
	system_contacts: boolean
	system_events: boolean
}

// Reactive data
const systemConfiguration = reactive<SystemConfiguration>(loadState('integration_davc', 'system-configuration') as SystemConfiguration)

// Services
const configuredServices = ref<Service[]>([])
const selectedService = ref<Service | null>(null)

// Contacts
const contactsRemoteSupported = ref<boolean>(false)
const contactsRemoteCollections = ref<Collection[]>([])
const contactsLocalCollections = ref<Collection[]>([])

// Events/Calendars
const eventsRemoteSupported = ref<boolean>(false)
const eventsRemoteCollections = ref<Collection[]>([])
const eventsLocalCollections = ref<Collection[]>([])

// Lifecycle
onMounted(() => {
	serviceList()
})

// Methods
function formatDate(dt: number | undefined): string {
	if (dt) {
		return (new Date(dt * 1000)).toLocaleString()
	} else {
		return 'never'
	}
}

function getErrorResponseText(error: unknown): string {
	if (typeof error !== 'object' || error === null || !('response' in error)) {
		return ''
	}

	const { response } = error as { response?: { request?: { responseText?: string } } }
	return response?.request?.responseText ?? ''
}

function freshService(): void {
	selectedService.value = { label: 'New Connection' } as Service
}
async function connectService(service: Service): Promise<void> {
	const uri = generateUrl('/apps/integration_davc/service/connect')
	const data = {
		service,
	}
	try {
		const response = await axios.post(uri, data)
		if (response.data === 'success') {
			showSuccess('Successfully connected to account')
			if (selectedService.value) {
				selectedService.value.connected = 1
			}
			serviceList()
			remoteCollectionsFetch()
			localCollectionsFetch()
			selectedService.value = service
		}
	} catch (error: unknown) {
		showError(t('integration_davc', 'Failed to authenticate with server')
			+ ': ' + getErrorResponseText(error))
	}
}

async function disconnectService(): Promise<void> {
	const uri = generateUrl('/apps/integration_davc/service/disconnect')
	const data = {
		sid: selectedService.value?.id,
	}
	try {
		await axios.post(uri, data)
		showSuccess(t('integration_davc', 'Successfully disconnected from account'))
		// Reset state
		selectedService.value = null
		// contacts
		contactsRemoteSupported.value = false
		contactsRemoteCollections.value = []
		contactsLocalCollections.value = []
		// events
		eventsRemoteSupported.value = false
		eventsRemoteCollections.value = []
		eventsLocalCollections.value = []
		// refresh service list
		serviceList()
	} catch (error: unknown) {
		showError(t('integration_davc', 'Failed to disconnect from account')
			+ ': ' + getErrorResponseText(error))
	}
}

function modifyService(): void {
	localCollectionsDeposit()
}

async function harmonizeService(): Promise<void> {
	const uri = generateUrl('/apps/integration_davc/service/harmonize')
	const data = {
		sid: selectedService.value?.id,
	}
	try {
		await axios.post(uri, data)
		showSuccess(t('integration_davc', 'Synchronization Successful'))
	} catch (error: unknown) {
		showError(t('integration_davc', 'Synchronization Failed')
			+ ': ' + getErrorResponseText(error))
	}
}

async function serviceList(): Promise<void> {
	const uri = generateUrl('/apps/integration_davc/service/list')
	try {
		const response = await axios.get(uri)
		if (response.data) {
			configuredServices.value = Object.values(response.data)
			showSuccess(t('integration_davc', 'Found {count} Configured Services', { count: configuredServices.value.length }))
		}
	} catch (error: unknown) {
		showError(t('integration_davc', 'Failed to load service list')
			+ ': ' + getErrorResponseText(error))
	}
}

function serviceSelect(option: Service | null): void {
	if (!option) {
		return
	}
	selectedService.value = option
	remoteCollectionsFetch()
	localCollectionsFetch()
}
async function remoteCollectionsFetch(): Promise<void> {
	const uri = generateUrl('/apps/integration_davc/remote/collections/fetch')
	const params = {
		sid: selectedService.value?.id,
	}
	try {
		const response = await axios.get(uri, { params })
		if (response.data.ContactsSupported) {
			contactsRemoteSupported.value = response.data.ContactsSupported
			contactsRemoteCollections.value = response.data.ContactsCollections
			showSuccess(t('integration_davc', 'Found {count} Remote Contacts Collections', { count: contactsRemoteCollections.value.length }))
		}
		if (response.data.EventsSupported) {
			eventsRemoteSupported.value = response.data.EventsSupported
			eventsRemoteCollections.value = response.data.EventsCollections
			showSuccess(t('integration_davc', 'Found {count} Remote Events Collections', { count: eventsRemoteCollections.value.length }))
		}
	} catch (error: unknown) {
		showError(t('integration_davc', 'Failed to load remote collections')
			+ ': ' + getErrorResponseText(error))
	}
}

async function localCollectionsFetch(): Promise<void> {
	const uri = generateUrl('/apps/integration_davc/local/collections/fetch')
	const params = {
		sid: selectedService.value?.id,
	}
	try {
		const response = await axios.get(uri, { params })
		if (response.data.ContactCollections) {
			contactsLocalCollections.value = response.data.ContactCollections
			showSuccess(t('integration_davc', 'Found {count} Local Contact Collections', { count: contactsLocalCollections.value.length }))
		}
		if (response.data.EventCollections) {
			eventsLocalCollections.value = response.data.EventCollections
			showSuccess(t('integration_davc', 'Found {count} Local Event Collections', { count: eventsLocalCollections.value.length }))
		}
	} catch (error: unknown) {
		showError(t('integration_davc', 'Failed to load remote collections')
			+ ': ' + getErrorResponseText(error))
	}
}

async function localCollectionsDeposit(): Promise<void> {
	const uri = generateUrl('/apps/integration_davc/local/collections/deposit')
	const data = {
		sid: selectedService.value?.id,
		ContactCorrelations: contactsLocalCollections.value,
		EventCorrelations: eventsLocalCollections.value,
	}
	try {
		await axios.post(uri, data)
		showSuccess(t('integration_davc', 'Saved correlations'))
		localCollectionsFetch()
	} catch (error: unknown) {
		showError(t('integration_davc', 'Failed to save correlations')
			+ ': ' + getErrorResponseText(error))
	}
}
function changeContactCorrelation(rcid: string | null, e: boolean): void {
	if (!rcid) {
		return
	}
	const lCollection = contactsLocalCollections.value.find((i) => String(i.ccid) === String(rcid))

	if (lCollection === undefined) {
		const rCollection = contactsRemoteCollections.value.find((i) => String(i.id) === String(rcid))
		if (rCollection && rCollection.id) {
			contactsLocalCollections.value.push({
				id: null,
				ccid: rCollection.id,
				label: rCollection.label,
				enabled: e,
			})
		}
	} else {
		lCollection.enabled = e
	}
}

function changeEventCorrelation(rcid: string | null, e: boolean): void {
	if (!rcid) {
		return
	}
	const lid = eventsLocalCollections.value.findIndex((i) => String(i.ccid) === String(rcid))

	if (lid === -1) {
		const rCollection = eventsRemoteCollections.value.find((i) => String(i.id) === String(rcid))
		if (rCollection && rCollection.id) {
			eventsLocalCollections.value.push({
				id: null,
				ccid: rCollection.id,
				label: rCollection.label,
				enabled: e,
			})
		}
	} else {
		eventsLocalCollections.value[lid].enabled = e
	}
}

</script>

<template>
	<div class="davc-settings">
		<div class="davc-section__title">
			<DavIcon class="logo" />
			<span class="label">
				{{ t('integration_davc', 'DAV Connector') }}
			</span>
		</div>
		<div class="davc-section__selector">
			<label>
				{{ t('integration_davc', 'Services') }}
			</label>
			<NcSelect
				v-model="selectedService"
				:clearable="false"
				:searchable="false"
				:options="configuredServices"
				@option:selected="serviceSelect" />
			<NcButton @click="disconnectService()">
				<template #icon>
					<AccountRemoveIcon :size="20" />
				</template>
			</NcButton>
			<NcButton @click="freshService()">
				<template #icon>
					<AccountAddIcon :size="20" />
				</template>
			</NcButton>
		</div>

		<SettingsEmptyState v-if="selectedService === null" @addService="freshService()" />

		<SettingsFreshService
			v-if="selectedService !== null && !Boolean(selectedService.connected)"
			:service="selectedService"
			@connect="connectService($event)" />

		<SettingsConnectedService
			v-if="selectedService !== null && Boolean(selectedService.connected)"
			:service="selectedService"
			:systemConfiguration="systemConfiguration"
			:contactsRemoteSupported="contactsRemoteSupported"
			:contactsRemoteCollections="contactsRemoteCollections"
			:contactsLocalCollections="contactsLocalCollections"
			:eventsRemoteSupported="eventsRemoteSupported"
			:eventsRemoteCollections="eventsRemoteCollections"
			:eventsLocalCollections="eventsLocalCollections"
			:formatDate="formatDate"
			:changeContactCorrelation="changeContactCorrelation"
			:changeEventCorrelation="changeEventCorrelation"
			@save="modifyService()"
			@harmonize="harmonizeService()"
			@disconnect="disconnectService()" />
	</div>
</template>

<style scoped lang="scss">
.davc-settings {
	padding: 30px;
	max-width: 100%;
	width: 100%;
}

.davc-section__title {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 20px;

	.logo {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.label {
		font-size: 24px;
		font-weight: bold;
		line-height: 1;
		display: flex;
		align-items: center;
	}
}

.davc-section__selector {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 20px;

	label {
		font-weight: bold;
		white-space: nowrap;
	}
}

</style>
