<?php
  require_once("mysql.class.php"); //класс для безопасной работы с бд
  require_once("work.class.php"); //класс для работы
  require_once('mysql.options.php'); //данные для подключения к бд
  //$db=new SafeMySQL($mysqlOpt); //подключаемся к бд
  
  $work = new Work($mysqlOpt);
  $form = ['name'=>'', 'age'=>''];
  if(!empty($_GET['edit'])){
    if(!empty($_POST['name']) && !empty($_POST['age'])){
      $work->edit_str($_GET['edit'], $_POST['name'], $_POST['age']);
    }
    else{$form=$work->get_str($_GET['edit']);}
  }
  if(!empty($_GET['del'])){
    $work->del_str($_GET['del']);
  }
  if(empty($_GET['edit']) && (!empty($_POST['name']) && !empty($_POST['age']))){
    $work->add_str($_POST['name'], $_POST['age']);
  }

  $table=$work->get_list();
  $stat=$work->get_stat();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
</head>
<body>
  <form action="#" method="post"><div>
    Имя: <input type="text" name="name" value="<?=$form['name'];?>">&nbsp;&nbsp;&nbsp;
    Возраст: <input type="text" name="age" value="<?=$form['age'];?>" size="3">&nbsp;&nbsp;&nbsp;
    <input type="submit" value="Отправить"/><br/><br/>
  </div></form>
  <?=$table;?><br/><br/>
  <div>Переписано всего: <?=$stat['count']?></div>
  <div>Общий возраст: <?=$stat['age']?></div>
</body>
</html>