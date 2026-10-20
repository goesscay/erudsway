import { describe, expect, it } from "vitest";
import { flattenLectures, resumeLectureId, type LearnCourse, type LearnLecture } from "./api";

function lecture(id: number, completed = false): LearnLecture {
  return {
    id,
    title: `Lecture ${id}`,
    kind: "video",
    duration_seconds: 300,
    is_free_preview: false,
    has_video: true,
    resource_count: 0,
    completed,
    last_position_seconds: 0,
  };
}

function course(sections: LearnLecture[][], lastLectureId: number | null = null): LearnCourse {
  return {
    id: "c",
    slug: "c",
    title: "Course",
    subtitle: null,
    description: null,
    thumbnail_url: null,
    lecture_count: sections.flat().length,
    duration_seconds: 0,
    is_published: true,
    role: "learner",
    instructor: { name: "CA Example", slug: null, headline: null },
    enrollment: {
      progress_percent: 0,
      completed_count: 0,
      last_lecture_id: lastLectureId,
      completed_at: null,
      certificate_code: null,
    },
    curriculum: sections.map((lectures, i) => ({ id: i + 1, title: `Section ${i + 1}`, lectures })),
  };
}

describe("flattenLectures", () => {
  it("numbers lectures across sections", () => {
    const flat = flattenLectures(course([[lecture(10), lecture(11)], [lecture(12)]]));
    expect(flat.map((l) => [l.id, l.number, l.sectionTitle])).toEqual([
      [10, 1, "Section 1"],
      [11, 2, "Section 1"],
      [12, 3, "Section 2"],
    ]);
  });
});

describe("resumeLectureId", () => {
  it("returns to the lecture the learner last opened", () => {
    expect(resumeLectureId(course([[lecture(1, true), lecture(2)], [lecture(3)]], 3))).toBe(3);
  });

  it("otherwise starts at the first unfinished lecture", () => {
    expect(resumeLectureId(course([[lecture(1, true), lecture(2)], [lecture(3)]]))).toBe(2);
  });

  it("ignores a last lecture that no longer exists", () => {
    expect(resumeLectureId(course([[lecture(1), lecture(2)]], 99))).toBe(1);
  });

  it("falls back to the first lecture when everything is done", () => {
    expect(resumeLectureId(course([[lecture(1, true), lecture(2, true)]]))).toBe(1);
  });

  it("handles an empty course", () => {
    expect(resumeLectureId(course([]))).toBeNull();
  });
});
