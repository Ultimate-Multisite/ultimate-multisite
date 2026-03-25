(function($) {

var coupon_app = new Vue({
	el: "#coupon-code-app",
	data: {
		coupon_id: '',
		coupon: wu_coupon_data.coupon,
		type: wu_coupon_data.type,
		value: parseFloat(wu_coupon_data.value),
		applies_to_setup_fee: wu_coupon_data.applies_to_setup_fee,
		setup_fee_discount_value: parseFloat(wu_coupon_data.setup_fee_discount_value),
		setup_fee_discount_type: wu_coupon_data.setup_fee_discount_type,
		allowed_plans: wu_coupon_data.allowed_plans,
		allowed_freqs: wu_coupon_data.allowed_freqs,
		success: false,
	},
	mounted: function() {

		this.apply_coupon();
		this.add_event_tabs();

	},
	methods: {

	remove_coupon: function() {

		// Clear current coupon and re-apply pricing without discounts
		this.coupon = false;
		this.apply_coupon();

	},

	add_event_tabs: function() {

		$('.wu-plans-frequency-selector li a').each(function() {

		this.addEventListener('click',function() {
			coupon_app.apply_coupon();
		});

		});


	},

	apply_coupon: function() {

		if(this.coupon) {

			$("body").block({
			message: null,
			overlayCSS: {
				background: "#F1F1F1",
				opacity: 0.6
			}
			});

			this.coupon_id = this.coupon.id;

			var coupon_type = this.type;

			var coupon_value = this.value;

			var applies_to_setup_fee = this.applies_to_setup_fee;
			var setup_fee_discount_value = this.setup_fee_discount_value;
			var setup_fee_discount_type = this.setup_fee_discount_type;
			var allowed_plans = $.parseJSON(this.allowed_plans);
			var allowed_freqs = $.parseJSON(this.allowed_freqs);

			//$('#signupform').append($('#coupon_id'));

			$('.superscript').show();
			$('h5 sub').show();

			setTimeout( function() { $('.wu-plan').each(function() {

			if (!$(this).find('.old-price').get(0)) {

				$(this).find('h4').after('<div class="old-price">--</div>');

			}

				let plan_id = $(this).data('plan');

				let is_allowed_plan = false;

				let is_allowed_freq = false;

				// check plan is_allowed
				if (typeof allowed_plans === 'object'){

				for (var each_plan of allowed_plans) {

					if (parseInt(each_plan) == plan_id) {
					is_allowed_plan = true;
					}

				}

				} else {
				is_allowed_plan = true;
				}

				// check freq is_allowed

				if (typeof allowed_freqs === 'object'){

				for (var each_freq of allowed_freqs) {

					if (each_freq == $('#wu_plan_freq').val()) {
					is_allowed_freq = true;
					}

				}

				} else {
				is_allowed_freq = true;
				}

				if (!is_allowed_plan) {

				$("body").unblock();
				return;

				}

				if (!is_allowed_freq) {

				$("body").unblock();
				$(".old-price").hide();
				$(".off-value").hide();
				return;

				} else {

				$(".old-price").show();
				$(".off-value").show();

				}

				let old_price = $(this).data('price-' + $('#wu_plan_freq').val());

				old_price = wu_fix_money_string(old_price);

				let new_price = 0;

				let old_yearly_value = old_price * jQuery('#wu_plan_freq').val();

				let new_yearly_value = 0;

				let old_setupfee = $(this).find('.pricing-table-setupfee') ? $(this).find('.pricing-table-setupfee').attr('data-value') : 0;

				let new_setupfee = 0;

				let off_with_symbol = '';

				// OFF RENDER
				if (coupon_type != '"absolute"')
				off_with_symbol = ''.concat(coupon_value, '%');
				else
				off_with_symbol = accounting.formatMoney(parseFloat(coupon_value));

				$(this).find('.old-price').html(accounting.formatMoney(parseFloat(old_price)));
				if (!$(this).find('.off-value').get(0)) {

				$(this).find('.old-price').after('<div class="off-value">(' + off_with_symbol + ' ' + wu_coupon_data.off_text + ')</div>');

				}

				if(applies_to_setup_fee) {

				if (setup_fee_discount_type != '"absolute"')
					setupfee_off_with_symbol = ''.concat(setup_fee_discount_value, '%');
				else
					setupfee_off_with_symbol = accounting.formatMoney(parseFloat(setup_fee_discount_value));

				if (!$(this).find('.setupfee-off-value').get(0)) {

					$(this).find('.pricing-table-setupfee').after('<span class="setupfee-off-value"> (' + setupfee_off_with_symbol + ' ' + wu_coupon_data.off_text + ')</span>');

				}

				}

				// END OFF RENDER

				if(coupon_type != '"absolute"') {

				new_price = old_price * ((100 - coupon_value) / 100);

				new_yearly_value = old_yearly_value * ((100 - coupon_value) / 100);

				} else {

				if(jQuery('#wu_plan_freq').val() > 1){

					new_price = ((old_price * jQuery('#wu_plan_freq').val()) - parseFloat(coupon_value)) / jQuery('#wu_plan_freq').val();

					new_yearly_value = old_yearly_value - parseFloat(coupon_value);

				} else {

					new_price = old_price - parseFloat(coupon_value);

					new_yearly_value = old_yearly_value - parseFloat(coupon_value);

				}

				}

				if (applies_to_setup_fee)  {

				if (setup_fee_discount_type != '"absolute"') {

				new_setupfee = old_setupfee * ((100 - setup_fee_discount_value) / 100);

				} else {

				new_setupfee = old_setupfee - parseFloat(setup_fee_discount_value);

				}

				} else {

				new_setupfee = old_setupfee;

				}

				if (new_yearly_value > 0) {

				wu_set_yearly_value(this, new_yearly_value);

				} else {

				$(this).find('.total-price.total-price-' + $('#wu_plan_freq').val() ).html(' ' + wu_coupon_data.free_text);

				}

				if (new_setupfee > 0) {

				wu_set_setupfee_value(this, new_setupfee);

				} else {

				$(this).find('.pricing-table-setupfee').html(' ' + wu_coupon_data.no_setup_fee_text);

				}

				if (new_price > 0) {

				$(this).find('.plan-price').html( accounting.formatMoney( parseFloat(new_price) ) );

				if ( $(this).find('.plan-price').html().indexOf(wpu.currency_symbol) !== -1 ) {

					$(this).find('.plan-price').html($(this).find('.plan-price').html().replace(wpu.currency_symbol, ''));

				}

				} else {

				let plan_price = $(this).find('.plan-price');
				plan_price.html(' ' + wu_coupon_data.free_text);
				let hagacinco = $(this).find('h5');
				hagacinco.find('sub').hide();
				hagacinco.find('.superscript').hide();

				}

				$("body").unblock();

			}); }, 400);


		} else {

			$('.old-price').hide();
			$('.off-value').hide();
			$('.setupfee-off-value').hide();

			this.coupon_id = '';

			$('.wu-plan').each(function() {

			var price = $(this).data('price-' + $('#wu_plan_freq').val());

			$(this).find('.plan-price').html( price );

			});

		}

	}
	}
});

function wu_fix_money_string(value) {

	if(typeof value == 'number'){
		value = value.toString();
	}

	return parseFloat(value.replace(wpu.thousand_separator, '').replace(wpu.decimal_separator, '.'));

}

function wu_set_setupfee_value(list, value) {

	jQuery(list).find('.pricing-table-setupfee').html( accounting.formatMoney(value));

}

function wu_set_yearly_value(list, value) {

	var current_freq = jQuery('#wu_plan_freq').val();

	var string = jQuery(list).find('.total-price.total-price-' + current_freq).html();

	if (string) {

		var parts = string.split(',');

		var result =  accounting.formatMoney(parseFloat(value)) + ', ' + parts[1];

		jQuery(list).find('.total-price.total-price-' + current_freq).html(result);

	}

}

})(jQuery);