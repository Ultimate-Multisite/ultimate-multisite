<?php
/**
 * No php code in this file.
 */
defined('ABSPATH') || exit;
?>
<div id="general" data-type="heading"
	class="wu-bg-gray-100 wu--mt-1 wu--mx-3 wu-p-4 wu-border-solid wu-border-b wu-border-l-0 wu-border-r-0 wu-border-t-0 wu-border-gray-300">
	<h3 class="wu-m-0 wu-p-0 wu-uppercase wu-text-gray-600 wu-text-sm wu-tracking-wide">General Options</h3>
	<p class="wu-m-0 wu-p-0 wu-text-gray-600">Here we define some of the fundamental settings of the plugin.</p>
</div>

<table class="form-table">
	<tbody>

	<tr>
		<th scope="row"><label for="trial">Trial Period</label></th>
		<td>
			<input min="0" name="trial" type="number" id="trial" class="regular-text" value="0" placeholder="">


			<p class="description" id="trial-desc">
				Number of days for the trial period. Leave 0 to disable trial.
			</p>

		</td>
	</tr>


	</tbody>
</table>

<div id="general" data-type="heading"
	class="wu-bg-gray-100 wu--mt-1 wu--mx-3 wu-p-4 wu-border-solid wu-border-b wu-border-l-0 wu-border-r-0 wu-border-t wu-border-gray-300">
	<h3 class="wu-m-0 wu-p-0 wu-uppercase wu-text-gray-600 wu-text-sm wu-tracking-wide">Currency Options</h3>
	<p class="wu-m-0 wu-p-0 wu-text-gray-600">The following options affect how prices are displayed on the frontend, the
		backend and in reports.</p>
</div>

<table class="form-table">
	<tbody>


	<tr>
		<th scope="row"><label for="currency_symbol">Currency Symbol</label></th>
		<td>

			<select name="currency_symbol" id="currency_symbol">

				<option value="AED">United Arab Emirates Dirham (د.إ)</option>
				<option value="ARS">Argentine Peso ($)</option>
				<option value="AUD">Australian Dollars ($)</option>
				<option value="BDT">Bangladeshi Taka (৳&nbsp;)</option>
				<option value="BRL">Brazilian Real (R$)</option>
				<option value="BGN">Bulgarian Lev (лв.)</option>
				<option value="CAD">Canadian Dollars ($)</option>
				<option value="CLP">Chilean Peso ($)</option>
				<option value="CNY">Chinese Yuan (¥)</option>
				<option value="COP">Colombian Peso ($)</option>
				<option value="CZK">Czech Koruna (Kč)</option>
				<option value="DKK">Danish Krone (DKK)</option>
				<option value="DOP">Dominican Peso (RD$)</option>
				<option value="EUR">Euros (€)</option>
				<option value="HKD">Hong Kong Dollar ($)</option>
				<option value="HRK">Croatia kuna (Kn)</option>
				<option value="HUF">Hungarian Forint (Ft)</option>
				<option value="ISK">Icelandic krona (Kr.)</option>
				<option value="IDR">Indonesia Rupiah (Rp)</option>
				<option value="INR">Indian Rupee (Rs.)</option>
				<option value="NPR">Nepali Rupee (Rs.)</option>
				<option value="ILS">Israeli Shekel (₪)</option>
				<option value="JPY">Japanese Yen (¥)</option>
				<option value="KES">Kenyan Shilling (KSh)</option>
				<option value="KIP">Lao Kip (₭)</option>
				<option value="KRW">South Korean Won (₩)</option>
				<option value="MYR">Malaysian Ringgits (RM)</option>
				<option value="MXN">Mexican Peso ($)</option>
				<option value="NGN">Nigerian Naira (₦)</option>
				<option value="NOK">Norwegian Krone (kr)</option>
				<option value="NZD">New Zealand Dollar ($)</option>
				<option value="PYG">Paraguayan Guaraní (₲)</option>
				<option value="PHP">Philippine Pesos (₱)</option>
				<option value="PLN">Polish Zloty (zł)</option>
				<option value="GBP">Pounds Sterling (£)</option>
				<option value="RON">Romanian Leu (lei)</option>
				<option value="RUB">Russian Ruble (руб.)</option>
				<option value="SGD">Singapore Dollar ($)</option>
				<option value="ZAR">South African rand (R)</option>
				<option value="SEK">Swedish Krona (kr)</option>
				<option value="CHF">Swiss Franc (CHF)</option>
				<option value="TWD">Taiwan New Dollars (NT$)</option>
				<option value="THB">Thai Baht (฿)</option>
				<option value="TRY">Turkish Lira (₺)</option>
				<option value="UAH">Ukrainian Hryvnia (₴)</option>
				<option selected="selected" value="USD">US Dollars ($)</option>
				<option value="VND">Vietnamese Dong (₫)</option>
				<option value="EGP">Egyptian Pound (EGP)</option>

			</select>

			<p class="description" id="currency_symbol-desc">
				Select the currency symbol to be used in Ultimate Multisite
			</p>

		</td>
	</tr>


	<tr>
		<th scope="row"><label for="currency_position">Currency Position</label></th>
		<td>

			<select name="currency_position" id="currency_position">

				<option value="%s%v">Left ($99.99)</option>
				<option value="%v%s">Right (99.99$)</option>
				<option selected="selected" value="%s %v">Left with space ($ 99.99)</option>
				<option value="%v %s">Right with space (99.99 $)</option>

			</select>


		</td>
	</tr>


	<tr>
		<th scope="row"><label for="decimal_separator">Decimal Separator</label></th>
		<td>
			<input name="decimal_separator" type="text" id="decimal_separator" class="regular-text" value="."
					placeholder="">


		</td>
	</tr>


	<tr>
		<th scope="row"><label for="thousand_separator">Thousand Separator</label></th>
		<td>
			<input name="thousand_separator" type="text" id="thousand_separator" class="regular-text" value=","
					placeholder="">


		</td>
	</tr>


	<tr>
		<th scope="row"><label for="precision">Number of Decimals</label></th>
		<td>
			<input min="0" name="precision" type="number" id="precision" class="regular-text" value="2"
					placeholder="">


		</td>
	</tr>


	</tbody>
</table>

<div id="dashboard_elements" data-type="heading">
	<h3>Subscriber Dashboard Options</h3>
	<p>Control the elements added to the Subscriber's Dashboard.</p>
</div>

<table class="form-table">
	<tbody>


	<tr id="multiselect-limits_and_quotas">
		<th scope="row"><label for="limits_and_quotas">Limits and Quotas</label></th>
		<td>


			<div class="row ">


				<div class="wu-col-sm-4" style="margin-bottom: 2px;">

					<label for="multiselect-post">
						<input checked="checked" name="limits_and_quotas[post]" type="checkbox" id="multiselect-post"
								value="1">
						Posts </label>

				</div>


				<div class="wu-col-sm-4" style="margin-bottom: 2px;">

					<label for="multiselect-page">
						<input checked="checked" name="limits_and_quotas[page]" type="checkbox" id="multiselect-page"
								value="1">
						Pages </label>

				</div>


				<div class="wu-col-sm-4" style="margin-bottom: 2px;">

					<label for="multiselect-attachment">
						<input checked="checked" name="limits_and_quotas[attachment]" type="checkbox"
								id="multiselect-attachment" value="1">
						Media </label>

				</div>


				<div class="wu-col-sm-4" style="margin-bottom: 2px;">

					<label for="multiselect-product">
						<input checked="checked" name="limits_and_quotas[product]" type="checkbox"
								id="multiselect-product"
								value="1">
						Products </label>

				</div>


				<div class="wu-col-sm-4" style="margin-bottom: 2px;">

					<label for="multiselect-sites">
						<input checked="checked" name="limits_and_quotas[sites]" type="checkbox" id="multiselect-sites"
								value="1">
						Sites </label>

				</div>


				<div class="wu-col-sm-4" style="margin-bottom: 2px;">

					<label for="multiselect-visits">
						<input checked="checked" name="limits_and_quotas[visits]" type="checkbox"
								id="multiselect-visits"
								value="1">
						Visits </label>

				</div>


			</div>


			<div style="clear: both"></div>
			<br>

			<button type="button" data-select-all="multiselect-limits_and_quotas" class="button wu-select-all">Check /
				Uncheck All
			</button>

			<br>

			<p class="description" id="limits_and_quotas-desc">
				Select which elements you would like to display on the Limits and Quotas Widget.
			</p>


		</td>
	</tr>


	</tbody>
</table>

<div id="uninstall" data-type="heading">
	<h3>Uninstall Options</h3>
	<p>Change the plugin behavior on uninstall.</p>
</div>

<table class="form-table">
	<tbody>


	<tr>
		<th scope="row"><label for="uninstall_wipe_tables">Remove Data on Uninstall</label></th>
		<td>

			<label for="uninstall_wipe_tables">
				<input name="uninstall_wipe_tables" type="checkbox" id="uninstall_wipe_tables" value="1">
				Remove Data on Uninstall </label>

			<p class="description" id="uninstall_wipe_tables-desc">
				Remove all saved data for Ultimate Multisite when the plugin is uninstalled.
			</p>

		</td>
	</tr>


	</tbody>
</table>

<p class="submit">
	<button type="submit" name="_submit" id="_submit" class="button button-primary">Save Changes</button>
	<input type="hidden" id="_wpnonce" name="_wpnonce" value="59c475b6c4"><input type="hidden"
																				name="_wp_http_referer"
																				value="/wp-admin/network/admin.php?page=wp-ultimo">
	<input type="hidden"
			name="wu_action" value="save_settings">
</p>
