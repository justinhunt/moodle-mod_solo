<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_solo;

use core_ai\aiactions\generate_text;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for sending student work to a Moodle AI provider instead of Cloud Poodll.
 *
 * Each core provider is given a mocked HTTP reply, so these tests check what Solo sends and how it reads
 * the reply, without calling any AI service.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(aimanager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_solo\local\aiprompts::class)]
final class aimanager_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\core_ai\manager::class) || !class_exists(\core_ai\provider::class)) {
            $this->markTestSkipped('Core AI subsystem not available.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Grading instructions as process_attempt() builds them.
     *
     * @return \stdClass
     */
    private function instructions(): \stdClass {
        return (object) [
            'feedbackscheme' => 'Give two short tips.',
            'feedbacklanguage' => 'ja-JP',
            'markscheme' => 'Give one mark for each correct sentence.',
            'maxmarks' => 100,
            'questiontext' => 'What did you do yesterday?',
            'modeltext' => '',
        ];
    }

    /**
     * Create an enabled provider instance and send student work to it.
     *
     * @param string $plugin openai, gemini or ollama
     * @return \core_ai\provider
     */
    private function use_provider(string $plugin): \core_ai\provider {
        $settings = [
            'openai' => ['model' => 'gpt-4o', 'endpoint' => 'https://api.openai.com/v1/chat/completions'],
            'gemini' => ['model' => 'gemini-2.0-flash',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent'],
            'ollama' => ['model' => 'llama3.2'],
        ][$plugin];
        $settings['systeminstruction'] = 'Admin system instruction';
        $config = $plugin === 'ollama' ? ['endpoint' => 'http://localhost:11434'] : ['apikey' => '123'];

        $manager = \core\di::get(\core_ai\manager::class);
        $provider = $manager->create_provider_instance(
            classname: "\\aiprovider_{$plugin}\\provider",
            name: $plugin,
            enabled: true,
            config: $config,
            actionconfig: [generate_text::class => ['enabled' => true, 'settings' => $settings]],
        );
        set_config('aiaction_' . aimanager::OPTION_GRADE_STUDENT_SUBMISSION, $provider->id, constants::M_COMPONENT);
        return $provider;
    }

    /**
     * A reply body in the shape the given provider's API returns.
     *
     * @param string $plugin
     * @param string $text what the model wrote
     * @return Response
     */
    private function reply(string $plugin, string $text): Response {
        switch ($plugin) {
            case 'openai':
                $body = [
                    'id' => 'chatcmpl-1',
                    'model' => 'gpt-4o',
                    'choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
                    'system_fingerprint' => 'fp_1',
                ];
                break;
            case 'gemini':
                $body = [
                    'responseId' => 'r1',
                    'candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP']],
                    'usageMetadata' => ['promptTokenCount' => 10, 'totalTokenCount' => 30],
                ];
                break;
            default:
                $body = [
                    'model' => 'llama3.2',
                    'response' => $text,
                    'done_reason' => 'stop',
                    'prompt_eval_count' => 10,
                    'eval_count' => 20,
                ];
        }
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * The text of the prompt in a recorded request.
     *
     * @param array $history from get_mocked_http_client()
     * @param int $index
     * @return string
     */
    private function sent(array $history, int $index): string {
        return json_encode(json_decode((string) $history[$index]['request']->getBody(), true), JSON_UNESCAPED_UNICODE);
    }

    public function test_extract_json_object(): void {
        $this->assertEquals('B1', aimanager::extract_json_object('{"cefr": "B1"}')->cefr);
        $fence = str_repeat(chr(96), 3);
        $this->assertEquals('B1', aimanager::extract_json_object("{$fence}json\n{\"cefr\": \"B1\"}\n{$fence}")->cefr);
        $this->assertEquals('B1', aimanager::extract_json_object("Here you go:\n{\"cefr\": \"B1\"}\nHope that helps.")->cefr);
        // Nested objects and braces inside strings survive.
        $nested = aimanager::extract_json_object('Sure! {"a": {"b": "x}y"}, "c": [1, 2]}');
        $this->assertEquals('x}y', $nested->a->b);
        // Paragraph breaks written as real newlines inside a string are repaired.
        $paragraphs = aimanager::extract_json_object("{\"feedback\": \"Well done.\n\nNext time, slow down.\"}");
        $this->assertEquals("Well done.\n\nNext time, slow down.", $paragraphs->feedback);
        $this->assertNull(aimanager::extract_json_object('The level is B1.'));
        $this->assertNull(aimanager::extract_json_object('[1, 2, 3]'));
        $this->assertNull(aimanager::extract_json_object(''));
    }

    public function test_cloudpoodll_is_default(): void {
        $this->assertFalse(aimanager::uses_moodle_ai());
        $this->assertEquals(
            aimanager::CLOUDPOODLL_OPTION,
            aimanager::get_action_provider_setting(aimanager::OPTION_GRADE_STUDENT_SUBMISSION)
        );
        $this->assertArrayHasKey(
            aimanager::CLOUDPOODLL_OPTION,
            aimanager::get_provider_options(aimanager::AIMANAGER_ACTIONS[aimanager::OPTION_GRADE_STUDENT_SUBMISSION])
        );
    }

    public function test_provider_is_offered_in_settings(): void {
        $provider = $this->use_provider('openai');
        $options = aimanager::get_provider_options(generate_text::class);
        $this->assertArrayHasKey($provider->id, $options);
        $this->assertTrue(aimanager::uses_moodle_ai());
    }

    /**
     * Grading works with each core provider, and what is sent is the stock request: no provider
     * specific parameters are added and the admin's own settings are kept.
     *
     * @dataProvider provider_names
     * @param string $plugin
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_names')]
    public function test_ai_grade_each_provider(string $plugin): void {
        global $DB;
        $this->use_provider($plugin);
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $fence = str_repeat(chr(96), 3);
        $reply = '{"correctedtext": "I went home.", "marks": 80, "feedback": ["Good.", "Use past tense."]}';
        $mock->append($this->reply($plugin, "{$fence}json\n{$reply}\n{$fence}"));

        $context = \context_system::instance();
        $grade = utils::fetch_ai_grade(
            '',
            'useast1',
            'en-US',
            false,
            'I go home yesterday.',
            $this->instructions(),
            $context->id
        );

        $this->assertEquals(80, $grade->marks);
        $this->assertEquals('I went home.', $grade->correctedtext);
        $this->assertEquals(['Good.', 'Use past tense.'], $grade->feedback);
        $this->assertEquals('I go home yesterday.', $grade->submittedtext);

        $this->assertCount(1, $history);
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('response_format', $body);
        $this->assertArrayNotHasKey('max_tokens', $body);
        $sent = $this->sent($history, 0);
        if ($plugin !== 'gemini') {
            // Core's Gemini provider always sends core's default system instruction, not the admin's.
            $this->assertStringContainsString('Admin system instruction', $sent);
        }
        $this->assertStringContainsString('I go home yesterday.', $sent);
        $this->assertStringContainsString('Give two short tips.', $sent);
        $this->assertStringContainsString('Give feedback in Japanese.', $sent);
        $this->assertStringContainsString('The maximum score is: 100.', $sent);

        // Logged in the core AI action register like any other Moodle AI request.
        $this->assertEquals(1, $DB->count_records(
            'ai_action_register',
            ['actionname' => 'generate_text', 'success' => 1, 'contextid' => $context->id]
        ));
    }

    /**
     * Provider names for the data provider.
     *
     * @return array
     */
    public static function provider_names(): array {
        return [['openai'], ['gemini'], ['ollama']];
    }

    public function test_null_marks_and_feedback_are_left_out(): void {
        $this->use_provider('openai');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->reply('openai', '{"correctedtext": "I went home.", "marks": null, "feedback": null}'));

        $grade = utils::fetch_ai_grade('', 'useast1', 'en-US', false, 'I go home.', $this->instructions());
        $this->assertObjectNotHasProperty('marks', $grade);
        $this->assertObjectNotHasProperty('feedback', $grade);
        $this->assertEquals('I went home.', $grade->correctedtext);
    }

    public function test_speech_is_punctuated_before_grading(): void {
        $this->use_provider('openai');
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append($this->reply('openai', '{"passage": "Yesterday I go home. It was late."}'));
        $mock->append($this->reply('openai', '{"correctedtext": "Yesterday I went home.", "marks": 70, "feedback": []}'));

        $grade = utils::fetch_ai_grade(
            '',
            'useast1',
            'en-US',
            true,
            'yesterday i go home it was late',
            $this->instructions()
        );

        $this->assertCount(2, $history);
        $this->assertStringContainsString('[[Yesterday I go home. It was late.]]', $this->sent($history, 1));
        $this->assertStringContainsString('Do not correct spelling or punctuation', $this->sent($history, 1));
        $this->assertEquals(70, $grade->marks);
    }

    public function test_punctuation_that_changes_the_words_is_not_used(): void {
        $this->use_provider('openai');
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append($this->reply('openai', '{"passage": "Yesterday I went home."}'));
        $mock->append($this->reply('openai', '{"correctedtext": "Yesterday I went home.", "marks": 70, "feedback": []}'));

        utils::fetch_ai_grade('', 'useast1', 'en-US', true, 'yesterday i go home', $this->instructions());
        $this->assertStringContainsString('[[yesterday i go home]]', $this->sent($history, 1));
    }

    public function test_attempt_is_punctuated_without_a_cloudpoodll_token(): void {
        global $DB;
        $this->use_provider('ollama');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->reply('ollama', '{"passage": "The birch canoe slid on the smooth planks."}'));

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $solo = $gen->create_module('solo', ['course' => $course->id, 'ttslanguage' => 'en-US']);
        $student = $gen->create_and_enrol($course, 'student');
        $text = 'the birch canoe slid on the smooth planks';
        $id = $DB->insert_record(constants::M_ATTEMPTSTABLE, (object) ['solo' => $solo->id, 'userid' => $student->id,
            'transcript' => $text, 'selftranscript' => $text,
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $student->id, 'modifiedby' => $student->id]);
        $attempt = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $id], '*', MUST_EXIST);
        $module = $DB->get_record(constants::M_TABLE, ['id' => $solo->id], '*', MUST_EXIST);

        $after = utils::punctuate_attempt_transcript($attempt, $module, \context_module::instance($solo->cmid)->id);
        $this->assertEquals('The birch canoe slid on the smooth planks.', $after->transcript);
        $this->assertEquals(
            'The birch canoe slid on the smooth planks.',
            $DB->get_field(constants::M_ATTEMPTSTABLE, 'selftranscript', ['id' => $id])
        );
    }

    public function test_prose_reply_is_retried_once(): void {
        $this->use_provider('gemini');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->reply('gemini', 'The student wrote quite well, I would give 80 out of 100.'));
        $mock->append($this->reply('gemini', '{"correctedtext": "I went home.", "marks": 80, "feedback": null}'));

        $grade = utils::fetch_ai_grade('', 'useast1', 'en-US', false, 'I go home yesterday.', $this->instructions());
        $this->assertDebuggingCalled();
        $this->assertEquals(80, $grade->marks);
    }

    public function test_prose_reply_twice_is_an_error_not_an_empty_grade(): void {
        $this->use_provider('ollama');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->reply('ollama', 'Good effort!'));
        $mock->append($this->reply('ollama', 'Really, good effort!'));

        $grade = utils::fetch_ai_grade('', 'useast1', 'en-US', false, 'I go home yesterday.', $this->instructions());
        // Two "not the JSON asked for" messages, then the failure.
        $this->assertDebuggingCalledCount(3);
        $this->assertFalse($grade);
    }

    public function test_disabled_provider_fails_cleanly(): void {
        $provider = $this->use_provider('openai');
        \core\di::get(\core_ai\manager::class)->disable_provider_instance($provider);

        $aimanager = new aimanager(null, 'en-US');
        $this->assertFalse($aimanager->predict_cefr('Some text.'));
        $this->assertDebuggingCalled();
        $this->assertEquals(get_string('aiprovider_unavailable', constants::M_COMPONENT), $aimanager->get_error_message());
    }

    public function test_provider_error_is_not_retried(): void {
        $this->use_provider('openai');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['message' => 'Invalid API key']])
        ));

        $textanalyser = new textanalyser('', 'Some text.', 'useast1', 'en-US');
        $this->assertFalse($textanalyser->fetch_idea_count());
        $this->assertDebuggingCalled();
        $this->assertCount(0, $mock);
    }

    public function test_text_analysis_keeps_cloudpoodll_shape(): void {
        $this->use_provider('openai');
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $mock->append($this->reply('openai', '{"cefr": "b2"}'));
        $mock->append($this->reply('openai', '{"count": 3}'));
        $mock->append($this->reply('openai', '{"relevance": 8}'));
        $mock->append($this->reply('openai', '{"correctedtext": ". I went home."}'));
        $mock->append($this->reply('openai', '{"cefr": "B7"}'));
        $mock->append($this->reply('openai', '{"cefr": "B7"}'));

        $textanalyser = new textanalyser('', 'I go home.', 'useast1', 'fr-FR');
        $this->assertSame('B2', $textanalyser->fetch_cefr_level());
        $this->assertStringContainsString('French', $this->sent($history, 0));
        $this->assertSame(3, $textanalyser->fetch_idea_count());
        $this->assertSame(80, $textanalyser->fetch_relevance_topic('Holidays'));
        $this->assertSame('I went home.', $textanalyser->fetch_grammar_correction());
        // Not a CEFR level: tried again, then reported as no level, as Cloud Poodll's reply would be.
        $this->assertFalse($textanalyser->fetch_cefr_level());
        $this->assertDebuggingCalledCount(3);
        // No topic means fully relevant, without asking.
        $this->assertSame(100, $textanalyser->fetch_relevance_topic(''));
        $this->assertCount(6, $history);
    }
}
