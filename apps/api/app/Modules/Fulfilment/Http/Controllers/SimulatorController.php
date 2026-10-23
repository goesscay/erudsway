<?php

namespace App\Modules\Fulfilment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Fulfilment\Http\Requests\StartTrialRequest;
use App\Modules\Fulfilment\Simulator\SimulatorAccess;
use App\Modules\Fulfilment\Simulator\SimulatorClient;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/** The GST Simulator page: the free trial, where the learner stands, and one-click sign-in. */
class SimulatorController extends Controller
{
    public function __construct(private readonly SimulatorAccess $access) {}

    /**
     * Facts the public page needs: the trial length and the course that sells simulator plans.
     *
     * @unauthenticated
     */
    public function info(): JsonResponse
    {
        return ApiResponse::data([
            'trial_days' => $this->access->trialDays(),
            // Its editions are the simulator plans (null until `simulator:setup-plans` has run).
            'plans_course' => $this->access->plansCourse()?->slug,
            'login_url' => rtrim((string) config('services.simulator.url'), '/').'/gst/login',
        ]);
    }

    /** The signed-in learner's trial or paid access, with their practice login when it's live. */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data($this->access->state($this->user($request)));
    }

    /**
     * Start the free trial (once per account). The login is ready within moments. A mobile
     * number is required: send `phone` if the profile doesn't have one yet (it's saved there).
     */
    public function startTrial(StartTrialRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $phone = Phone::normalise($request->safe()->string('phone')->toString());
        if ($phone !== null) {
            $user->profile()->updateOrCreate([], ['phone' => $phone]);
            $user->load('profile');
        }
        if (empty($user->profile()->value('phone'))) {
            throw ValidationException::withMessages(['phone' => 'Add your mobile number to start the free trial.']);
        }
        $this->access->startTrial($user);

        return ApiResponse::data($this->access->state($user), status: 201);
    }

    /** A one-time link that signs the learner into their live practice login. */
    public function open(Request $request, SimulatorClient $simulator): JsonResponse
    {
        $login = $this->access->liveLogin($this->user($request));
        $username = (string) ($login->data['username'] ?? '');
        if ($login === null || $username === '') {
            throw new DomainException('NO_SIMULATOR_ACCESS', 'You don’t have a simulator login right now. Start the free trial or choose a plan.', 409);
        }
        try {
            return ApiResponse::data(['url' => $simulator->signInLink($username)]);
        } catch (Throwable $e) {
            report($e);
            throw new DomainException('SIMULATOR_UNAVAILABLE', 'The simulator couldn’t sign you in just now. Use your username and password on its login page instead.', 503);
        }
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
