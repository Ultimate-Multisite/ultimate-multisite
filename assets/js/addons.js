/* global Vue, wu_addons, ajaxurl, _ */
(function($) {

	const search_addon = new Vue({
		el: '#search-addons',
		data: {
			search: wu_addons.search,
		},
	});

	const wu_main_addon_app = new Vue({
		el: '#wu-addon',
		data() {

			return {
				loading: true,
				category: wu_addons.category,
				addons: [],
			};

		},
		mounted() {

			this.fetch_addons_list();

		},
		computed: {
			search() {

				return search_addon.search;

			},
			i18n() {

				return window.wu_addons.i18n;

			},
			categories() {

				let categories = [];

				_.each(this.addons, function(addon) {

					categories = categories.concat(addon.categories);

				});

				return _.unique(categories);

			},
			addons_list() {

				const app = this;

				return _.filter(app.addons, function(addon, slug) {

					if (app.category !== 'all' && ! addon.categories.some((cat) => cat.slug === app.category) ) {

						return false;

					} // end if;

					if (! app.search) {

						return true;

					} // end if;

					const search = [
						addon.slug,
						addon.name,
						addon.categories,
						addon.description,
					];

					return search.join('').toLowerCase().indexOf(app.search.toLowerCase()) > -1;

				});

			},
		},
		methods: {
			fetch_addons_list() {

				const app = this;

				$.ajax({
					method: 'GET',
					url: ajaxurl,
					data: {
						action: 'serve_addons_list',
					},
					success(data) {

						app.addons = data.data;

						app.loading = false;

					},
					error(jqXHR) {

						app.loading = false;

						// eslint-disable-next-line no-undef
						wu_ajax_error(jqXHR);

					},
				});

			},
		},
	});

	new Vue({
		el: '.wp-heading-inline',
		data: {},
		computed: {
			count() {

				return wu_main_addon_app.addons_list.length;

			},
		},
	});

	new Vue({
		el: '#addons-menu',
		data: {},
		methods: {
			set_category(category) {

				this.main_app.category = category;

				const url = new URL(window.location.href);

				url.searchParams.set('tab', category); // setting your param

				history.pushState({}, null, url);

			},
		},
		computed: {
			main_app() {

				return wu_main_addon_app;

			},
			category() {

				return wu_main_addon_app.category;

			},
			available_categories() {

				return wu_main_addon_app.categories;

			},
		},
	});

}(jQuery));
