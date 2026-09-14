<?php

require_once __DIR__ . '/Person.php';

class Lecturer extends Person {
    private string $staffId;
    private string $specialization;

    public function __construct(string $id, string $name, string $email, string $staffId, string $specialization) {
        parent::__construct($id, $name, $email);
        $this->staffId = $staffId;
        $this->specialization = $specialization;
    }

    public function getStaffId(): string {
        return $this->staffId;
    }

    public function getSpecialization(): string {
        return $this->specialization;
    }

    // Polymorphic implementation
    public function getDetails(): string {
        return "[Lecturer] {$this->name} (Staff ID: {$this->staffId}, Specialization: {$this->specialization})";
    }
}