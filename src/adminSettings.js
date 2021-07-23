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
import AdminSettings from './components/AdminSettings'

// eslint-disable-next-line
'use strict'

// eslint-disable-next-line
new Vue({
	el: '#restya_prefs',
	render: h => h(AdminSettings),
})
