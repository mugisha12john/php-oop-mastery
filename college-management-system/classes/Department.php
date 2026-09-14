<?php

class Department {
    private string $name;
    private string $code;
    private array $courses = [];
    private array $lecturers = [];
    private array $students = [];

    public function __construct(string $name, string $code) {
        $this->name = $name;
        $this->code = $code;
    }

    public function getName(): string {
        return $this->name;
    }

    public function addCourse(Course $course): void {
        $this->courses[$course->getCode()] = $course;
    }

    public function addLecturer(Lecturer $lecturer): void {
        $this->lecturers[$lecturer->getId()] = $lecturer;
    }

    public function addStudent(Student $student): void {
        $this->students[$student->getId()] = $student;
    }

    public function getCourses(): array {
        return $this->courses;
    }

    public function getLecturers(): array {
        return $this->lecturers;
    }

    public function getStudents(): array {
        return $this->students;
    }
}