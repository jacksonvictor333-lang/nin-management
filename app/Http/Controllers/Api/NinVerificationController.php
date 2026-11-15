<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\NinVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NinVerificationController extends Controller
{
    /**
     * Idadi ya maswali kwa kila hatua.
     */
    private const STAGE_ONE_QUESTIONS = 3;
    private const TOTAL_QUESTIONS = 5;

    /**
     * Hatua 1: majibu yote 3 sahihi = PASS.
     * Hatua 1: chini ya 2 sahihi = FAIL papo hapo.
     * Hatua 2: angalau 4 kati ya 5 sahihi = PASS.
     */
    private const STAGE_ONE_PASS = 3;
    private const STAGE_ONE_CONTINUE = 2;
    private const FINAL_PASS = 4;

    /**
     * Kufungiwa: NIN ikishindwa mara hizi ndani ya dakika hizi, majaribio mapya yanakataliwa.
     */
    private const MAX_FAILURES = 3;
    private const LOCKOUT_MINUTES = 60;

    /**
     * Maswali yenye nguvu: majibu yake ni magumu kubahatisha.
     */
    private function strongQuestions(): array
    {
        return [
            'first_name' => ['question' => 'What is your first name?', 'type' => 'text'],
            'middle_name' => ['question' => 'What is your middle name?', 'type' => 'text'],
            'last_name' => ['question' => 'What is your last name?', 'type' => 'text'],
            'date_of_birth' => ['question' => 'What is your date of birth? (YYYY-MM-DD)', 'type' => 'date'],
            'birth_region' => ['question' => 'In which region were you born?', 'type' => 'text'],
            'birth_district' => ['question' => 'In which district were you born?', 'type' => 'text'],
        ];
    }

    /**
     * Maswali dhaifu (rahisi kubahatisha). Yanatumika tu kama hakuna
     * maswali ya kutosha yenye nguvu, na huwekwa mwisho.
     */
    private function weakQuestions(): array
    {
        return [
            'nationality' => ['question' => 'What is your nationality?', 'type' => 'text'],
            'gender' => ['question' => 'What is your gender?', 'type' => 'select', 'options' => ['Male', 'Female']],
        ];
    }

    private function questionBank(): array
    {
        return $this->strongQuestions() + $this->weakQuestions();
    }

    /**
     * Chagua maswali 5 kwa ajili ya mtu huyu: yenye nguvu kwanza
     * (yale tu ambayo mtu ana taarifa zake), kisha dhaifu kujaza nafasi.
     */
    private function pickQuestions(Person $person): array
    {
        $available = fn (array $bank) => array_values(array_filter(
            array_keys($bank),
            fn ($field) => trim((string) $person->{$field}) !== ''
        ));

        $strong = $available($this->strongQuestions());
        $weak = $available($this->weakQuestions());

        shuffle($strong);
        shuffle($weak);

        return array_slice(array_merge($strong, $weak), 0, self::TOTAL_QUESTIONS);
    }

    /**
     * Authorize API client.
     */
    private function authorizeClient(Request $request)
    {
        $apiKey = config('services.nin.api_key');

        if (
            !$apiKey ||
            !hash_equals(
                (string) $apiKey,
                (string) $request->header('X-NIN-API-KEY')
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized API access.',
            ], 401);
        }

        return null;
    }

    /**
     * Get question.
     */
    private function getQuestion(string $key): array
    {
        $bank = $this->questionBank();

        $question = [
            'field' => $key,
            'question' => $bank[$key]['question'],
            'type' => $bank[$key]['type'],
        ];

        if (isset($bank[$key]['options'])) {
            $question['options'] = $bank[$key]['options'];
        }

        return $question;
    }

    /**
     * Linganisha jibu lililotumwa na taarifa zilizohifadhiwa.
     */
    private function isCorrectAnswer(Person $person, string $field, string $submitted): bool
    {
        $submitted = trim($submitted);

        if ($field === 'date_of_birth') {
            return $person->date_of_birth
                && $submitted === $person->date_of_birth->format('Y-m-d');
        }

        $normalize = fn ($value) => mb_strtolower(
            preg_replace('/\s+/', ' ', trim((string) $value))
        );

        $expected = $normalize($person->{$field});

        return $expected !== '' && hash_equals($expected, $normalize($submitted));
    }

    private function failedResponse(NinVerification $verification, int $correct, int $answered, int $stage)
    {
        $verification->update(['status' => 'failed']);

        return response()->json([
            'success' => false,
            'verification_status' => 'failed',
            'message' => 'Identity verification failed.',
            'correct_answers' => $correct,
            'questions_answered' => $answered,
            'total_questions' => $stage === 1 ? self::STAGE_ONE_QUESTIONS : self::TOTAL_QUESTIONS,
            'stage' => $stage,
            'can_retrieve_person' => false,
        ], 422);
    }

    private function passedResponse(NinVerification $verification, int $correct, int $answered, int $stage)
    {
        $verification->update(['status' => 'passed']);

        return response()->json([
            'success' => true,
            'verification_status' => 'passed',
            'message' => 'Identity verification successful.',
            'correct_answers' => $correct,
            'questions_answered' => $answered,
            'total_questions' => $stage === 1 ? self::STAGE_ONE_QUESTIONS : self::TOTAL_QUESTIONS,
            'stage' => $stage,
            'can_retrieve_person' => true,
        ]);
    }

    private function nextQuestionResponse(array $questions, int $correct, int $answered)
    {
        $stage = $answered < self::STAGE_ONE_QUESTIONS ? 1 : 2;

        return response()->json([
            'success' => true,
            'verification_status' => 'pending',
            'message' => $answered === self::STAGE_ONE_QUESTIONS
                ? 'Please answer the additional questions.'
                : 'Please answer the next question.',
            'correct_answers' => $correct,
            'questions_answered' => $answered,
            'question_number' => $answered + 1,
            'total_questions' => $stage === 1 ? self::STAGE_ONE_QUESTIONS : self::TOTAL_QUESTIONS,
            'stage' => $stage,
            'question' => $this->getQuestion($questions[$answered]),
        ]);
    }

    /**
     * Start verification.
     */
    public function start(Request $request)
    {
        if ($error = $this->authorizeClient($request)) {
            return $error;
        }

        $validated = $request->validate([
            'nin' => ['required', 'string', 'digits:20'],
        ]);

        // Zuia mashambulizi ya kubahatisha dhidi ya NIN moja.
        $recentFailures = NinVerification::where('nin', $validated['nin'])
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes(self::LOCKOUT_MINUTES))
            ->count();

        if ($recentFailures >= self::MAX_FAILURES) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed verification attempts for this NIN. Please try again later.',
            ], 429);
        }

        $person = Person::where('nin', $validated['nin'])
            ->where('status', 'Active')
            ->first();

        if (!$person) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify the provided NIN.',
            ], 422);
        }

        $questions = $this->pickQuestions($person);

        if (count($questions) < self::TOTAL_QUESTIONS) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify the provided NIN.',
            ], 422);
        }

        $sessionToken = Str::random(64);

        NinVerification::create([
            'nin' => $person->nin,
            'session_token_hash' => hash('sha256', $sessionToken),
            'questions' => $questions,
            'questions_answered' => 0,
            'correct_answers' => 0,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification started successfully.',
            'session_token' => $sessionToken,
            'question_number' => 1,
            'total_questions' => self::STAGE_ONE_QUESTIONS,
            'stage' => 1,
            'question' => $this->getQuestion($questions[0]),
        ]);
    }

    /**
     * Submit verification answer.
     */
    public function answer(Request $request)
    {
        if ($error = $this->authorizeClient($request)) {
            return $error;
        }

        $validated = $request->validate([
            'session_token' => ['required', 'string'],
            'answer' => ['required', 'string', 'max:255'],
        ]);

        $verification = NinVerification::where(
            'session_token_hash',
            hash('sha256', $validated['session_token'])
        )->first();

        if (
            !$verification ||
            $verification->status !== 'pending' ||
            !$verification->expires_at ||
            $verification->expires_at->isPast()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Verification session is invalid or expired.',
            ], 422);
        }

        $questions = $verification->questions;
        $currentIndex = (int) $verification->questions_answered;

        if (!isset($questions[$currentIndex])) {
            return response()->json([
                'success' => false,
                'message' => 'No more questions available.',
            ], 422);
        }

        $person = Person::where('nin', $verification->nin)
            ->where('status', 'Active')
            ->first();

        if (!$person) {
            return response()->json([
                'success' => false,
                'message' => 'Person record is unavailable.',
            ], 422);
        }

        $correct = (int) $verification->correct_answers;

        if ($this->isCorrectAnswer($person, $questions[$currentIndex], $validated['answer'])) {
            $correct++;
        }

        $answered = $currentIndex + 1;

        $verification->update([
            'questions_answered' => $answered,
            'correct_answers' => $correct,
        ]);

        // HATUA 1: maswali 1-3.
        if ($answered < self::STAGE_ONE_QUESTIONS) {
            return $this->nextQuestionResponse($questions, $correct, $answered);
        }

        if ($answered === self::STAGE_ONE_QUESTIONS) {
            if ($correct >= self::STAGE_ONE_PASS) {
                return $this->passedResponse($verification, $correct, $answered, 1);
            }

            if ($correct < self::STAGE_ONE_CONTINUE) {
                return $this->failedResponse($verification, $correct, $answered, 1);
            }

            // 2 kati ya 3: endelea na maswali ya ziada.
            return $this->nextQuestionResponse($questions, $correct, $answered);
        }

        // HATUA 2: maswali 4-5.
        if ($answered < self::TOTAL_QUESTIONS) {
            // Kama haiwezekani tena kufikia kizingiti, sitisha mapema.
            if ($correct + (self::TOTAL_QUESTIONS - $answered) < self::FINAL_PASS) {
                return $this->failedResponse($verification, $correct, $answered, 2);
            }

            return $this->nextQuestionResponse($questions, $correct, $answered);
        }

        if ($answered === self::TOTAL_QUESTIONS) {
            return $correct >= self::FINAL_PASS
                ? $this->passedResponse($verification, $correct, $answered, 2)
                : $this->failedResponse($verification, $correct, $answered, 2);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unexpected verification state.',
        ], 422);
    }

    /**
     * Staff NIDA profiling: pokea taarifa za mtu moja kwa moja kwa NIN
     * (bila maswali). Inatumiwa na upande wa staff wa mfumo-mteja pekee,
     * ambao tayari umeshamthibitisha mtumiaji wake.
     */
    public function profile(Request $request)
    {
        if ($error = $this->authorizeClient($request)) {
            return $error;
        }

        $validated = $request->validate([
            'nin' => ['required', 'string', 'digits:20'],
        ]);

        $person = Person::where('nin', $validated['nin'])->first();

        if (!$person) {
            return response()->json([
                'success' => false,
                'message' => 'No NIDA record was found for this NIN.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Personal information retrieved successfully.',
            'data' => [
                'nin' => $person->nin,
                'first_name' => $person->first_name,
                'middle_name' => $person->middle_name,
                'last_name' => $person->last_name,
                'date_of_birth' => $person->date_of_birth?->format('Y-m-d'),
                'gender' => $person->gender,
                'nationality' => $person->nationality,
                'birth_region' => $person->birth_region,
                'birth_district' => $person->birth_district,
                'region' => $person->region,
                'district' => $person->district,
                'ward' => $person->ward,
                'village' => $person->village,
                'phone' => $person->phone,
                'profile_picture' => $person->profile_picture,
                // Picha yenyewe (data URI) ili mfumo-mteja usitegemee URL ya umma
                'photo' => $this->photoDataUri($person->profile_picture),
                'status' => $person->status,
            ],
        ]);
    }

    /**
     * Soma picha ya NIDA kutoka public disk na irudishe kama data URI (null kama haipo).
     */
    private function photoDataUri(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if (!$disk->exists($path) || $disk->size($path) > 5 * 1024 * 1024) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/jpeg';

        if (!str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($disk->get($path));
    }

    /**
     * Retrieve personal information after successful verification.
     */
    public function person(Request $request)
    {
        if ($error = $this->authorizeClient($request)) {
            return $error;
        }

        $validated = $request->validate([
            'session_token' => ['required', 'string'],
        ]);

        $verification = NinVerification::where(
            'session_token_hash',
            hash('sha256', $validated['session_token'])
        )->first();

        if (
            !$verification ||
            $verification->status !== 'passed' ||
            !$verification->expires_at ||
            $verification->expires_at->isPast()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Successful verification is required.',
            ], 403);
        }

        $person = Person::where('nin', $verification->nin)
            ->where('status', 'Active')
            ->first();

        if (!$person) {
            return response()->json([
                'success' => false,
                'message' => 'Person record not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Personal information retrieved successfully.',

            'data' => [
                'nin' => $person->nin,

                'first_name' => $person->first_name,
                'middle_name' => $person->middle_name,
                'last_name' => $person->last_name,

                'date_of_birth' => $person->date_of_birth?->format('Y-m-d'),
                'gender' => $person->gender,
                'nationality' => $person->nationality,

                // Place of birth.
                'birth_region' => $person->birth_region,
                'birth_district' => $person->birth_district,

                // Current residential address.
                'region' => $person->region,
                'district' => $person->district,
                'ward' => $person->ward,
                'village' => $person->village,

                'phone' => $person->phone,
                'profile_picture' => $person->profile_picture,
            ],
        ]);
    }
}
