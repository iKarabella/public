<?php
  class Work {
    private $db;

    public function __construct($mysqlOpt){
      $this->db = new SafeMySQL($mysqlOpt);
    }
    public function get_list() {
      $result=$this->db->getAll("SELECT * FROM `test1`"); 
      if(count($result)>0){
        $list='<table>';
        foreach($result as $res){
          $list.='<tr><td>'.$res['name'].'</td><td>'.$res['age'].'</td><td><a href="/?edit='.$res['id'].'">edit</a></td> <td><a href="/?del='.$res['id'].'">del</a></td></tr>';
        }
        $list.='<table>';
      }
      return $list;
    }
    public function get_stat(){
      return $this->db->getRow("SELECT count(*) as `count`, SUM(age) AS `age` FROM `test1`");
    }

    public function get_str($id){
      return $this->db->getRow("SELECT * FROM `test1` WHERE `id`=?i LIMIT 1", $id);
    }

    public function del_str($id){
      $this->db->query("DELETE FROM `test1` WHERE `id` = ?i", $id);
    }

    public function edit_str($id, $name, $age){
      $this->db->query("UPDATE `test1` SET `name`=?s, `age`=?i WHERE `id`='?i'", $name, $age, $id);
    }

    public function add_str($name, $age){
      $this->db->query("INSERT INTO `test1` (`name`, `age`) VALUES (?s, ?i);", $name, $age);
    }
  }
?>