<?php

class Course {
    private string $code;
    private string $title;
    private int $credits;
    private ?Lecturer $lecturer = null;

    public function __construct(string $code, string $title, int $credits) {
        $this->code = $code;
        $this->title = $title;
        $this->credits = $credits;
    }

    public function getCode(): string {
        return $this->code;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function getCredits(): int {
        return $this->credits;
    }

    public function assignLecturer(Lecturer $lecturer): void {
        $this->lecturer = $lecturer;
    }

    public function getLecturer(): ?Lecturer {
        return $this->lecturer;
    }

    public function getCourseInfo(): string {
        $assigned = $this->lecturer ? $this->lecturer->getName() : 'Unassigned';
        return "Course: {$this->code} - {$this->title} ({$this->credits} Credits) | Lecturer: {$assigned}";
    }
}