<?php

namespace App\Modules\Legacy\Moodle;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\LegacyPasswords;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Services\Enroller;
use App\Modules\Learning\Services\ProgressTracker;
use App\Modules\Legacy\Models\LegacyLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Brings a Moodle course's learners across once the course has been imported and published:
 * an account each (keeping their Moodle password until they first sign in), access to the
 * course recorded as a grant that names their Moodle purchase, the lessons they completed,
 * and — for those who earned one — a certificate dated as in Moodle. Safe to run again.
 */
class LearnerImporter
{
    public function __construct(
        private readonly Enroller $enroller,
        private readonly ProgressTracker $progress,
    ) {}

    /** @return list<array{email: string, account: string, access: string, completed: int, certificate: string}> */
    public function import(Bundle $bundle, User $admin): array
    {
        $courseId = LegacyLink::target('course', $bundle->courseId());
        $course = $courseId ? Course::query()->find($courseId) : null;
        if ($course === null) {
            throw new RuntimeException('Import the course first (moodle:import-course).');
        }
        if (! in_array($course->status, [CourseStatus::Published, CourseStatus::Unpublished], true)) {
            throw new RuntimeException('Publish the course first: learners can only be given access to a published course.');
        }

        $rows = [];
        foreach ($bundle->manifest['users'] as $moodleUser) {
            if ($moodleUser['deleted'] || ! $moodleUser['active'] || array_diff($moodleUser['roles'], ['student']) !== []) {
                continue; // removed accounts, suspended enrolments and staff aren't buyers
            }
            $rows[] = $this->learner($course, $admin, $moodleUser);
        }

        return $rows;
    }

    /**
     * @param  array{id: int, email: string, firstname: string, lastname: string, password_hash: string, auth: string, confirmed: bool, suspended: bool, created_at: string|null, enrolled_at: string|null, edition: array{tier: int, name: string, amount: string, granted_at: string|null}|null, payments: list<array{amount: string, currency: string, gateway: string, paid_at: string|null, razorpay_payment_id: string|null, razorpay_order_id: string|null, item: string}>, completed_cmids: list<int>, certificate: array{code: string, issued_at: string|null}|null}  $m
     * @return array{email: string, account: string, access: string, completed: int, certificate: string}
     */
    private function learner(Course $course, User $admin, array $m): array
    {
        [$user, $account] = $this->account($m);

        $enrollment = Enrollment::query()->where(['user_id' => $user->id, 'course_id' => $course->id])->first();
        if ($enrollment?->status === EnrollmentStatus::Active) {
            $access = 'already had access';
        } else {
            $enrollment = $this->enroller->grant($user, $course, $admin, $this->reason($m));
            if ($m['enrolled_at']) {
                $enrollment->forceFill(['granted_at' => Carbon::parse($m['enrolled_at'])])->save();
            }
            $access = 'granted';
        }

        // Lessons done in Moodle; a learner who earned the certificate finished everything.
        $lectureIds = $m['certificate']
            ? Lecture::query()->where('course_id', $course->id)->pluck('id')->all()
            : collect($m['completed_cmids'])->flatMap(fn (int $cmid) => LegacyLink::targets('module', $cmid))
                ->filter(fn (int $id) => Lecture::query()->whereKey($id)->where('course_id', $course->id)->exists())->unique()->all();
        foreach (Lecture::query()->whereIn('id', $lectureIds)->get() as $lecture) {
            $this->progress->setCompleted($user, $lecture, true);
        }

        $certificate = 'none';
        if ($m['certificate']) {
            $enrollment->refresh();
            $certificate = Certificate::query()->where('enrollment_id', $enrollment->id)->first();
            if ($certificate && $m['certificate']['issued_at']) {
                $issued = Carbon::parse($m['certificate']['issued_at']);
                $enrollment->forceFill(['completed_at' => $issued])->save();
                $certificate->forceFill(['issued_at' => $issued])->save();
            }
            $certificate = $certificate ? "{$certificate->serial} (Moodle {$m['certificate']['code']})" : 'not issued — course not complete';
        }

        return ['email' => $user->email, 'account' => $account, 'access' => $access, 'completed' => count($lectureIds), 'certificate' => $certificate];
    }

    /**
     * @param  array{id: int, email: string, firstname: string, lastname: string, password_hash: string, auth: string, confirmed: bool, suspended: bool, created_at: string|null}  $m
     * @return array{0: User, 1: string}
     */
    private function account(array $m): array
    {
        $email = mb_strtolower(trim($m['email']));
        $user = User::query()->where('email', $email)->first();
        if ($user !== null) {
            LegacyLink::remember('user', $m['id'], $user);

            return [$user, 'existing account kept as is'];
        }

        $user = DB::transaction(function () use ($m, $email) {
            $user = User::query()->create([
                'name' => Str::limit(trim("{$m['firstname']} {$m['lastname']}") ?: Str::before($email, '@'), 120, ''),
                'email' => $email,
                // Unusable until they sign in with their Moodle password or reset it.
                'password' => Str::random(64),
            ]);
            $user->forceFill([
                'email_verified_at' => $m['confirmed'] ? now() : null,
                'created_at' => $m['created_at'] ? Carbon::parse($m['created_at']) : now(),
            ])->save();
            $user->profile()->create();
            $user->assignRole(Role::Student->value);
            if (in_array($m['auth'], ['email', 'manual'], true) && $m['password_hash'] !== '') {
                LegacyPasswords::store($user, $m['password_hash']);
            }
            LegacyLink::remember('user', $m['id'], $user);

            return $user;
        });

        return [$user, 'created (Moodle password kept)'];
    }

    /** @param array{edition: array{name: string, amount: string}|null, payments: list<array{amount: string, gateway: string, paid_at: string|null, razorpay_payment_id: string|null}>} $m */
    private function reason(array $m): string
    {
        $parts = ['Bought on the old Moodle site'];
        if ($m['edition']) {
            $parts[] = "{$m['edition']['name']} edition, ₹".number_format((float) $m['edition']['amount']);
        }
        foreach ($m['payments'] as $p) {
            $parts[] = trim("{$p['gateway']} {$p['razorpay_payment_id']}").($p['paid_at'] ? ' on '.Carbon::parse($p['paid_at'])->timezone('Asia/Kolkata')->format('j M Y') : '');
        }
        if ($m['payments'] === []) {
            $parts[] = 'no online payment recorded in Moodle';
        }

        return Str::limit(implode('; ', $parts), 500, '');
    }
}
