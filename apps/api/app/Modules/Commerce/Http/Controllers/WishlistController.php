<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Http\Resources\CourseCardResource;
use App\Modules\Catalog\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WishlistController extends Controller
{
    /** Saved courses that are still live, most recently saved first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $courses = Course::query()->published()
            ->join('wishlist_items', 'wishlist_items.course_id', '=', 'courses.id')
            ->where('wishlist_items.user_id', $this->user($request)->id)
            ->orderByDesc('wishlist_items.created_at')
            ->select('courses.*')
            ->with(['instructor:id,name', 'instructorProfile', 'category', 'priceTier'])
            ->get();

        return CourseCardResource::collection($courses);
    }

    public function store(Request $request): Response
    {
        $data = $request->validate(['course' => ['required', 'string']]);
        $course = Course::query()->published()->where('slug', $data['course'])->firstOrFail();
        DB::table('wishlist_items')->insertOrIgnore([
            'user_id' => $this->user($request)->id,
            'course_id' => $course->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->noContent();
    }

    public function destroy(Request $request, string $slug): Response
    {
        $courseId = Course::query()->where('slug', $slug)->value('id');
        DB::table('wishlist_items')->where('user_id', $this->user($request)->id)->where('course_id', $courseId)->delete();

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
