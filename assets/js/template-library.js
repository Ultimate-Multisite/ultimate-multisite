/* global Vue, wu_template_library, ajaxurl, _ */
(function($) {

	const search_template = new Vue({
		el: '#search-templates',
		data: {
			search: wu_template_library.search,
		},
	});

	const wu_main_template_app = new Vue({
		el: '#wu-template-library',
		data() {

			return {
				loading: true,
				category: wu_template_library.category,
				templates: [],
			};

		},
		mounted() {

			this.fetch_templates_list();

		},
		computed: {
			search() {

				return search_template.search;

			},
			i18n() {

				return window.wu_template_library.i18n;

			},
			categories() {

				let categories = [];

				_.each(this.templates, function(template) {

					if (template.categories && Array.isArray(template.categories)) {
						categories = categories.concat(template.categories);
					}

				});

				return _.unique(categories, function(cat) {
					return cat.slug;
				});

			},
			templates_list() {

				const app = this;

				return _.filter(app.templates, function(template) {

					// Filter by category
					if (app.category !== 'all') {
						const hasCategory = template.categories && template.categories.some((cat) => cat.slug === app.category);
						if (! hasCategory) {
							return false;
						}
					}

					// Filter by search
					if (! app.search) {

						return true;

					}

					const search_fields = [
						template.slug || '',
						template.name || '',
						template.description || '',
						template.short_description || '',
						template.author || '',
						template.industry_type || '',
					];

					// Add category names to search
					if (template.categories && Array.isArray(template.categories)) {
						template.categories.forEach(function(cat) {
							search_fields.push(cat.name || cat.slug || '');
						});
					}

					return search_fields.join(' ').toLowerCase().indexOf(app.search.toLowerCase()) > -1;

				});

			},
			count() {

				return this.templates_list.length;

			},
		},
		methods: {
			fetch_templates_list() {

				const app = this;

				$.ajax({
					method: 'GET',
					url: ajaxurl,
					data: {
						action: 'serve_templates_list',
					},
					success(data) {

						if (data.success && data.data) {
							app.templates = data.data;
						} else {
							app.templates = [];
						}

						app.loading = false;

					},
					error(jqXHR) {

						app.templates = [];
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

				return wu_main_template_app.count;

			},
		},
	});

	new Vue({
		el: '#templates-menu',
		data: {},
		methods: {
			set_category(category) {

				this.main_app.category = category;

				const url = new URL(window.location.href);

				url.searchParams.set('tab', category);

				history.pushState({}, null, url);

			},
		},
		computed: {
			main_app() {

				return wu_main_template_app;

			},
			category() {

				return wu_main_template_app.category;

			},
			available_categories() {

				return wu_main_template_app.categories;

			},
		},
	});

}(jQuery));
