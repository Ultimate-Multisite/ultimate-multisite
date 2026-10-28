<?php
/**
 * Test case for the invoice language-to-font mapping.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Invoices;

use WP_Ultimo\Invoices\Language_To_Font;
use WP_UnitTestCase;

/**
 * Tests which font each language of an invoice uses.
 */
class Language_To_Font_Test extends WP_UnitTestCase {

	/**
	 * Language tags and the packaged script they must map to.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function language_tags(): array {

		return [
			'Hindi'                       => ['hi', 'devanagari'],
			'Hindi with region'           => ['hi-IN', 'devanagari'],
			'Hindi with underscore'       => ['hi_IN', 'devanagari'],
			'upper case'                  => ['HI', 'devanagari'],
			'Marathi (shares Devanagari)' => ['mr', 'devanagari'],
			'ISO 639-2 code'              => ['ben', 'bengali'],
			'Tamil'                       => ['ta', 'tamil'],
			'Thai'                        => ['th', 'thai'],
			'Hebrew'                      => ['he', 'hebrew'],
			'Amharic'                     => ['am', 'ethiopic'],
			'undetermined Ethiopic'       => ['und-Ethi', 'ethiopic'],
			'English'                     => ['en', ''],
			'Russian'                     => ['ru', ''],
			'Arabic'                      => ['ar', ''],
			'undetermined Latin'          => ['und-Latn', ''],
			'empty tag'                   => ['', ''],
		];
	}

	/**
	 * Test language tags resolve to the right packaged script.
	 *
	 * @dataProvider language_tags
	 *
	 * @param string $tag    Language tag.
	 * @param string $script Expected script slug, or '' for none.
	 */
	public function test_get_script_by_language(string $tag, string $script) {

		$this->assertSame($script, Language_To_Font::get_script_by_language($tag));
	}

	/**
	 * Test the eight scripts mPDF sends to one shared font each get their own font.
	 */
	public function test_indic_scripts_do_not_share_a_font() {

		$mapper = new Language_To_Font();

		$fonts = [];

		foreach (['hi', 'bn', 'gu', 'pa', 'ta', 'ml', 'or', 'dv'] as $language) {
			[$core, $font] = $mapper->getLanguageOptions($language, true);

			$this->assertFalse($core);

			$fonts[] = $font;
		}

		// mPDF alone maps all eight to "freeserif".
		$this->assertCount(8, array_unique($fonts));
		$this->assertNotContains('freeserif', $fonts);
	}

	/**
	 * Test languages without a packaged font still use mPDF's own choice.
	 */
	public function test_other_languages_defer_to_mpdf() {

		$mapper = new Language_To_Font();

		[, $russian] = $mapper->getLanguageOptions('ru', true);
		[, $arabic]  = $mapper->getLanguageOptions('ar', true);
		[, $sindhi]  = $mapper->getLanguageOptions('sd', true);

		$this->assertSame('dejavusanscondensed', $russian);
		$this->assertSame('xbriyaz', $arabic);
		$this->assertSame('lateef', $sindhi);
	}

	/**
	 * Test font names carry the version, so a font update never reuses a stale mPDF cache entry.
	 */
	public function test_font_names_include_the_version() {

		$this->assertSame('notodevanagari_v' . Language_To_Font::FONT_VERSION, Language_To_Font::get_font_name('devanagari'));
		$this->assertArrayHasKey(Language_To_Font::get_font_name('thai'), Language_To_Font::get_fontdata());
	}
}
