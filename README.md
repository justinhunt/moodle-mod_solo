# Poodll Solo for Moodle

**An automatically graded open speaking (or writing) assignment for Moodle.**

The teacher sets a speaking topic — a prompt, a target speaking time, a target word count, and
optional target keywords — and the student responds by recording themselves speaking (or, in
writing mode, by typing). Poodll Solo grades the submission automatically using speech
recognition and AI: word count, grammar accuracy, relevance to the topic, and speaking clarity
all feed into the grade, and the student gets grammar correction suggestions as feedback.

Used for oral exam preparation, speaking practice with automated feedback, low/medium-stakes
speaking assessments (e.g. placement tests), and short essay writing tasks with instant feedback.

- **Plugin:** `mod_solo` (activity module)
- **Maintainer:** Justin Hunt — poodllsupport@gmail.com
- **Documentation:** https://support.poodll.com
- **License:** GNU GPL v3 or later

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Site configuration](#site-configuration)
- [The speaking topic](#the-speaking-topic)
- [The activity steps](#the-activity-steps)
- [Auto-grading](#auto-grading)
- [Reports and tabs](#reports-and-tabs)
- [Mobile support](#mobile-support)
- [Privacy](#privacy)
- [Support](#support)

---

## Requirements

| | |
|---|---|
| Moodle | 4.3 or later (`$plugin->requires = 2023100900`)|
| PHP | 8.1+ (PHP 8.4 supported) |
| Database | MySQL / MariaDB / PostgreSQL |
| Cloud Poodll account | **Required.** An API user and secret from https://poodll.com |

Solo relies on the Cloud Poodll service for audio/video recording, speech recognition, grammar
checking and AI grading. Without API credentials the activity will not function.
See [Cloud Poodll API secret](https://support.poodll.com/support/solutions/articles/19000083076-cloud-poodll-api-secret)
for how to obtain them.

## Installation

1. Copy the plugin folder to `mod/solo` in your Moodle code root (on Moodle 5.0+ this is
   `public/mod/solo`).
2. Visit **Site administration → Notifications** and complete the upgrade.
3. Enter your Cloud Poodll API user and secret at
   **Site administration → Plugins → Activity modules → Poodll Solo**.

## Site configuration

Settings live under **Site administration → Plugins → Activity modules → Poodll Solo**:

- **API user / API secret** Without these the plugin will not function.
- **AWS region**  The AWS region processing and data storage take place in, - - **Cloud Poodll server** The default is fine. Users with AWS region Ningxia in China should use cloud.poodll.cn
- **Enable native language** — lets a student choose their own language for AI feedback.

## The speaking topic

Each activity is built around one speaking topic, set up by the teacher:

- The topic prompt itself, plus optional supporting resources (images, video, model audio).
- A **target speaking time** and **target word count**, used in grading.
- **Target words** — specific words or phrases the student is encouraged to use; using them
  earns bonus points.

## The activity steps

A Solo attempt follows a sequence of steps, chosen per activity from four options:

| Sequence | Steps |
|---|---|
| **Prepare → Record → Model** *(default)* | Read the topic, record a spoken response, then optionally review a model answer. |
| **Prepare → Type → Record → Model** | Type out a response first (e.g. a script), then record it being spoken aloud — useful for practicing reading a prepared script. |
| **Prepare → Type → Model** | A writing-only variant: the student types their response instead of recording; no audio is involved. |
| **Record → Model** | Skips the preparation step, straight to recording. |

Every sequence ends with the student reviewing their results and (if configured) a model answer.
The record step accepts audio or video.

## Auto-grading

The grade is built from a configurable formula, combining:

- **Word count** — actual words spoken/written against the target word count.
- **AI grade** — an overall AI-assessed quality score.
- **Relevance** — how well the response matches the topic (or a model answer), assessed by AI.
- **Bonus points** — for using specified target words.

Each component's weighting is set on the activity's grading settings page. Alongside the grade,
students receive suggested grammar corrections and other AI feedback on their submission.
Teachers can review and override any grade from the Grades tab.

## Reports and tabs

Teacher tabs on the activity: **Attempts**, **Grades**, **Reports**, **Setup** (if enabled), and
**Developer** (diagnostic tools). Reports let a teacher review submissions, recordings,
transcripts and grades per student.

Over 50 speech recognition / TTS locales are supported, including multiple variants of English,
Spanish, French, German, Portuguese, Chinese, Japanese, Korean, Arabic, Hindi, several Indian
regional languages, and many more.

## Mobile support

Poodll Solo is supported in the Moodle mobile app via `db/mobile.php`.

## Privacy

Solo stores personal data: attempts, grades, session data, recordings and transcripts.
Recordings are stored via Cloud Poodll (AWS S3), and the Moodle user id appears in
recording/transcript URLs. The plugin implements the Moodle Privacy API for export and deletion.

## Support

- Documentation and how-tos: https://support.poodll.com
- Account and subscriptions: https://member.poodll.com
- Contact: poodllsupport@gmail.com

## License

Copyright Justin Hunt / Poodll. Licensed under the
[GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).
