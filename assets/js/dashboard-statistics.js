/* global wu_dashboard_statistics_vars, wu_format_money, Vue, wu_block_ui, moment, ajaxurl */
(function() {

	const graph = document.getElementById('wp-ultimo-mrr-growth');

	if (! graph) {

		return;

	} // end if;

	// eslint-disable-next-line no-undef
	mrr_graph = new Vue({
		el: '#wp-ultimo-mrr-growth',
		components: {
			apexchart: window.VueApexCharts,
		},
		data: {
			start_date: wu_dashboard_statistics_vars.start_date,
			end_date: wu_dashboard_statistics_vars.end_date,
			chart_options: {
				mrr_growth: {
					series: [
						{
							name: wu_dashboard_statistics_vars.i18n.new_mrr,
							data: [
								wu_dashboard_statistics_vars.mrr_array.january.total,
								wu_dashboard_statistics_vars.mrr_array.february.total,
								wu_dashboard_statistics_vars.mrr_array.march.total,
								wu_dashboard_statistics_vars.mrr_array.april.total,
								wu_dashboard_statistics_vars.mrr_array.may.total,
								wu_dashboard_statistics_vars.mrr_array.june.total,
								wu_dashboard_statistics_vars.mrr_array.july.total,
								wu_dashboard_statistics_vars.mrr_array.august.total,
								wu_dashboard_statistics_vars.mrr_array.september.total,
								wu_dashboard_statistics_vars.mrr_array.october.total,
								wu_dashboard_statistics_vars.mrr_array.november.total,
								wu_dashboard_statistics_vars.mrr_array.december.total,
							],
						},
						{
							name: wu_dashboard_statistics_vars.i18n.cancellations,
							data: [
								-wu_dashboard_statistics_vars.mrr_array.january.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.february.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.march.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.april.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.may.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.june.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.july.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.august.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.september.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.october.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.november.cancelled,
								-wu_dashboard_statistics_vars.mrr_array.december.cancelled,
							],
						},
					],
					chartOptions: {
						chart: {
							type: 'bar',
							height: 300,
							stacked: true,
							toolbar: {
								show: false,
							},
							zoom: {
								enabled: true,
							},
						},
						dataLabels: {
							enabled: false,
							maxItems: 0,
						},
						responsive: [ {
							breakpoint: 480,
							options: {
								legend: {
									position: 'bottom',
									offsetX: -10,
									offsetY: 0,
								},
							},
						} ],
						colors: [ '#3498db', '#e74c3c' ],
						plotOptions: {
							bar: {
								horizontal: false,
								columnWidth: '40%',
								endingShape: 'rounded',
								startingShape: 'rounded',
							},
						},
						xaxis: {
							categories: wu_dashboard_statistics_vars.month_list,
							position: 'bottom',
							axisBorder: {
								show: true,
							},
							axisTicks: {
								show: true,
							},
							crosshairs: {
								fill: {
									type: 'gradient',
									gradient: {
										colorFrom: '#D8E3F0',
										colorTo: '#BED1E6',
										stops: [ 0, 100 ],
										opacityFrom: 0.4,
										opacityTo: 0.5,
									},
								},
							},
							tooltip: {
								enabled: true,
							},
						},
						yaxis: {
							labels: {
								formatter(y) {

									return y >= 0 ? wu_format_money(y) : '-' + wu_format_money(y);

								},
							},
						},
						legend: {
							position: 'top',
							offsetY: 0,
						},
						fill: {
							opacity: 1,
						},
					},
				},
			},
		},
	});

}());

(function($) {

	$(document).ready(function() {

		$('.wu-loader').on('click', function() {

			wu_block_ui('#wpcontent');

		});

		$('#wu-date-range').flatpickr({
			mode: 'range',
			dateFormat: 'Y-m-d',
			maxDate: wu_dashboard_statistics_vars.today,
			defaultDate: [
				wu_dashboard_statistics_vars.start_date,
				wu_dashboard_statistics_vars.end_date,
			],
			onClose(selectedDates) {

				const redirect = new URL(window.location.href);

				redirect.searchParams.set('start_date', moment(selectedDates[ 0 ]).format('YYYY-MM-DD'));

				redirect.searchParams.set('end_date', moment(selectedDates[ 1 ]).format('YYYY-MM-DD'));

				window.location.href = redirect.toString();

				wu_block_ui('#wpcontent');

			},
		});

	});

}(jQuery));

(function($) {

	$(document).ready(function() {

		$('.wu-export-button').on('click', function(e) {

			e.preventDefault();

			const slug = e.target.getAttribute('attr-slug-csv');

			const headers = $('#csv_headers_' + slug).val();

			const data = $('#csv_data_' + slug).val();

			const action = $('#csv_action_' + slug).val();

			const date_range = wu_dashboard_statistics_vars.start_date + '_to_' + wu_dashboard_statistics_vars.end_date;

			const block = wu_block_ui('#wpcontent');

			setTimeout(() => {

				block.unblock();

			}, 2000);

			// eslint-disable-next-line max-len
			$('body').append('<form id="export_csv" method="post" action="' + ajaxurl + '" style="display:none;"><input name="action" value="' + action + '" type="hidden"><input name="slug" value="' + slug + '" type="hidden"></form>');

			$('<input />').attr('type', 'hidden')
				.attr('name', 'headers')
				.attr('value', headers)
				.appendTo('#export_csv');

			$('<input />').attr('type', 'hidden')
				.attr('name', 'data')
				.attr('value', data)
				.appendTo('#export_csv');

			$('<input />').attr('type', 'hidden')
				.attr('name', 'date_range')
				.attr('value', date_range)
				.appendTo('#export_csv');

			$('#export_csv').submit();

			$('#export_csv').remove();

		});

	});

}(jQuery));
