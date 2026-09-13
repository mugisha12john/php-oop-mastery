
<?php

class Customer
{
    private $id;
    private $name;
    public $email;
    private $phone;
    private $nid;

    // cunstructor function
    function __construct($_name,$_email,$_phone,$_nid){
        $this -> name = $_name;
        $this -> email = $_email;
        $this -> phone = $_phone;
        $this -> nid = $_nid;
    }

    public function register()
    {
        echo "New customer is created with ID: " . $this->nid . " successfully...\n";
    }

    public function setUid($uuid)
    {
        $this->nid = $uuid;
    }
    public function getNid(){
        return $this -> $nid;
    }
}

$customer1 = new Customer("Obama","obama@gmail.com","0789393","NID#123343");
$customer2 = new Customer("JImmy","jimmy@gmail.com","078389","NID#324324");
// $customer1 -> email = 'buban@gmail.com\n';
// $customer1->setUid("21322");
// $customer1->register();
// echo $customer1 -> email

?>