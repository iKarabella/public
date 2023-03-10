<?php
  require_once("mysql.class.php"); //класс для безопасной работы с бд
  require_once("work.class.php"); //класс для работы
  require_once('mysql.options.php'); //данные для подключения к бд
  
  $work = new Work($mysqlOpt);

  $form = ['name'=>'', 'age'=>'']; //заполнение формы

  if(!empty($_GET['edit'])){ //если нужно редактирование записи
    if(!empty($_POST['name']) && !empty($_POST['age'])){ //и есть данные для обновления
      $work->edit_str($_GET['edit'], $_POST['name'], $_POST['age']);
      header("Location: https://".$_SERVER['SERVER_NAME'].'/');
    }
    else{ //нет данных для обновления - подгружаем их для формы
      $form=$work->get_str($_GET['edit']);
    }
  }
  if(!empty($_GET['del'])){ //удалить запиись
    $work->del_str($_GET['del']);
    header("Location: https://".$_SERVER['SERVER_NAME'].'/');
  }
  if(empty($_GET['edit']) && (!empty($_POST['name']) && !empty($_POST['age']))){ //не редактируем, но добавляем
    $work->add_str($_POST['name'], $_POST['age']);
  }

  $table=$work->get_list(); //данные таблицы
  $stat=$work->get_stat();  //данные статистики
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
