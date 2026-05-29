<!--
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onMounted, reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AccountRemoveIcon from 'vue-material-design-icons/AccountMinus.vue'
import AccountAddIcon from 'vue-material-design-icons/AccountPlus.vue'
import CalendarIcon from 'vue-material-design-icons/Calendar.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import ContactIcon from 'vue-material-design-icons/ContactsOutline.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import DavIcon from '../icons/DavIcon.vue'

// Types
interface SystemConfiguration {
	system_contacts: boolean
	system_events: boolean
}

interface Service {
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

interface Collection {
	id: string | null
	ccid: string
	label: string
	enabled?: boolean
	color?: string
	hlockhb?: number
	count?: number
}

// Reactive data
const readonly = ref<boolean>(true)
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

// UI State
const configureManually = ref<boolean>(false)
const connecting = ref<boolean>(false)

// Lifecycle
onMounted(() => {
	serviceList()
})

// Methods
function randomColor(): string {
	return '#' + (Math.random() * 0xFFFFFF << 0).toString(16).padStart(6, '0')
}

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
async function connectService(): Promise<void> {
	if (connecting.value) {
		return
	}
	connecting.value = true
	const uri = generateUrl('/apps/integration_davc/service/connect')
	const data = {
		service: selectedService.value,
	}
	try {
		const response = await axios.post(uri, data)
		if (response.data && response.data.id) {
			showSuccess(t('integration_davc', 'Successfully connected to account'))
			const connected = response.data as Service
			await serviceList()
			selectedService.value = configuredServices.value.find(
				(s) => String(s.id) === String(connected.id),
			) ?? connected
			await Promise.all([remoteCollectionsFetch(), localCollectionsFetch()])
		}
	} catch (error: unknown) {
		showError(t('integration_davc', 'Failed to authenticate with server')
			+ ': ' + getErrorResponseText(error))
	} finally {
		connecting.value = false
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
				color: randomColor(),
			})
		}
	} else {
		lCollection.enabled = e
		if (!lCollection.color) {
			lCollection.color = randomColor()
		}
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
				color: randomColor(),
			})
		}
	} else {
		eventsLocalCollections.value[lid].enabled = e
		if (!eventsLocalCollections.value[lid].color) {
			eventsLocalCollections.value[lid].color = randomColor()
		}
	}
}

const establishedContactCorrelation = computed(() => {
	return (rcid: string | null): boolean => {
		if (!rcid) {
			return false
		}
		const lCollection = contactsLocalCollections.value.find((i) => String(i.ccid) === String(rcid))
		if (typeof lCollection === 'undefined') {
			return false
		}
		if (typeof lCollection.enabled === 'undefined') {
			return true
		}
		return lCollection.enabled
	}
})

const establishedEventCorrelation = computed(() => {
	return (rcid: string | null): boolean => {
		if (!rcid) {
			return false
		}
		const lCollection = eventsLocalCollections.value.find((i) => String(i.ccid) === String(rcid))
		if (typeof lCollection === 'undefined') {
			return false
		}
		if (typeof lCollection.enabled === 'undefined') {
			return true
		}
		return lCollection.enabled
	}
})

function establishedContactCorrelationColor(ccid: string | null): string {
	if (!ccid) {
		return ''
	}
	const collection = contactsLocalCollections.value.find((i) => String(i.ccid) === String(ccid))
	return collection?.color ?? ''
}

function establishedEventCorrelationColor(ccid: string | null): string {
	if (!ccid) {
		return ''
	}
	const collection = eventsLocalCollections.value.find((i) => String(i.ccid) === String(ccid))
	return collection?.color ?? ''
}

function setContactCorrelationColor(rcid: string | null, value: string): void {
	if (!rcid) {
		return
	}
	const lCollection = contactsLocalCollections.value.find((i) => String(i.ccid) === String(rcid))
	if (lCollection) {
		lCollection.color = value
		return
	}
	const rCollection = contactsRemoteCollections.value.find((i) => String(i.id) === String(rcid))
	if (rCollection && rCollection.id) {
		contactsLocalCollections.value.push({
			id: null,
			ccid: rCollection.id,
			label: rCollection.label,
			enabled: false,
			color: value,
		})
	}
}

function setEventCorrelationColor(rcid: string | null, value: string): void {
	if (!rcid) {
		return
	}
	const lCollection = eventsLocalCollections.value.find((i) => String(i.ccid) === String(rcid))
	if (lCollection) {
		lCollection.color = value
		return
	}
	const rCollection = eventsRemoteCollections.value.find((i) => String(i.id) === String(rcid))
	if (rCollection && rCollection.id) {
		eventsLocalCollections.value.push({
			id: null,
			ccid: rCollection.id,
			label: rCollection.label,
			enabled: false,
			color: value,
		})
	}
}

function establishedContactCorrelationHarmonized(ccid: string | null): number {
	if (!ccid) {
		return 0
	}
	const collection = contactsLocalCollections.value.find((i) => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.hlockhb || 0
	} else {
		return 0
	}
}

function establishedEventCorrelationHarmonized(ccid: string | null): number {
	if (!ccid) {
		return 0
	}
	const collection = eventsLocalCollections.value.find((i) => String(i.ccid) === String(ccid))
	if (typeof collection !== 'undefined') {
		return collection.hlockhb || 0
	} else {
		return 0
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
			<NcButton :disabled="selectedService === null" @click="disconnectService()">
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
		<div v-if="selectedService === null" class="davc-section__empty">
			<NcEmptyContent description="Please select a configured service or add a new service.">
				<template #icon>
					<AccountAddIcon />
				</template>
				<template #name>
					<h2 class="empty-content__name">
						{{ t('integration_davc', 'No service selected') }}
					</h2>
				</template>
				<template #action>
					<NcButton variant="primary" @click="freshService()">
						<template #icon>
							<AccountAddIcon :size="20" />
						</template>
						{{ t('integration_davc', 'Add Service') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</div>
		<div v-if="selectedService !== null && !Boolean(selectedService.connected)" class="davc-section__fresh">
			<h3 class="title">
				{{ t('integration_davc', 'Connection') }}
			</h3>
			<div class="description">
				{{ t('integration_davc', 'Enter your server and account information then press connect.') }}
			</div>
			<div class="parameter">
				<label for="davc-account-description">
					{{ t('integration_davc', 'Account Description') }}
				</label>
				<NcTextField
					id="davc-account-description"
					v-model="selectedService.label"
					type="text"
					autocomplete="off"
					autocorrect="off"
					autocapitalize="none"
					:labelOutside="true"
					:style="{ width: '48ch' }"
					:placeholder="t('integration_davc', 'Description for this Account')" />
			</div>
			<div v-if="selectedService.auth === 'BA'" class="parameter">
				<label for="davc-account-bauth-id">
					{{ t('integration_davc', 'Account ID') }}
				</label>
				<NcTextField
					id="davc-account-bauth-id"
					v-model="selectedService.bauth_id"
					type="text"
					autocomplete="off"
					autocorrect="off"
					autocapitalize="none"
					:style="{ width: '48ch' }"
					:placeholder="t('integration_davc', 'Authentication ID for your Account')" />
			</div>
			<div v-if="selectedService.auth === 'BA'" class="parameter">
				<label for="davc-account-bauth-secret">
					{{ t('integration_davc', 'Account Secret') }}
				</label>
				<NcPasswordField
					id="davc-account-bauth-secret"
					v-model="selectedService.bauth_secret"
					type="password"
					autocomplete="off"
					autocorrect="off"
					autocapitalize="none"
					:style="{ width: '48ch' }"
					:placeholder="t('integration_davc', 'Authentication secret for your Account')" />
			</div>
			<div v-if="selectedService.auth === 'OA'" class="parameter">
				<label for="davc-account-oauth-id">
					{{ t('integration_davc', 'Account ID') }}
				</label>
				<NcTextField
					id="davc-account-oauth-id"
					v-model="selectedService.oauth_id"
					type="text"
					autocomplete="off"
					autocorrect="off"
					autocapitalize="none"
					:style="{ width: '48ch' }"
					:placeholder="t('integration_davc', 'Authentication ID for your Account')" />
			</div>
			<div v-if="selectedService.auth === 'OA'" class="parameter">
				<label for="davc-account-oauth-token">
					{{ t('integration_davc', 'Account Token') }}
				</label>
				<NcPasswordField
					id="davc-account-oauth-token"
					v-model="selectedService.oauth_access_token"
					type="password"
					autocomplete="off"
					autocorrect="off"
					autocapitalize="none"
					:style="{ width: '48ch' }"
					:placeholder="t('integration_davc', 'Authentication secret for your Account')" />
			</div>
			<div class="parameter">
				<label for="davc-service-authentication">
					{{ t('integration_davc', 'Authentication Type') }}
				</label>
				<div class="radio-group">
					<NcCheckboxRadioSwitch
						v-model="selectedService.auth"
						name="service_auth"
						type="radio"
						value="BA"
						buttonVariantGrouped="horizontal"
						:buttonVariant="true">
						{{ t('integration_davc', 'Basic') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						v-model="selectedService.auth"
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
					{{ t('integration_davc', 'Service Address') }}
				</label>
				<NcTextField
					id="davc-service-address"
					v-model="selectedService.location_host"
					type="text"
					autocomplete="off"
					autocorrect="off"
					autocapitalize="none"
					:style="{ width: '48ch' }"
					:placeholder="t('integration_davc', 'Domain or IP Address')" />
			</div>
			<div v-if="configureManually" class="parameter">
				<label for="davc-service-protocol">
					{{ t('integration_davc', 'Service Protocol') }}
				</label>
				<div class="radio-group">
					<NcCheckboxRadioSwitch
						v-model="selectedService.location_protocol"
						name="service_protocol"
						type="radio"
						value="http"
						buttonVariantGrouped="horizontal"
						:buttonVariant="true">
						{{ t('integration_davc', 'http') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						v-model="selectedService.location_protocol"
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
				<NcCheckboxRadioSwitch v-model="selectedService.location_security" type="switch">
					{{ t('integration_davc', 'Secure Transport Verification (SSL Certificate Verification). Should always be ON, unless connecting to a service over a secure internal network') }}
				</NcCheckboxRadioSwitch>
			</div>
			<div v-if="configureManually" class="parameter">
				<label for="davc-service-port">
					{{ t('integration_davc', 'Service Port') }}
				</label>
				<NcTextField
					id="davc-service-port"
					v-model="selectedService.location_port"
					type="text"
					autocomplete="off"
					autocorrect="off"
					autocapitalize="none"
					:style="{ width: '48ch' }"
					:placeholder="t('integration_davc', 'Leave empty for default. http (80) https (443)')" />
			</div>
			<div v-if="configureManually" class="parameter">
				<label for="davc-service-path">
					{{ t('integration_davc', 'Service Path') }}
				</label>
				<NcTextField
					id="davc-service-path"
					v-model="selectedService.location_path"
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
				<NcButton :disabled="connecting" @click="connectService()">
					<template #icon>
						<NcLoadingIcon v-if="connecting" :size="20" />
						<CheckIcon v-else />
					</template>
					{{ connecting ? t('integration_davc', 'Connecting…') : t('integration_davc', 'Connect') }}
				</NcButton>
			</div>
		</div>
		<div v-if="selectedService !== null && Boolean(selectedService.connected)" class="davc-section__connected">
			<div class="connection-status">
				<h3 class="connection-status__title">
					{{ t('integration_davc', 'Connection') }}
				</h3>
				<div class="connection-status__overview">
					<DavIcon />
					<span>{{ t('integration_davc', 'Connected as {0} to {1}', {0: selectedService.bauth_id || '', 1: selectedService.location_host || ''}) }}</span>
				</div>
				<div class="connection-status__harmonization">
					{{ t('integration_davc', 'Synchronization was last started on ') }} {{ formatDate(selectedService.harmonization_start) }}
					{{ t('integration_davc', 'and finished on ') }} {{ formatDate(selectedService.harmonization_end) }}
				</div>
			</div>
			<div class="connection-correlations-contacts">
				<h3>{{ t('integration_davc', 'Contacts') }}</h3>
				<div v-if="systemConfiguration.system_contacts && contactsRemoteSupported" class="instruction-message">
					{{ t('integration_davc', 'Select the contacts collection(s) you wish to synchronize by using the toggle') }}
				</div>
				<div v-if="!systemConfiguration.system_contacts" class="warning-message">
					{{ t('integration_davc', 'The contacts app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
				</div>
				<div v-if="!contactsRemoteSupported" class="warning-message">
					{{ t('integration_davc', 'The connected service does not support contacts') }}
				</div>
				<div v-if="systemConfiguration.system_contacts && contactsRemoteSupported" class="collections-list">
					<ul v-if="contactsRemoteCollections.length > 0">
						<li v-for="ritem in contactsRemoteCollections" :key="ritem.id" class="collections-list-item">
							<NcCheckboxRadioSwitch
								type="switch"
								:modelValue="establishedContactCorrelation(ritem.id)"
								@update:modelValue="changeContactCorrelation(ritem.id, $event)" />
							<NcColorPicker
								:modelValue="establishedContactCorrelationColor(ritem.id)"
								:advancedFields="true"
								@update:modelValue="setContactCorrelationColor(ritem.id, $event)">
								<ContactIcon :inline="true" :style="{ color: establishedContactCorrelationColor(ritem.id) }" />
							</NcColorPicker>
							<label>
								{{ ritem.label }}
							</label>
							<label v-if="ritem.count && ritem.count > 0">
								({{ ritem.count }} {{ t('integration_davc', 'Contacts') }})
							</label>
							<label v-if="establishedContactCorrelationHarmonized(ritem.id) > 0">
								{{ t('integration_davc', 'Last Harmonized') }} {{ formatDate(establishedContactCorrelationHarmonized(ritem.id)) }}
							</label>
							<label v-else>
								{{ t('integration_davc', 'Last Harmonized never') }}
							</label>
						</li>
					</ul>
					<div v-else-if="contactsRemoteCollections.length === 0" class="empty-message">
						{{ t('integration_davc', 'No contacts collections were found in the connected account') }}
					</div>
					<div v-else class="loading-message">
						{{ t('integration_davc', 'Loading contacts collections from the connected account') }}
					</div>
				</div>
			</div>
			<div class="connection-correlations-events">
				<h3>{{ t('integration_davc', 'Calendars') }}</h3>
				<div v-if="systemConfiguration.system_events && eventsRemoteSupported" class="instruction-message">
					{{ t('integration_davc', 'Select the events collection(s) you wish to synchronize by using the toggle') }}
				</div>
				<div v-if="!systemConfiguration.system_events" class="warning-message">
					{{ t('integration_davc', 'The calendar app is either disabled or not installed. Please contact your administrator to install or enable the app') }}
				</div>
				<div v-if="!eventsRemoteSupported" class="warning-message">
					{{ t('integration_davc', 'The connected service does not support events') }}
				</div>
				<div v-if="systemConfiguration.system_events && eventsRemoteSupported" class="collections-list">
					<ul v-if="eventsRemoteCollections.length > 0">
						<li v-for="ritem in eventsRemoteCollections" :key="ritem.id" class="collections-list-item">
							<NcCheckboxRadioSwitch
								type="switch"
								:modelValue="establishedEventCorrelation(ritem.id)"
								@update:modelValue="changeEventCorrelation(ritem.id, $event)" />
							<NcColorPicker
								:modelValue="establishedEventCorrelationColor(ritem.id)"
								:advancedFields="true"
								@update:modelValue="setEventCorrelationColor(ritem.id, $event)">
								<CalendarIcon :inline="true" :style="{ color: establishedEventCorrelationColor(ritem.id) }" />
							</NcColorPicker>
							<label>
								{{ ritem.label }}
							</label>
							<label v-if="ritem.count && ritem.count > 0">
								({{ ritem.count }} {{ t('integration_davc', 'Events') }})
							</label>
							<label v-if="establishedEventCorrelationHarmonized(ritem.id) > 0">
								{{ t('integration_davc', 'Last Harmonized') }} {{ formatDate(establishedEventCorrelationHarmonized(ritem.id)) }}
							</label>
							<label v-else>
								{{ t('integration_davc', 'Last Harmonized never') }}
							</label>
						</li>
					</ul>
					<div v-else-if="eventsRemoteCollections.length === 0" class="empty-message">
						{{ t('integration_davc', 'No events collections were found in the connected account') }}
					</div>
					<div v-else class="loading-message">
						{{ t('integration_davc', 'Loading events collections from the connected account') }}
					</div>
				</div>
			</div>
			<div class="actions">
				<NcButton @click="modifyService()">
					<template #icon>
						<CheckIcon />
					</template>
					{{ t('integration_davc', 'Save') }}
				</NcButton>
				<NcButton @click="harmonizeService()">
					<template #icon>
						<LinkIcon />
					</template>
					{{ t('integration_davc', 'Harmonize') }}
				</NcButton>
				<NcButton @click="disconnectService()">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('integration_davc', 'Disconnect') }}
				</NcButton>
			</div>
		</div>
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

.davc-section__empty {
	margin-top: 40px;
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 400px;
	width: 100%;

	.empty-content__name {
		margin: 0;
		font-size: 20px;
		font-weight: bold;
	}
}

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

.davc-section__connected {
	margin-top: 20px;
	border-top: 1px solid var(--color-border);
	border-bottom: 1px solid var(--color-border);

	.connection-status {
		margin-bottom: 30px;

		.connection-status__title {
			margin-bottom: 16px;
			font-size: 18px;
			font-weight: bold;
		}

		.connection-status__overview {
			display: flex;
			align-items: center;
			gap: 8px;
			margin-bottom: 12px;
			padding: 12px;

			span {
				font-weight: 500;
			}
		}

		.connection-status__harmonization {
			font-size: 14px;
			color: var(--color-text-maxcontrast);
			margin-left: 12px;
		}
	}

	.connection-correlations-contacts,
	.connection-correlations-events {
		margin-bottom: 24px;

		h3 {
			margin-bottom: 12px;
			font-size: 18px;
			font-weight: bold;
		}

		ul {
			list-style: none;
			padding: 0;
			margin: 0;

			.collections-list-item {
				display: flex;
				align-items: center;
				padding: 12px;

				label {
					flex: 1;
					font-weight: 500;

					&:last-child {
						font-size: 12px;
						font-weight: normal;
					}
				}
			}
		}
	}

	.actions {
		display: flex;
		gap: 12px;
		margin-top: 24px;
		padding-top: 20px;
	}
}
</style>
