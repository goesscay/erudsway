<?php

namespace App\Modules\Legacy\Console;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\Role;
use App\Modules\Legacy\Moodle\Bundle;
use App\Modules\Legacy\Moodle\CourseImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportMoodleCourse extends Command
{
    protected $signature = 'moodle:import-course
        {bundle : Folder made by deploy/server/bin/moodle-export}
        {--instructor= : Email of the approved instructor who will own the course}
        {--category=registration-returns : Subcategory address (slug)}
        {--price=7800 : Price in rupees, GST included (a price tier is added if missing)}
        {--skip-sections= : Moodle section numbers to leave out, comma-separated}';

    protected $description = 'Import a Moodle course (curriculum, notes, quizzes, files, videos) as a draft course';

    public function handle(CourseImporter $importer): int
    {
        try {
            $bundle = Bundle::open((string) $this->argument('bundle'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $instructor = User::query()->where('email', mb_strtolower((string) $this->option('instructor')))->first();
        if ($instructor === null || ! $instructor->hasRole(Role::Instructor->value)) {
            $this->error('Give --instructor the email of an approved instructor.');

            return self::FAILURE;
        }
        $category = Category::query()->where('slug', (string) $this->option('category'))->whereNotNull('parent_id')->first();
        if ($category === null) {
            $this->error('--category must be a subcategory address, e.g. registration-returns.');

            return self::FAILURE;
        }
        $rupees = (int) $this->option('price');
        if ($rupees < 1) {
            $this->error('--price must be a whole number of rupees.');

            return self::FAILURE;
        }
        $skipOption = trim((string) $this->option('skip-sections'));
        $skip = $skipOption === '' ? [] : array_map('intval', explode(',', $skipOption));

        $result = $importer->import($bundle, $instructor, $category, $rupees * 100, $skip);
        foreach ($result['log'] as $line) {
            $this->line("  {$line}");
        }

        $course = $result['course'];
        $this->newLine();
        $this->info("Draft course “{$course->title}”: {$course->lecture_count} lectures; {$result['videos']} videos queued for transcoding.");
        $this->line('Next: finish the landing page (outcomes, requirements, audience) in Admin › Courses › Edit in builder,');
        $this->line('publish once every video is ready, then run moodle:import-learners.');

        return self::SUCCESS;
    }
}
