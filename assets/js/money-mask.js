/**
 * Opt-in blank values for the bundled v-money component.
 *
 * Nonblank amounts and fields without allow_blank use the upstream formatter.
 * Keep this adapter separate from the library so library rebuilds retain it.
 */
(function () {
	const Vue = window.Vue;
	if (! Vue) {
		return;
	}

	const money = Vue.component('money');
	if (! money) {
		return;
	}

	const options = { ...money.options };
	// Do not reuse Vue's cached constructor when registering adapted options.
	delete options._Ctor;
	// Vue normalizes directive functions into bind/update hook objects.
	const directive = options.directives.money;
	const original_mask = typeof directive === 'function' ? directive : directive.bind;
	const original_value_handler = options.watch.value.handler;
	const original_change = options.methods.change;

	/**
	 * Preserve only absent values; numeric zero is a real amount.
	 *
	 * @param {*} value Model value.
	 * @return {boolean} Whether the model is blank.
	 */
	function is_blank(value) {
		return value === '' || value === null || value === undefined;
	}

	/**
	 * Keep a cleared input blank while delegating numeric input to v-money.
	 *
	 * @param {HTMLInputElement} element Input receiving the mask.
	 * @param {Object}           binding Vue directive binding.
	 * @param {Object}           vnode   Vue virtual node.
	 */
	function mask(element, binding, vnode) {
		if (! vnode.context.allow_blank) {
			original_mask(element, binding, vnode);
			return;
		}

		// Prefixes, suffixes and separators alone must not turn deletion into zero.
		if (! /\d/.test(element.value)) {
			element.value = '';
			element.oninput = function () {
				if (! /\d/.test(element.value)) {
					element.value = '';
					element.dispatchEvent(new Event('change', { bubbles: true }));
					return;
				}
				mask(element, binding, vnode);
			};
			return;
		}

		original_mask(element, binding, vnode);
		const original_input = element.oninput;
		element.oninput = function () {
			if (! /\d/.test(element.value)) {
				element.value = '';
				element.dispatchEvent(new Event('change', { bubbles: true }));
				return;
			}
			original_input();
		};
	}

	Vue.component('money', {
		...options,
		props: {
			...options.props,
			allow_blank: { type: Boolean, default: false },
			value: {
				...options.props.value,
				default() {
					// value may initialize before Vue has cast the allow_blank prop.
					const props = this.$options.propsData || {};
					return [ true, '', 'allow_blank' ].includes(props.allow_blank) ? '' : options.props.value.default;
				}
			}
		},
		directives: { ...options.directives, money: mask },
		watch: {
			...options.watch,
			value: {
				...options.watch.value,
				handler(value) {
					if (this.allow_blank && is_blank(value)) {
						this.formattedValue = '';
						return;
					}
					// PHP-rendered values arrive as strings, but represent whole amounts.
					if (this.allow_blank && typeof value === 'string' && /^-?\d+(?:\.\d+)?$/.test(value)) {
						value = Number(value);
					}
					original_value_handler.call(this, value);
				}
			}
		},
		methods: {
			...options.methods,
			change(event) {
				if (this.allow_blank && event.target.value === '') {
					this.$emit('input', '');
					return;
				}
				original_change.call(this, event);
			}
		}
	});
}());
