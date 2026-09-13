<?php
class Student{
    private $name;
    private $email;
    private $registrationNumber;
    private $password;

    //const variable
    const MESSAGE = "Welcome to student class\n";

    function __construct($studentName,$studentEmail,$StudentRegistrationNumber){
        $this -> name = $studentName;
        $this -> email = $studentEmail;
        $this -> registrationNumber = $StudentRegistrationNumber;
    }

    protected function intro(){
        echo "My name is ".$this -> name." am student thanks.\n";
    }

    function register(){
        echo "student registered.. \n";
    }

    function setPassword($newPass){
        $this -> password = $newPass;
        echo "password changed successfully.\n";
    }
    function getPassword(){
        echo "Your password: ".$this -> password."\n";
    }
}

class Person extends Student{
    private $phone;

    const MESSAGE = "Welcome to derived class Person from student\n";

    public function __construct($name,$email,$regno,$phone){
        $this -> name = $name;
        $this -> email = $email;
        $this -> registerNumber = $regno;
        $this -> phone = $phone;
    }
    //call protected function
    function message(){
        echo "I came from outside school, thanks.\n";
        echo $this -> intro();
    }

    //overriding intro method
    public function intro(){
        echo "My name is ".$this -> name."I also has phone ".$this -> phone."\n";
    }
}

$student1= new Student("mugisha","mugisha@gnail.com","24RP3934");
echo $student1::MESSAGE;
$student1 -> setPassword("jskdi");
$student1 -> getPassword();

$p1 = new Person("keza","keza@gnail.com","24RP3934","078900");
echo $p1::MESSAGE;
$p1 -> message();
$p1 -> intro()
?>