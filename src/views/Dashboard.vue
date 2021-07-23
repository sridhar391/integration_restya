<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template #empty-content>
			<EmptyContent
				v-if="emptyContentMessage"
				:icon="emptyContentIcon">
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a class="button" :href="settingsUrl">
							{{ t('integration_restya', 'Connect to Restya') }}
						</a>
					</div>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import moment from '@nextcloud/moment'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget, EmptyContent,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notifications: [],
			restyaUrl: null,
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			darkThemeColor: OCA.Accessibility?.theme === 'dark' ? 'ffffff' : '181818',
		}
	},

	computed: {
		showMoreUrl() {
			return this.restyaUrl
		},
		items() {
			// only display last apparition of an issue
			const seenKeys = []
			const items = this.notifications.filter((n) => {
				if (seenKeys.includes(n.id)) {
					return false
				} else {
					seenKeys.push(n.id)
					return true
				}
			})

			return items.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getCreatorAvatarUrl(n),
					avatarUsername: this.getCreatorDisplayName(n),
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].modified : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_restya', 'No Restyaboard account connected')
			} else if (this.state === 'error') {
				return t('integration_restya', 'Error connecting to Restya')
			} else if (this.state === 'ok') {
				return t('integration_restya', 'No Restyaboard notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return 'icon-restya'
			} else if (this.state === 'error') {
				return 'icon-close'
			} else if (this.state === 'ok') {
				return 'icon-checkmark'
			}
			return 'icon-checkmark'
		},
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/integration_restya/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_restya', 'Failed to get Restyaboard notifications'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].modified)) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		filter(notifications) {
			return notifications
		},
		getNotificationTarget(n) {
			return n.restyaUrl + '#/board/' + n.board_id + '/card/' + n.card_id
		},
		getUniqueKey(n) {
			return n.id + ':' + n.modified
		},
		getCreatorDisplayName(n) {
			return n.username
		},
		getCreatorAvatarUrl(n) {
			return (n.profile_picture_path) ? generateUrl('/apps/integration_restya/avatar?') + encodeURIComponent('accountId') + '=' + n.profile_picture_path : ''
		},
		getNotificationTypeImage(n) {
			// if (n.type_lookup_id === 2 || n.type === 'update') {
			// return generateUrl('/svg/integration_restya/rename?color=ffffff')
			// } else if (n.type_lookup_id === 3 || n.type === 'create') {
			// return generateUrl('/svg/integration_restya/add?color=ffffff')
			// }
			return generateUrl('/svg/core/actions/sound?color=' + this.darkThemeColor)
		},
		getSubline(n) {
			return this.getCreatorDisplayName(n) + ' #' + n.card_id
		},
		getTargetTitle(n) {
			return n.comment
		},
		getFormattedDate(n) {
			return moment(n.modified).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
