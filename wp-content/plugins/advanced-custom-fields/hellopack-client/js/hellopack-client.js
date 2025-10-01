/* global _hellopackClient, tb_click */

/**
 * HelloPack Client sripts.
 *
 * @since 2.0.0
 */
(function( $ ) {
	'use strict';
	var dialog, hellopackClient = {

		cache: {},

		init: function () {
			this.bindEvents()
		},

		bindEvents: function () {
			const self = this

			self.addItem()
			self.removeItem()
			self.tabbedNav()

			$(document).on('click', '.hellopack-card a.thickbox', function () {
				tb_click.call(this)
				$('#TB_title').css({ 'background-color': '#23282d', color: '#cfcfcf' })
				return false
			})
		},

		addItem: function () {
			$(document).on('click', '.add-hellopack-client-item', function (event) {
				const id = 'hellopack-client-dialog-form'
				event.preventDefault()

				if ($('#' + id).length === 0) {
					$('body').append(wp.template(id))
				}

				dialog = $('#' + id).dialog({
					autoOpen: true,
					modal: true,
					width: 350,
					buttons: {
						Save: {
							text: _hellopackClient.i18n.save,
							click: function () {
								const form = $(this)
								let request; let token; let input_id

								form.on('submit', function (event) {
									event.preventDefault()
								})

								token = form.find('input[name="token"]').val()
								input_id = form.find('input[name="id"]').val()

								request = wp.ajax.post(_hellopackClient.action + '_add_item', {
									nonce: _hellopackClient.nonce,
									token,
									id: input_id
								})

								request.done(function (response) {
									const item = wp.template('hellopack-client-item')
									const card = wp.template('hellopack-client-card')
									const button = wp.template('hellopack-client-auth-check-button')

									$('.nav-tab-wrapper').find('[data-id="' + response.type + '"]').removeClass('hidden')

									response.item.type = response.type
									$('#' + response.type + 's').append(card(response.item)).removeClass('hidden')

									$('#hellopack-client-items').append(item({
										name: response.name,
										token: response.token,
										id: response.id,
										key: response.key,
										type: response.type,
										authorized: response.authorized
									}))

									if ($('.auth-check-button').length === 0) {
										$('p.submit').append(button)
									}

									dialog.dialog('close')
									hellopackClient.addReadmore()
								})

								request.fail(function (response) {
									const template = wp.template('hellopack-client-dialog-error')
									const data = {
										message: (response.message ? response.message : _hellopackClient.i18n.error)
									}

									dialog.find('.notice').remove()
									dialog.find('form').prepend(template(data))
									dialog.find('.notice').fadeIn('fast')
								})
							}
						},
						Cancel: {
							text: _hellopackClient.i18n.cancel,
							click: function () {
								dialog.dialog('close')
							}
						}
					},
					close: function () {
						dialog.find('.notice').remove()
						dialog.find('form')[0].reset()
					}
				})
			})
		},

		removeItem: function () {
			$(document).on('click', '#hellopack-client-items .item-delete', function (event) {
				const self = this; const id = 'hellopack-client-dialog-remove'
				event.preventDefault()

				if ($('#' + id).length === 0) {
					$('body').append(wp.template(id))
				}

				dialog = $('#' + id).dialog({
					autoOpen: true,
					modal: true,
					width: 350,
					buttons: {
						Save: {
							text: _hellopackClient.i18n.remove,
							click: function () {
								const form = $(this)
								let request; let id

								form.on('submit', function (submit_event) {
									submit_event.preventDefault()
								})

								id = $(self).parents('li').data('id')

								request = wp.ajax.post(_hellopackClient.action + '_remove_item', {
									nonce: _hellopackClient.nonce,
									id
								})

								request.done(function () {
									const item = $('.col[data-id="' + id + '"]')
									const type = item.find('.hellopack-card').hasClass('theme') ? 'theme' : 'plugin'

									item.remove()

									if ($('#' + type + 's').find('.col').length === 0) {
										$('.nav-tab-wrapper').find('[data-id="' + type + '"]').addClass('hidden')
										$('#' + type + 's').addClass('hidden')
									}

									$(self).parents('li').remove()

									$('#hellopack-client-items li').each(function (index) {
										$(this).find('input').each(function () {
											$(this).attr('name', $(this).attr('name').replace(/\[\d\]/g, '[' + index + ']'))
										})
									})

									if ($('.auth-check-button').length !== 0 && $('#hellopack-client-items li').length === 0) {
										$('p.submit .auth-check-button').remove()
									}

									dialog.dialog('close')
								})

								request.fail(function (response) {
									const template = wp.template('hellopack-client-dialog-error')
									const data = {
										message: response.message ? response.message : _hellopackClient.i18n.error
									}

									dialog.find('.notice').remove()
									dialog.find('form').prepend(template(data))
									dialog.find('.notice').fadeIn('fast')
								})
							}
						},
						Cancel: {
							text: _hellopackClient.i18n.cancel,
							click: function () {
								dialog.dialog('close')
							}
						}
					}
				})
			})
		},

		tabbedNav: function () {
			const self = this
			const $wrap = $('.about-wrap')

			// Hide all panels
			$('div.panel', $wrap).hide()

			const tab = self.getParameterByName('tab')
			const hashTab = window.location.hash.substr(1)

			// Listen for the click event.
			$(document, $wrap).on('click', '.nav-tab-wrapper a', function () {
				// Deactivate and hide all tabs & panels.
				$('.nav-tab-wrapper a', $wrap).removeClass('nav-tab-active')
				$('div.panel', $wrap).hide()

				// Activate and show the selected tab and panel.
				$(this).addClass('nav-tab-active')
				$('div' + $(this).attr('href'), $wrap).show()

				self.maybeLoadhealthcheck()

				return false
			})

			if (tab) {
				$('.nav-tab-wrapper a[href="#' + tab + '"]', $wrap).click()
			} else if (hashTab) {
				$('.nav-tab-wrapper a[href="#' + hashTab + '"]', $wrap).click()
			} else {
				$('div.panel:not(.hidden)', $wrap).first().show()
			}
		},

		getParameterByName: function (name) {
			let regex, results
			name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]')
			regex = new RegExp('[\\?&]' + name + '=([^&#]*)')
			results = regex.exec(location.search)
			return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '))
		},

		maybeLoadhealthcheck: function () {
			// We only load the health check ajax call when the hellopack-client-healthcheck div is visible on the page.
			const $healthCheckOutput = $('.hellopack-client-healthcheck')
			if ($healthCheckOutput.is(':visible')) {
				$healthCheckOutput.text('Loading...')

				// Use our existing wp.ajax.post pattern from above to call the healthcheck API endpoint
				const request = wp.ajax.post(_hellopackClient.action + '_healthcheck', {
					nonce: _hellopackClient.nonce
				})

				request.done(function (response) {
					if (response && response.limits) {
						const $healthCheckUL = $('<ul></ul>')
						const limits = Object.keys(response.limits)
						for (let i = 0; i < limits.length; i++) {
							const $healthCheckLI = $('<li></li>')
							const healthCheckItem = response.limits[limits[i]]
							$healthCheckLI.addClass(healthCheckItem.ok ? 'healthcheck-ok' : 'healthcheck-error')
							$healthCheckLI.attr('data-limit', limits[i])
							$healthCheckLI.append('<span class="healthcheck-item-title">' + healthCheckItem.title + '</span>')
							$healthCheckLI.append('<span class="healthcheck-item-message">' + healthCheckItem.message + '</span>')
							$healthCheckUL.append($healthCheckLI)
						}
						$healthCheckOutput.html($healthCheckUL)
					} else {
						window.console.log(response)
						$healthCheckOutput.text('Health check failed to load. Please check console for errors.')
					}
				})

				request.fail(function (response) {
					window.console.log(response)
					$healthCheckOutput.text('Health check failed to load. Please check console for errors.')
				})
			}
		}

	}

	$(window).on('load', function () {
		hellopackClient.init()
	})
})(jQuery)
