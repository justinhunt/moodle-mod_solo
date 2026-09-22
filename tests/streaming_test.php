<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_solo;

/**
 * Which activities record with the in page streaming recorder.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(utils::class)]
final class streaming_test extends \advanced_testcase {
    /**
     * An activity that can stream: streaming on, audio, the default skin and sequence, English.
     *
     * @param array $overrides Fields to change.
     * @return \stdClass
     */
    protected function activity(array $overrides = []): \stdClass {
        $activity = (object) [
            'streamingrecord' => 1,
            'recordertype' => constants::REC_AUDIO,
            'recorderskin' => constants::SKIN_SOLO,
            'enableai' => 1,
            'region' => 'useast1',
            'ttslanguage' => constants::M_LANG_ENUS,
            'activitysteps' => constants::M_SEQ_PRM,
        ];
        foreach ($overrides as $name => $value) {
            $activity->{$name} = $value;
        }
        return utils::sequence_to_steps($activity);
    }

    public function test_can_stream_record(): void {
        $this->resetAfterTest();
        set_config('azureapikey', '', 'mod_solo');

        $this->assertTrue(utils::can_stream_record($this->activity()));
        $this->assertTrue(utils::can_stream_record($this->activity(['activitysteps' => constants::M_SEQ_RM])));
        $this->assertTrue(utils::can_stream_record($this->activity(['activitysteps' => constants::M_SEQ_PTRM])));
        $this->assertTrue(utils::can_stream_record($this->activity(['ttslanguage' => constants::M_LANG_FRFR])));

        $this->assertFalse(utils::can_stream_record($this->activity(['streamingrecord' => 0])));
        $this->assertFalse(utils::can_stream_record($this->activity(['recordertype' => constants::REC_VIDEO])));
        $this->assertFalse(utils::can_stream_record($this->activity(['recorderskin' => constants::SKIN_UPLOAD])));
        $this->assertFalse(utils::can_stream_record($this->activity(['enableai' => 0])));
        // No record step, and the legacy sequences that preload an automatic transcript.
        $this->assertFalse(utils::can_stream_record($this->activity(['activitysteps' => constants::M_SEQ_PTM])));
        $this->assertFalse(utils::can_stream_record($this->activity(['activitysteps' => constants::M_SEQ_PRTM])));
        $this->assertFalse(utils::can_stream_record($this->activity(['activitysteps' => constants::M_SEQ_PRMT])));
        // A step layout that is not one of the sequences must not pass as PRM.
        $odd = $this->activity();
        $odd->step2 = constants::M_STEP_MODEL;
        $odd->step3 = constants::M_STEP_RECORD;
        $this->assertFalse(utils::can_stream_record($odd));
        // AssemblyAI does not stream Japanese.
        $this->assertFalse(utils::can_stream_record($this->activity(['ttslanguage' => constants::M_LANG_JAJP])));
    }

    public function test_can_stream_record_with_azure(): void {
        $this->resetAfterTest();
        set_config('azureapikey', 'somekey', 'mod_solo');
        set_config('azureapiregion', 'eastus', 'mod_solo');

        $this->assertSame('azure', utils::streaming_token_type());
        $this->assertTrue(utils::can_stream_record($this->activity(['ttslanguage' => constants::M_LANG_JAJP])));
        $this->assertFalse(utils::can_stream_record($this->activity(['ttslanguage' => constants::M_LANG_NONO])));
    }

    public function test_streaming_supports_language(): void {
        foreach (['en-US', 'en-WL', 'es-ES', 'fr-CA', 'de-AT', 'it-IT', 'pt-BR'] as $language) {
            $this->assertTrue(utils::streaming_supports_language('assemblyai', $language), $language);
        }
        foreach (['ja-JP', 'zh-CN', 'ar-AE', 'ru-RU'] as $language) {
            $this->assertFalse(utils::streaming_supports_language('assemblyai', $language), $language);
        }
        foreach (['ja-JP', 'zh-CN', 'ar-AE', 'fr-CA', 'he-IL', 'hu-HU', 'ro-RO'] as $language) {
            $this->assertTrue(utils::streaming_supports_language('azure', $language), $language);
        }
        foreach (['en-WL', 'en-AB', 'mi-NZ', 'no-NO', 'xx-XX'] as $language) {
            $this->assertFalse(utils::streaming_supports_language('azure', $language), $language);
        }
        $this->assertFalse(utils::streaming_supports_language('msspeech', 'en-US'));
    }
}
