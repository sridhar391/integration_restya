<template>
	<div id="restya_prefs" class="section">
		<h2>
			<a class="icon icon-restya" />
			{{ t('integration_restya', 'Restyaboard integration') }}
		</h2>
		<p class="settings-hint">
			{{ t('integration_restya', 'If you want to allow your Nextcloud users to use OAuth to authenticate to Restyaboard, create an application in your Restyaboard admin settings and set the ID and secret here.') }}
			<a class="external" href="https://developer.atlassian.com/apps">
				{{ t('integration_restya', 'Restyaboard app settings') }}
			</a>
			<br><br>
			<span class="icon icon-details" />
			{{ t('integration_restya', 'Make sure you set the redirection/callback URL to') }}
			<b> {{ redirect_uri }} </b>
			<br><br>
			<span class="icon icon-details" />
			{{ t('integration_restya', 'Don\'t forget to make your Restyaboard OAuth application public.') }}
			<a class="external" href="https://developer.atlassian.com/cloud/restya/platform/oauth-2-authorization-code-grants-3lo-for-apps/#publishing-your-oauth-2-0--3lo--app">
				{{ t('integration_restya', 'How to make Restyaboard OAuth public') }}
			</a>
			<br><br>
			{{ t('integration_restya', 'Put the "Client ID" and "Client secret" below. Your Nextcloud users will then see a "Connect to Restyaboard" button in their personal settings.') }}
		</p>
		<div class="grid-form">
			<label for="restya-client-id">
				<a class="icon icon-category-auth" />
				{{ t('integration_restya', 'Client ID') }}
			</label>
			<input id="restya-client-id"
				v-model="state.client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_restya', 'ID of your application')"
				@focus="readonly = false"
				@input="onInput">
			<label for="restya-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('integration_restya', 'Client secret') }}
			</label>
			<input id="restya-client-secret"
				v-model="state.client_secret"
				type="password"
				:readonly="readonly"
				:placeholder="t('integration_restya', 'Your application secret')"
				@focus="readonly = false"
				@input="onInput">
		</div>
		<br>
		<div class="grid-form">
			<label for="restya-forced-instance">
				<a class="icon icon-link" />
				{{ t('integration_restya', 'Restrict self hosted URL to') }}
			</label>
			<input id="restya-forced-instance"
				v-model="state.forced_instance_url"
				type="text"
				:placeholder="t('integration_restya', 'Instance address')"
				@input="onInput">
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_restya', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_restya/oauth-redirect'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				this.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					forced_instance_url: this.state.forced_instance_url,
				},
			}
			const url = generateUrl('/apps/integration_restya/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_restya', 'Restyaboard admin options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_restya', 'Failed to save Restyaboard admin options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.grid-form label {
	line-height: 38px;
}

.grid-form input {
	width: 100%;
}

.grid-form {
	max-width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
}

#restya_prefs .icon {
	display: inline-block;
	width: 32px;
}

#restya_prefs .grid-form .icon {
	margin-bottom: -3px;
}

.icon-restya {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-restya {
	background-image: url(./../../img/app.svg);
}

</style>
