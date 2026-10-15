<?php
// $array = array("mugisha","keza","jimmy");
// var_dump($array[0]);
// echo $array[2];

// $car = array("brand"=>"Ford", "model"=>"Mustang", "year"=>1964);
// // var_dump($car["brand"]);
// foreach($car as $key => $value){
//     echo "$key : $value \n";
// }

//constant variable and how to access them
// const MU = 2;
// echo MU;


// function myFunction() {
//   echo "I come from a function!";
// }

// $myArr = array("Volvo", 15, "myFunction");

// $myArr[2]();


// $cars = array("Volvo", "BMW", "Toyota");
// foreach ($cars as &$x) {
//   $x = "Ford";
// }

// $x = "ice cream";

// var_dump($cars);


// $cars = array("Volvo", "BMW", "Toyota");
// foreach ($cars as &$x) {
//   $x = "Ford";
// }

// var_dump($cars);

$eac_country = array("Rwanda","Uganda","Kenya");
$eac_country[] = "Burundi";
array_push($eac_country, "mugisha","jimmy");
array_splice($eac_country,4,1,"Somalia");
array_splice($eac_country,5,1,"DRC");
array_splice($eac_country,1,0,"Tanzania");
sort($eac_country,);
array_splice($eac_country,4,2);
array_pop($eac_country);
array_shift($eac_country);
print_r($eac_country);
?>