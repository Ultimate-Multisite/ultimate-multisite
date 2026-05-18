(function ($) {
	$(document).ready(
		function () {
			$('.wu-ajax-button').on(
				'click',
				function (e) {
					e.preventDefault();

					const $this = $(this);
					const default_label = $this.html();
					const action_url = $this.data('action-url');

					$this.html('...').attr('disabled', 'disabled');

					$.ajax(
						{
							url: action_url,
							dataType: 'json',
							success (response) {
								$this.html(response.message);

								setTimeout(
									function () {
										$this.html(default_label).removeAttr('disabled');
									},
									4000
								);
							},
							error (jqXHR) {
								$this.html(default_label).removeAttr('disabled');
								wu_ajax_error(jqXHR);
							},
						}
					);
				}
			);
		}
	);
}(jQuery));