/* global Vue, ajaxurl, wu_moment, wu_activity_stream_nonce, wu_ajax_error */
document.addEventListener('DOMContentLoaded', function() {
	Object.defineProperty(Vue.prototype, '$moment', {
		value: wu_moment
	});

	var wuActivityStream = new Vue({
		el: '#activity-stream-content',
		data: {
			count: 0,
			loading: true,
			page: 1,
			queried: [],
			error: false,
			errorMessage: "",
		},
		mounted() {
			this.pullQuery();
		},
		watch: {
			queried(value) {},
		},
		methods: {
			hasMore() {
				return this.queried.count > (this.page * 5)
			},
			refresh() {
				this.loading = true;
				this.pullQuery();
			},
			navigatePrev() {
				this.page = this.page <= 1 ? 1 : this.page - 1;
				this.loading = true;
				this.pullQuery();
			},
			navigateNext() {
				this.page = this.page + 1;
				this.loading = true;
				this.pullQuery();
			},
			pullQuery() {
				const that = this;
				jQuery.ajax({
					url: ajaxurl,
					data: {
						_ajax_nonce: wu_activity_stream_nonce,
						action: 'wu_fetch_activity',
						page: this.page,
					},
					success(data) {
						that.loading = false;
						Vue.set(wuActivityStream, 'loading', false);

						if (data.success) {
							Vue.set(wuActivityStream, 'queried', data.data);
						}
					},
					error(jqXHR) {
						that.loading = false;
						Vue.set(wuActivityStream, 'loading', false);
						wu_ajax_error(jqXHR);
					},
				})
			},
			get_color_event(type) {},
		}
	});
});
