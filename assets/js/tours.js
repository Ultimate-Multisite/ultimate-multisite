/* global _, wu_tours, ajaxurl, wu_tours_vars */
import Shepherd from 'shepherd.js';

(function($) {

	$(document).ready(function() {
		_.each(wu_tours, function(tour, tour_id) {

			window[ tour_id ] = new Shepherd.Tour({
				useModalOverlay: true,
				includeStyles: false,
				styleVariables: {
					arrowSize: 1.1,
				},
				defaultStepOptions: {
					classes: 'wu-p-2 wu-bg-white wu-shadow-sm wu-rounded wu-text-left wu-text-gray-700',
					scrollTo: {
						block: 'center',
						behavior: 'smooth',
					},
					tippyOptions: {
						zIndex: 999999,
						onCreate(instance) {

							instance.popper.classList.add('wu-styling');

							const elements = instance.popperChildren.content.children[ 0 ].children[ 0 ].children;

							if (elements[ 0 ].children[ 0 ]) {

								elements[ 0 ].children[ 0 ].classList.add('wu-p-2', 'wu-pb-0', 'wu-m-0', 'wu--mb-1', 'wu-text-gray-800');

							} // end if;

							elements[ 1 ].classList.add('wu-p-2');

							elements[ 2 ].classList.add('wu--mt-1', 'wu-p-2', 'wu-bg-gray-200', 'wu-rounded', 'wu-text-right');

						},
					},
				},
			});

		const markTourFinished = function() {

			const data = new URLSearchParams({
				action: 'wu_mark_tour_as_finished',
				tour_id,
				nonce: wu_tours_vars.nonce,
			});

			/*
			 * sendBeacon() queues the request at the OS/browser level and
			 * survives page navigation — so the setting is saved even if the
			 * user refreshes immediately after closing the tour (which would
			 * cancel an in-flight $.ajax() request). Falls back to $.ajax()
			 * for browsers without sendBeacon support.
			 */
			if (navigator.sendBeacon) {
				navigator.sendBeacon(ajaxurl, data);
			} else {
				$.ajax({ url: ajaxurl, data: Object.fromEntries(data) });
			}

		};

			window[ tour_id ].on('complete', markTourFinished);
			window[ tour_id ].on('cancel', markTourFinished);

			_.each(tour, function(step, step_index) {

				const last_step = (step_index + 1) === tour.length;

				const action_url = function(url, target = '_blank') {

					return () => {

						window.open(url, target);

					};

				};

				step.buttons = _.isArray(step.buttons) ? step.buttons : [];

				step.buttons = _.map(step.buttons, function(item) {

					item.action = action_url(item.url, item.target);

					return item;

				});

				window[ tour_id ].addStep({
					...step,
					buttons: [
						...step.buttons,
						{
							classes: 'button button-primary wu-text-xs sm:wu-normal-case',
							text: last_step ? wu_tours_vars.i18n.finish : wu_tours_vars.i18n.next,
							action: window[ tour_id ].next,
						},
					],
				});

			});

			window[ tour_id ].start();

		});

	});

}(jQuery));
