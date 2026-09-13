# College Management System (PHP OOP)

A lightweight console-based College Management System designed to demonstrate core Object-Oriented Programming (OOP) principles in PHP 8+.

## OOP Concepts Demonstrated

- **Encapsulation:** Properties are declared `private` or `protected` and modified/accessed via typed getters and setters.
- **Inheritance:** `Student` and `Lecturer` inherit base attributes and behavior from the abstract base class `Person`.
- **Abstraction:** `Person` is an abstract class defining a contract with the abstract method `getDetails()`.
- **Polymorphism:** The method `getDetails()` behaves differently depending on whether it is called on a `Student` or a `Lecturer`.
- **Dynamic Autoloading:** `spl_autoload_register` dynamically resolves class dependencies.

## How to Run

```bash
cd college-management-system
php index.php
```
