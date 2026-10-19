<?php

namespace App\Modules\Legacy\Moodle;

use RuntimeException;

/**
 * An export made by deploy/server/bin/moodle-export: manifest.json plus files/<sha1>.
 *
 * @phpstan-type MoodleFile array{area: string, name: string, hash: string, mime: string, size: int}
 * @phpstan-type MoodleAnswer array{text_html: string, fraction: float, feedback_html: string|null}
 * @phpstan-type MoodleQuestion array{qtype: string, single: bool, text_html: string, feedback_html: string|null, answers: list<MoodleAnswer>}
 * @phpstan-type MoodleModule array{cmid: int, type: string, visible: bool, name: string, html: string|null, files: list<MoodleFile>, quiz?: array{questions: list<MoodleQuestion>}}
 * @phpstan-type MoodleSection array{number: int, name: string, visible: bool, modules: list<MoodleModule>}
 * @phpstan-type MoodlePayment array{amount: string, currency: string, gateway: string, paid_at: string|null, razorpay_payment_id: string|null, razorpay_order_id: string|null, item: string}
 * @phpstan-type MoodleUser array{id: int, email: string, firstname: string, lastname: string, password_hash: string, auth: string, confirmed: bool, suspended: bool, deleted: bool, roles: list<string>, active: bool, created_at: string|null, enrolled_at: string|null, edition: array{tier: int, name: string, amount: string, granted_at: string|null}|null, payments: list<MoodlePayment>, completed_cmids: list<int>, certificate: array{code: string, issued_at: string|null}|null}
 */
class Bundle
{
    /**
     * @param  array{source: array{site: string, course_id: int, exported_at: string}, course: array{fullname: string, shortname: string, summary_html: string|null, overview_files: list<MoodleFile>}, sections: list<MoodleSection>, users: list<MoodleUser>}  $manifest
     */
    private function __construct(public readonly string $dir, public readonly array $manifest) {}

    public static function open(string $dir): self
    {
        $dir = rtrim($dir, '/\\');
        $json = @file_get_contents("{$dir}/manifest.json");
        if ($json === false) {
            throw new RuntimeException("No manifest.json in {$dir}.");
        }
        $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 'erudsway-moodle-export' || ($manifest['version'] ?? null) !== 1) {
            throw new RuntimeException('This is not a Moodle export bundle (format version 1).');
        }

        return new self($dir, $manifest);
    }

    public function courseId(): int
    {
        return (int) $this->manifest['source']['course_id'];
    }

    /** Local path of an exported file; it must have been copied (not exported with --skip-files). */
    public function file(string $hash): string
    {
        if (! preg_match('/^[0-9a-f]{40}$/', $hash)) {
            throw new RuntimeException("Bad file reference {$hash}.");
        }
        $path = "{$this->dir}/files/{$hash}";
        if (! is_file($path)) {
            throw new RuntimeException("File {$hash} is missing from the bundle — re-run the export without --skip-files.");
        }

        return $path;
    }
}
