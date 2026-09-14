<?php

require_once __DIR__ . '/Person.php';

class Student extends Person {
    private string $registrationNumber;
    private array $enrolledCourses = [];

    public function __construct(string $id, string $name, string $email, string $registrationNumber) {
        parent::__construct($id, $name, $email);
        $this->registrationNumber = $registrationNumber;
    }

    public function getRegistrationNumber(): string {
        return $this->registrationNumber;
    }

    public function enrollCourse(Course $course): void {
        $this->enrolledCourses[$course->getCode()] = $course;
    }

    public function getEnrolledCourses(): array {
        return $this->enrolledCourses;
    }

    // Polymorphic implementation
    public function getDetails(): string {
        $courseCount = count($this->enrolledCourses);
        return "[Student] {$this->name} (Reg: {$this->registrationNumber}, Email: {$this->email}, Enrolled Courses: {$courseCount})";
    }
}