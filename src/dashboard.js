/* jshint esversion: 6 */

/**
 * Nextcloud - restya
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Restya <info@restya.com>
 * @copyright Restya 2021
 */

import Vue from 'vue'
import './bootstrap'
import Dashboard from './views/Dashboard'

document.addEventListener('DOMContentLoaded', function() {

	OCA.Dashboard.register('restya_notifications', (el, { widget }) => {
		const View = Vue.extend(Dashboard)
		new View({
			propsData: { title: widget.title },
		}).$mount(el)
	})

})
