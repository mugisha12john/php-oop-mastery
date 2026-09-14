<?php
require_once __DIR__ . '/includes/autoLoader.php';

// 1. Create Department
$dept = new Department("Information Technology", "IT");

// 2. Instantiate Lecturers
$lecturer1 = new Lecturer("L01", "Dr. Alan Turing", "alan@college.ac.rw", "STF-101", "Algorithms");
$lecturer2 = new Lecturer("L02", "Ada Lovelace", "ada@college.ac.rw", "STF-102", "Software Architecture");

$dept->addLecturer($lecturer1);
$dept->addLecturer($lecturer2);

// 3. Instantiate Courses and assign Lecturers
$cs101 = new Course("CS101", "Object-Oriented Programming with PHP", 4);
$cs102 = new Course("CS102", "Database Systems & Architecture", 3);

$cs101->assignLecturer($lecturer1);
$cs102->assignLecturer($lecturer2);

$dept->addCourse($cs101);
$dept->addCourse($cs102);

// 4. Instantiate Students & Enroll in Courses
$student1 = new Student("S01", "Jean Baptiste", "jb@student.ac.rw", "REG/2026/001");
$student2 = new Student("S02", "Divine Akisa", "divine@student.ac.rw", "REG/2026/002");
$student3 = new Student("S03", "Regis Mucyo", "regis@student.ac.rw", "REG/2026/003");

$student1->enrollCourse($cs101);
$student1->enrollCourse($cs102);
$student2->enrollCourse($cs101);
$student3->enrollCourse($cs102);

$dept->addStudent($student1);
$dept->addStudent($student2);

// 5. Output / Demonstration
echo "========================================\n";
echo "DEPARTMENT: " . $dept->getName() . "\n";
echo "========================================\n\n";

echo "--- COURSES OFFERED ---\n";
foreach ($dept->getCourses() as $course) {
    echo $course->getCourseInfo() . "\n";
}

echo "\n--- POLYMORPHISM DEMO (Person::getDetails) ---\n";
// Demonstrating polymorphism: treat different objects uniformly as Person instances
$people = [$lecturer1, $lecturer2, $student1, $student2];

foreach ($people as $person) {
    echo $person->getDetails() . "\n";
}