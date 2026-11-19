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

use core\di;
use core_ai\aiactions\generate_text as core_generate_text;
use core_ai\aiactions\responses\response_base;
use core_ai\manager;
use core_ai\provider;
use mod_solo\local\aiprompts;

/**
 * Sends the AI requests that evaluate student work to a Moodle AI provider, when the admin has chosen one.
 *
 * Cloud Poodll is the default, and its code stays in utils and textanalyser. Those ask uses_moodle_ai()
 * first and, if it says yes, call the matching method here instead. Each method returns exactly what the
 * Cloud Poodll code returns to its caller, so the callers do not change. A failure is reported and gives
 * false; it is never sent on to Cloud Poodll.
 *
 * Semantic similarity and embeddings always use Cloud Poodll: core Moodle AI has no action for them.
 *
 * @package    mod_solo
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aimanager {
    /** @var int Times to ask for a JSON reply before giving up: one go, then one more. */
    const GENERATION_ATTEMPTS = 2;

    /** @var int Seconds to wait before asking again. */
    const GENERATION_RETRY_DELAY = 1;

    /** @var int The setting value that means "use Cloud Poodll", the default. */
    public const CLOUDPOODLL_OPTION = -1;

    /** @var string Grading, correcting and analysing what a student wrote or said. */
    public const OPTION_GRADE_STUDENT_SUBMISSION = 'grade_student_submission';

    /**
     * The core AI action each kind of request is sent as. All of them are plain text generation:
     * the prompt carries everything, so any provider that can generate text can take them.
     *
     * @var array
     */
    public const AIMANAGER_ACTIONS = [
        self::OPTION_GRADE_STUDENT_SUBMISSION => core_generate_text::class,
    ];

    /** @var int|null */
    protected $contextid;

    /** @var string|null The language of the student's work, such as en-US. */
    protected $language;

    /** @var string */
    protected $errormessage = '';

    /** @var string */
    protected $lastprovider = '';

    /**
     * aimanager constructor.
     *
     * @param int|null $contextid the context the request is made in, for the AI action register
     * @param string|null $language the language of the student's work, such as en-US
     */
    public function __construct($contextid = null, $language = null) {
        $this->contextid = $contextid;
        $this->language = $language;
    }

    /**
     * The name of the provider the last request went to.
     *
     * @return string
     */
    public function get_last_provider() {
        return $this->lastprovider;
    }

    /**
     * What went wrong with the last failed request.
     *
     * @return string
     */
    public function get_error_message() {
        return $this->errormessage;
    }

    /**
     * The kinds of request an admin can send to a Moodle AI provider, for the settings page.
     *
     * @return array
     */
    public static function get_action_options() {
        $options = [];
        foreach (array_keys(static::AIMANAGER_ACTIONS) as $actiontype) {
            $options[$actiontype] = [
                'name' => get_string($actiontype, constants::M_COMPONENT),
                'description' => get_string($actiontype . '_desc', constants::M_COMPONENT),
            ];
        }
        return $options;
    }

    /**
     * The full setting name (component/name) for a kind of request.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return string
     */
    public static function get_action_settingname($actiontype) {
        return constants::M_COMPONENT . "/aiaction_{$actiontype}";
    }

    /**
     * The provider chosen for a kind of request: a provider id (a name on Moodle 4.5), or CLOUDPOODLL_OPTION.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return int|string
     */
    public static function get_action_provider_setting($actiontype) {
        [$component, $settingname] = explode('/', static::get_action_settingname($actiontype), 2);
        $setting = get_config($component, $settingname);
        return !empty($setting) ? $setting : static::CLOUDPOODLL_OPTION;
    }

    /**
     * Whether the admin has sent this kind of request to a Moodle AI provider instead of Cloud Poodll.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return bool
     */
    public static function uses_moodle_ai(string $actiontype = self::OPTION_GRADE_STUDENT_SUBMISSION): bool {
        return isset(static::AIMANAGER_ACTIONS[$actiontype])
            && static::get_action_provider_setting($actiontype) != static::CLOUDPOODLL_OPTION
            && static::get_ai_manager() !== null;
    }

    /**
     * Grade a student's response.
     *
     * A speech transcript with no punctuation is punctuated first, as Cloud Poodll does, because the grading
     * is much better on punctuated text. If that step fails the transcript is graded as it is.
     *
     * @param string $studentresponse
     * @param object $instructions feedbackscheme, feedbacklanguage, markscheme, maxmarks
     * @param bool $isspeech the response is a speech transcript
     * @return \stdClass|false submittedtext, correctedtext and, when set, marks and feedback; as utils::fetch_ai_grade()
     */
    public function autograde($studentresponse, $instructions, bool $isspeech) {
        $submittedtext = (string) $studentresponse;
        if ($isspeech) {
            $punctuated = $this->punctuate($submittedtext);
            if ($punctuated !== false) {
                $submittedtext = $punctuated;
            }
        }

        $instructionsobj = json_decode(json_encode($instructions));
        $response = $this->call_moodle_ai_json(
            aiprompts::autograde($submittedtext, $instructionsobj, $this->language, $isspeech),
            ['correctedtext', 'marks'],
            fn($data) => $data->marks === null || is_numeric($data->marks)
        );
        if ($response->returnCode !== 0) {
            return false;
        }
        $grade = $response->data;
        if (!isset($grade->submittedtext) || !is_string($grade->submittedtext)) {
            $grade->submittedtext = $submittedtext;
        }
        // Cloud Poodll's reply leaves out a mark or feedback it did not give.
        foreach (['marks', 'feedback'] as $key) {
            if (property_exists($grade, $key) && $grade->{$key} === null) {
                unset($grade->{$key});
            }
        }
        return $grade;
    }

    /**
     * Add punctuation to a speech transcript, if it has none.
     *
     * @param string $text
     * @return string|false the punctuated text, or false if it needs none, could not be done, or the words changed
     */
    public function punctuate($text) {
        $text = (string) $text;
        if (!utils::needs_punctuation($text)) {
            return false;
        }
        $response = $this->call_moodle_ai_json(
            aiprompts::punctuation($text, $this->language),
            ['passage'],
            fn($data) => is_string($data->passage)
        );
        if ($response->returnCode !== 0) {
            return false;
        }
        $punctuated = trim($response->data->passage);
        if ($punctuated === '' || !utils::same_words($text, $punctuated)) {
            return false;
        }
        return $punctuated;
    }

    /**
     * Correct the grammar and spelling of a passage.
     *
     * @param string $passage
     * @return string|false the corrected text, as textanalyser::fetch_grammar_correction()
     */
    public function grammar_correction($passage) {
        $response = $this->call_moodle_ai_json(
            aiprompts::grammar_correction((string) $passage, $this->language),
            ['correctedtext'],
            fn($data) => is_string($data->correctedtext)
        );
        if ($response->returnCode !== 0) {
            return false;
        }
        return textanalyser::tidy_correction($response->data->correctedtext);
    }

    /**
     * How relevant a student's response is to the topic.
     *
     * @param string $topic
     * @param string $passage the student's response
     * @return int|false 10 to 100, as textanalyser::fetch_relevance_topic()
     */
    public function topic_relevance($topic, $passage) {
        $response = $this->call_moodle_ai_json(
            aiprompts::topic_relevance((string) $topic, (string) $passage),
            ['relevance'],
            fn($data) => is_numeric($data->relevance)
        );
        if ($response->returnCode !== 0) {
            return false;
        }
        // The prompt asks for 1 to 10.
        return (int) round(10 * max(1, min(10, (float) $response->data->relevance)));
    }

    /**
     * Estimate the CEFR level of a passage.
     *
     * @param string $passage
     * @return string|false A1 to C2, as textanalyser::fetch_cefr_level()
     */
    public function predict_cefr($passage) {
        $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        $response = $this->call_moodle_ai_json(
            aiprompts::predict_cefr((string) $passage, $this->language),
            ['cefr'],
            fn($data) => is_string($data->cefr) && in_array(strtoupper(trim($data->cefr)), $levels)
        );
        if ($response->returnCode !== 0) {
            return false;
        }
        return strtoupper(trim($response->data->cefr));
    }

    /**
     * Count the different ideas in a passage.
     *
     * @param string $passage
     * @return int|false as textanalyser::fetch_idea_count()
     */
    public function count_unique_ideas($passage) {
        $response = $this->call_moodle_ai_json(
            aiprompts::count_unique_ideas((string) $passage, $this->language),
            ['count'],
            fn($data) => is_numeric($data->count)
        );
        if ($response->returnCode !== 0) {
            return false;
        }
        return (int) $response->data->count;
    }

    /**
     * The Moodle AI provider chosen for a kind of request, if it is still there and usable.
     *
     * Only enabled, configured providers with text generation turned on are returned, so a provider that
     * was disabled or deleted after it was chosen gives null.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return provider|null
     */
    public static function get_moodle_provider(string $actiontype): ?provider {
        global $CFG;
        $manager = static::get_ai_manager();
        $actionclass = static::AIMANAGER_ACTIONS[$actiontype] ?? null;
        $setting = static::get_action_provider_setting($actiontype);
        if (!$manager || !$actionclass || $setting == static::CLOUDPOODLL_OPTION) {
            return null;
        }
        // Static on Moodle 4.5, an instance method later: calling it through the instance works on both.
        $providers = $manager->get_providers_for_actions([$actionclass], true);
        foreach ($providers[$actionclass] ?? [] as $provider) {
            // Moodle 4.5 has one provider per plugin, chosen by name; later versions have instances, chosen by id.
            $id = $CFG->branch < 500 ? $provider->get_name() : $provider->id;
            if ((string) $id === (string) $setting) {
                return $provider;
            }
        }
        return null;
    }

    /**
     * Send a prompt to the Moodle AI provider chosen for a kind of request, and return its text.
     *
     * The prompt goes as the stock core generate_text action, through the provider's own processor, so
     * the provider runs exactly as the admin set it up (model, system instruction, extra parameters,
     * rate limits) and nothing here depends on which provider it is. The call is logged in the core AI
     * action register like any other Moodle AI request.
     *
     * @param string $prompt
     * @param string $actiontype one of the OPTION_ constants
     * @return \stdClass returnCode (0 = success) and returnMessage (the text, or the error)
     */
    public function call_moodle_ai(string $prompt, string $actiontype = self::OPTION_GRADE_STUDENT_SUBMISSION): \stdClass {
        global $CFG, $USER;

        $provider = static::get_moodle_provider($actiontype);
        if (!$provider) {
            $this->lastprovider = 'Moodle AI';
            return $this->moodle_ai_failure(get_string('aiprovider_unavailable', constants::M_COMPONENT), [
                'actiontype' => $actiontype,
                'setting' => static::get_action_provider_setting($actiontype),
            ]);
        }
        // The name the admin gave the provider; Moodle 4.5 has only the plugin's name.
        $this->lastprovider = $CFG->branch < 500 ? $provider->get_name() : $provider->name;

        $contextid = $this->contextid ?: \context_system::instance()->id;
        $action = new core_generate_text(
            contextid: (int) $contextid,
            userid: (int) $USER->id,
            prompttext: $prompt,
        );

        // This is what core_ai\manager::call_action_provider() does. It is protected, and the public
        // process_action() chooses the provider itself instead of using the one the admin chose here.
        $processclass = static::get_component_from_classname(get_class($provider))
            . '\\process_' . $action->get_basename();
        if (!class_exists($processclass)) {
            return $this->moodle_ai_failure(get_string('aiprovider_unavailable', constants::M_COMPONENT), [
                'provider' => $provider->get_name(),
                'missing class' => $processclass,
            ]);
        }

        try {
            $processor = new $processclass($provider, $action);
            $response = $processor->process();
        } catch (\Throwable $e) {
            return $this->moodle_ai_failure($e->getMessage(), [
                'provider' => $provider->get_name(),
                'exception' => get_class($e),
            ]);
        }

        static::log_moodle_ai_action($provider, $action, $response);

        if (!$response->get_success()) {
            $message = $response->get_errormessage();
            return $this->moodle_ai_failure(
                !empty($message) ? $message : get_string('aiprovider_badreply', constants::M_COMPONENT),
                ['provider' => $provider->get_name(), 'errorcode' => $response->get_errorcode()]
            );
        }

        $data = $response->get_response_data();
        return (object) [
            'returnCode' => 0,
            'returnMessage' => (string) ($data['generatedcontent'] ?? ''),
        ];
    }

    /**
     * Send a prompt that asks for a JSON object to a Moodle AI provider, and check the reply.
     *
     * Providers do not share a reliable "reply in JSON" switch, so the prompt asks for JSON and the reply
     * is read leniently (code fences and text around the object are ignored). It must decode to an object
     * holding every required key, and $accept must accept it. If not, the prompt is sent once more, because
     * a model that wrote prose instead of JSON usually gets it right the second time. A provider error
     * (rate limit, bad key, network) is not repeated.
     *
     * @param string $prompt
     * @param string[] $requiredkeys keys the JSON object must have
     * @param callable|null $accept given the decoded object, returns false to reject it
     * @param string $actiontype one of the OPTION_ constants
     * @return \stdClass returnCode, returnMessage and, on success, data (the decoded object)
     */
    public function call_moodle_ai_json(
        string $prompt,
        array $requiredkeys,
        ?callable $accept = null,
        string $actiontype = self::OPTION_GRADE_STUDENT_SUBMISSION
    ): \stdClass {
        for ($attempt = 1; $attempt <= self::GENERATION_ATTEMPTS; $attempt++) {
            $response = $this->call_moodle_ai($prompt, $actiontype);
            if ($response->returnCode !== 0) {
                return $response;
            }

            $data = static::extract_json_object($response->returnMessage);
            $missing = $data ? array_diff($requiredkeys, array_keys(get_object_vars($data))) : $requiredkeys;
            if ($data && empty($missing) && (!$accept || $accept($data))) {
                return (object) [
                    'returnCode' => 0,
                    'returnMessage' => $response->returnMessage,
                    'data' => $data,
                ];
            }

            debugging('mod_solo Moodle AI reply was not the JSON asked for [' . implode(' | ', [
                'provider: ' . $this->lastprovider,
                'attempt: ' . $attempt . ' of ' . self::GENERATION_ATTEMPTS,
                'missing keys: ' . (!$data ? '(not a JSON object)' : (implode(', ', $missing) ?: '(none, a value was not usable)')),
                'reply: ' . str_replace(["\r", "\n"], ' ', substr($response->returnMessage, 0, 1000)),
            ]) . ']', DEBUG_DEVELOPER);
            if ($attempt < self::GENERATION_ATTEMPTS) {
                sleep(self::GENERATION_RETRY_DELAY);
            }
        }

        return $this->moodle_ai_failure(get_string('aiprovider_badreply', constants::M_COMPONENT), [
            'provider' => $this->lastprovider,
        ]);
    }

    /**
     * Find the JSON object in a model's reply.
     *
     * Accepts a bare object, an object in a markdown code fence, or an object with text before or after
     * it (taken from the first "{" to the last "}"). Models often write paragraph breaks inside a string
     * as real newlines instead of \\n, which is not valid JSON, so those are repaired. Anything that is
     * not a JSON object gives null.
     *
     * @param string $text
     * @return \stdClass|null
     */
    public static function extract_json_object(string $text): ?\stdClass {
        $text = trim($text);
        $candidates = [$text];
        if (preg_match('/\x60{3}(?:json)?\s*([\s\S]*?)\x60{3}/i', $text, $matches)) {
            $candidates[] = trim($matches[1]);
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }
        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate);
            if (!$decoded instanceof \stdClass) {
                $decoded = json_decode(static::escape_json_control_chars($candidate));
            }
            if ($decoded instanceof \stdClass) {
                return $decoded;
            }
        }
        return null;
    }

    /**
     * Escape control characters (such as newlines) inside the string literals of a JSON document.
     *
     * Control characters between literals are left alone: they are ordinary whitespace. Working byte by
     * byte is safe for UTF-8, because every byte of a multi-byte character is 0x80 or above.
     *
     * @param string $json
     * @return string
     */
    protected static function escape_json_control_chars(string $json): string {
        $out = '';
        $instring = false;
        $escaped = false;
        $length = strlen($json);
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if (!$instring) {
                $instring = $char === '"';
                $out .= $char;
                continue;
            }
            if ($escaped) {
                $escaped = false;
                $out .= $char;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                $out .= $char;
            } else if ($char === '"') {
                $instring = false;
                $out .= $char;
            } else if (ord($char) < 0x20) {
                $out .= ["\n" => '\n', "\r" => '\r', "\t" => '\t'][$char] ?? sprintf('\u%04x', ord($char));
            } else {
                $out .= $char;
            }
        }
        return $out;
    }

    /**
     * Record a failed Moodle AI request and build the reply for it.
     *
     * @param string $message what went wrong, also kept for get_error_message()
     * @param array $detail context for the developer debugging message
     * @return \stdClass returnCode 1 and returnMessage
     */
    protected function moodle_ai_failure(string $message, array $detail = []): \stdClass {
        $this->errormessage = $message;
        $lines = [];
        foreach ($detail as $key => $value) {
            $lines[] = $key . ': ' . str_replace(["\r", "\n"], ' ', (string) $value);
        }
        debugging(
            'mod_solo Moodle AI request failed - ' . $message . ' [' . implode(' | ', $lines) . ']',
            DEBUG_DEVELOPER
        );
        return (object) [
            'returnCode' => 1,
            'returnMessage' => $message,
        ];
    }

    /**
     * Log a Moodle AI request in the core AI action register, as core_ai\manager does for its own.
     *
     * The register feeds the AI usage report and the core AI privacy provider. Core's own method for this
     * is private, so this writes the same record. A logging failure is reported to developers but does
     * not stop the request.
     *
     * @param provider $provider
     * @param core_generate_text $action
     * @param response_base $response
     * @return void
     */
    protected static function log_moodle_ai_action(
        provider $provider,
        core_generate_text $action,
        response_base $response
    ): void {
        global $DB;
        $record = [
            'actionname' => $action->get_basename(),
            'success' => $response->get_success(),
            'userid' => $action->get_configuration('userid'),
            'contextid' => $action->get_configuration('contextid'),
            'provider' => $provider->get_name(),
            'errorcode' => $response->get_errorcode(),
            'errormessage' => $response->get_errormessage(),
            'timecreated' => $action->get_configuration('timecreated'),
            'timecompleted' => $response->get_timecreated(),
        ];
        // Columns added after Moodle 4.5.
        $columns = $DB->get_columns('ai_action_register');
        if (isset($columns['error']) && method_exists($response, 'get_error')) {
            $record['error'] = $response->get_error();
        }
        if (isset($columns['model']) && method_exists($response, 'get_model_used')) {
            $record['model'] = $response->get_model_used();
        }
        try {
            $record['actionid'] = $action->store($response);
            $DB->insert_record('ai_action_register', (object) $record);
        } catch (\Throwable $e) {
            debugging('mod_solo could not log a Moodle AI request: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The choices for a kind of request on the settings page: Cloud Poodll, then every enabled
     * Moodle AI provider that can generate text.
     *
     * @param string $actionclass the core AI action class
     * @return array setting value => name
     */
    public static function get_provider_options($actionclass) {
        global $CFG;
        $options = [static::CLOUDPOODLL_OPTION => get_string('cloudpoodll', constants::M_COMPONENT)];
        $manager = static::get_ai_manager();
        if (!empty($manager)) {
            $allproviders = $manager->get_providers_for_actions([$actionclass], true);
            foreach ($allproviders[$actionclass] ?? [] as $aiprovider) {
                if ($CFG->branch < 500) {
                    $options[$aiprovider->get_name()] = $aiprovider->get_name();
                } else {
                    $aiproviderrecord = $aiprovider->to_record();
                    $options[$aiproviderrecord->id] = $aiproviderrecord->name;
                }
            }
        }
        return $options;
    }

    /**
     * The core AI manager, or null before Moodle 4.5.
     *
     * @return manager|null
     */
    public static function get_ai_manager(): ?manager {
        if (!class_exists(manager::class)) {
            return null;
        }
        return di::get(manager::class);
    }

    /**
     * Resolve the frankenstyle component name from a PSR-4 class name.
     *
     * This intentionally does not delegate to \core\component /
     * \core_component::get_component_from_classname(). The namespaced \core\component
     * class only exists on Moodle 5.0+, and core_component::get_component_from_classname()
     * does not exist before Moodle 4.4, so a direct reference fatals on Moodle 4.3.
     * Every caller passes a PSR-4 / frankenstyle class name, so the first namespace
     * segment is the component.
     *
     * @param string $classname
     * @return string The component name, or '' if it could not be determined.
     */
    public static function get_component_from_classname($classname): string {
        return strstr(ltrim($classname, '\\'), '\\', true) ?: '';
    }
}
