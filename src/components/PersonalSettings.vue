<template>
	<div id="restya_prefs" class="section">
		<h2>
			<a class="icon icon-restya" />
			{{ t('integration_restya', 'Restyaboard integration') }}
		</h2>
		<div id="restya-content">
			<div v-if="connected">
				<div class="restya-grid-form">
					<label>
						<a class="icon icon-checkmark-color" />
						{{ t('integration_restya', 'Connected as {username}', { username: state.user_name }) }}
					</label>
					<button @click="onLogoutClick">
						<span class="icon icon-close" />
						{{ t('integration_restya', 'Disconnect from Restyaboard') }}
					</button>
				</div>

				<div id="restya-search-block">
					<input
						id="search-restya"
						type="checkbox"
						class="checkbox"
						:checked="state.search_enabled"
						@input="onSearchChange">
					<label for="search-restya">{{ t('integration_restya', 'Enable unified search for tickets') }}</label>
					<br><br>
					<p v-if="state.search_enabled" class="settings-hint">
						<span class="icon icon-details" />
						{{ t('integration_restya', 'Warning, everything you type in the search bar will be sent to Restya.') }}
					</p>
					<input
						id="notification-restya"
						type="checkbox"
						class="checkbox"
						:checked="state.notification_enabled"
						@input="onNotificationChange">
					<label for="notification-restya">{{ t('integration_restya', 'Enable notifications for open tickets') }}</label>
				</div>
			</div>
			<div v-else>
				<h3>
					<span class="icon icon-timezone" />
					{{ t('integration_restya', 'Restyaboard') }}
				</h3>
				<div v-if="showOAuth">
					<button
						class="oauth-connect"
						@click="onOAuthClick">
						<span class="icon icon-external" />
						{{ t('integration_restya', 'Connect to Restyaboard') }}
					</button>
					<br><br>
				</div>
				<div v-else>
					<p class="settings-hint">
						{{ t('integration_github', 'You can get the access token from the Restyaboard Nextcloud app.') }}
						<a href="https://restya.com/board/apps/r_link_nextcloud" target="_blank" class="external">
							<span class="icon icon-external" />
							{{ t('integration_github', 'Restyaboard Nextcloud app') }}
						</a>
					</p>
					<br>
				</div>
				<h3>
					<span class="icon icon-home" />
					{{ t('integration_restya', 'Self-hosted Restyaboard Software') }} welcome
				</h3>
				<div class="restya-grid-form restya-sub">
					<label>
						<span class="icon icon-link" />
						{{ t('integration_restya', 'Restyaboard self-hosted instance address') }}
					</label>
					<input v-if="state.forced_instance_url"
						type="text"
						:value="state.forced_instance_url"
						:disabled="true"
						:placeholder="t('integration_restya', 'Restyaboard URL')">
					<input v-else
						v-model="state.url"
						type="text"
						:placeholder="t('integration_restya', 'Restyaboard URL')">
					<label v-show="state.forced_instance_url || state.url">
						<span class="icon icon-user" />
						{{ t('integration_restya', 'Access Token') }}
					</label>
					<input v-show="state.forced_instance_url || state.url"
						v-model="login"
						type="text"
						:placeholder="t('integration_restya', 'Restyaboard Access Token')"
						@keyup.enter="onSelfHostedAuth">
					<button v-show="state.forced_instance_url || state.url"
						:class="{ loading: connecting }"
						@click="onSelfHostedAuth">
						<span class="icon icon-external" />
						{{ t('integration_restya', 'Connect to this Restyaboard instance') }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_restya', 'user-config'),
			login: '',
			password: '',
			connecting: false,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_restya/oauth-redirect'),
		}
	},

	computed: {
		showOAuth() {
			return this.state.client_id && this.state.client_secret
		},
		connected() {
			return this.state.user_name && this.state.user_name !== ''
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const zmToken = urlParams.get('restyaToken')
		if (zmToken === 'success') {
			showSuccess(t('integration_restya', 'Successfully connected to Restyaboard!'))
		} else if (zmToken === 'error') {
			showError(t('integration_restya', 'OAuth access token could not be obtained:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onLogoutClick() {
			this.state.user_name = ''
			this.saveOptions({ user_name: '' })
		},
		onNotificationChange(e) {
			this.state.notification_enabled = e.target.checked
			this.saveOptions({ notification_enabled: this.state.notification_enabled ? '1' : '0' })
		},
		onSearchChange(e) {
			this.state.search_enabled = e.target.checked
			this.saveOptions({ search_enabled: this.state.search_enabled ? '1' : '0' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_restya/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_restya', 'Restyaboard options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_restya', 'Failed to save Restyaboard options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onSelfHostedAuth() {
			this.connecting = true
			const req = {
				url: this.state.url,
				login: this.login,
				password: '',
			}
			const url = generateUrl('/apps/integration_restya/soft-connect')
			axios.put(url, req)
				.then((response) => {
					this.state.user_name = response.data.user_name
					if (response.data.user_name === '') {
						if (response.data.error) {
							showError(t('integration_restya', 'Impossible to connect to Restyaboard instance') + ': ' + response.data.error)
						} else {
							showError(t('integration_restya', 'Login/password are invalid or account is locked'))
						}
					}
				})
				.catch((error) => {
					showError(
						t('integration_restya', 'Failed to connect to Restyaboard Software')
						+ ': ' + error.response?.request?.responseText
					)
				})
				.then(() => {
					this.connecting = false
				})
		},
		onOAuthClick() {
			const oauthState = Math.random().toString(36).substring(3)
			const scopes = [
				'offline_access',
				'read:me',
				'read:restya-work',
				'read:restya-user',
				'write:restya-work',
				'manage:restya-project',
				'manage:restya-configuration',
				'manage:restya-data-provider',
			]
			const requestUrl = 'https://auth.atlassian.com/authorize?client_id=' + encodeURIComponent(this.state.client_id)
				+ '&audience=api.atlassian.com'
				+ '&scope=' + encodeURIComponent(scopes.join(' '))
				+ '&response_type=code'
				+ '&prompt=consent'
				+ '&redirect_uri=' + encodeURIComponent(this.redirect_uri)
				+ '&state=' + encodeURIComponent(oauthState)

			const req = {
				values: {
					oauth_state: oauthState,
					url: '',
					redirect_uri: this.redirect_uri,
				},
			}
			const url = generateUrl('/apps/integration_restya/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_restya', 'Failed to save Restyaboard OAuth state')
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
.restya-sub,
.oauth-connect {
	margin-left: 40px;
}

#restya-search-block {
	margin-top: 30px;
}

.restya-grid-form label {
	line-height: 38px;
}

.restya-grid-form input {
	width: 100%;
}

.restya-grid-form {
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	button .icon {
		margin-bottom: -1px;
	}
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

#restya-content {
	margin-left: 40px;
}

#restya-search-block .icon {
	width: 22px;
}

</style>
