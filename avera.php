<?php
class LeagueIndividual implements iLeagues
{
    private $idLeague, $nameLeague, $typeLeague, $handicapLeague; //* Инфа о лиге
    private $members, $membersCount; //*Участники и их количество
    private $infocycle;  //* Информация о последнем цикле: id, номер цикла, id лиги, кол-во туров, начало и конец цикла
    private $NumberTourEnd; //* Последний тур в текущем цикле
    private $ToursMvsM; //* Партии текущего цикла и тура
    private $db;
    
    private $countTour = []; //!
    private $idApprenticeThisLeague; //* метод: addMemberLeague. Хранит ид участников лиги

    //todo Переменные для работы с туром
    private $arrMembKey, $maxTour, $winMemTourId;
  
    public function __construct($id_League, $league_information, $MySqlOpt)
    {
        //todo записываются все необходимые данные о лиге
        $this->db = new SafeMySQL($MySqlOpt);

        $this->idLeague = $id_League;
        $this->nameLeague = $league_information[0]['name'];
        $this->typeLeague = $league_information[0]['type'];
        $this->handicapLeague = $league_information[0]['handicap'];

        $this->members = $this->infoMembersList(); //* [key]=>[участник]
        $this->membersCount = count($this->members); //* Просто количество (число)

        $this->infocycle = $this->db->getAll("SELECT c.id, c.cycle, c.league, c.tourcount, c.started, c.ended FROM leagues_circles c WHERE c.league=?i ORDER BY c.cycle DESC LIMIT 1", $this->idLeague); //! Информация о последнем цикле

        $this->infoTour(); //*Инфа о последнем туре и назначенных партиях
       
     

        $this->countTour[] = $this->infocycle[0]["tourcount"]; //*Количество туров в цикле

        $this->countTour[] = $this->db->getAll("SELECT MAX(DISTINCT league_tour) as `0` FROM games WHERE league=?i AND league_cirle=?i AND winner != 0", $this->idLeague, $this->infocycle[0]["cycle"]);  //* Сколько прошло туров

        $this->countTour[] = $this->membersCount/2; //* Количество партий должно быть сыграно в туре

        $this->countTour[] = $this->db->getAll("SELECT COUNT(*) as `0` FROM games WHERE league=?i AND league_cirle=?i AND league_tour=?i AND winner != 0", $this->idLeague, $this->infocycle[0]["cycle"], $this->countTour[1][0][0]);  //* сколько партий прошло в этом туре


       
        
        
    }
    //--------------------------------------------------
    public function testtest(){return $this->ToursMvsM;} //* Щуп класса, чтобы не нарушить его работу.
    //---------------------------------------------------

    public function infoCycles(){return $this->infocycle;} //* Возвращается информацию о текущем цикле
    public function NumberTourEnd(){return $this->NumberTourEnd;} //*Возвращает последний прошедший тур
    public function infoTourMvsM(){return $this->ToursMvsM;} //*Возвращает любые назначенные партии в текущем цикле и туре

    public function infoLeague()
    {
        return 'id Лиги: ' . $this->idLeague .' Название: ' . $this->nameLeague . '<br>Тип: ' . $this->typeLeague . ' Гандикап: ' . $this->handicapLeague;
    }

    public function infoMembersList()
    {
        //* Возвращает массив всех участников лиги [key] => [id,имя, отчество, фамилия, рейтинг в лиге текущий]
        return $this->db->getAll("SELECT a.id, a.name, a.patronymic, a.surname, m.rating FROM leagues_members m INNER JOIN apprentices a ON m.apprentice = a.id WHERE m.league=?i ORDER BY m.rating DESC", $this->idLeague);
        
    }

    public function addMemberLeague($idApprentices)
    {
        //* [key]=>[apprentice]=>[id]
        $this->idApprenticeThisLeague = $this->db->getAll("SELECT l.apprentice FROM leagues_members l  WHERE  l.league = ?i", $this->idLeague);

        foreach($this->idApprenticeThisLeague as $key => $value)
        {
            //*Убираем из переданного массива уже ид уже существующие в лиге
            unset($idApprentices[$value["apprentice"]]);
        }
        
        foreach($idApprentices as $key => $value) //* key - ид участника
        {
            $this->db->query("INSERT INTO leagues_members (apprentice, league, rating) VALUE (?i, ?i, ?i)", $key, $this->idLeague, 0); 
        }
    }

    public function removeMemberLeague($idLeagueMembers)
    {
        $this->db->query("DELETE FROM `leagues_members` WHERE `id` = ?i", $idLeagueMembers);
    }
 


    //*Ебаторий 
    //* При StatCycle Создается новый цикл в таблице leagues_circles, запрещается добавление/удаление участников. +
    //* Кнопка запуска цикла меняется на неактивную 'закончить цикл' и становится активная кнопка 'запустить тур'. +

    //* При NexTour:
    //* 1. Если не было туров то по клику из предыдущих пунктов по кнопке формируется первый тур.
    //* 2. Если тур уже запущен то ни одна из кнопок не активна, пока все партии не будут сыграны,
    //* Когда сыграны то появляется кпонка следующего тура
    //* 3. Если это был последний тур то активна кнопка закончить цикл и не активна кнопка тура
    //* Все: по клику должны обновляться очки

    //* При каждом запуске тура смотрится результат посленднего тура, устанавливаются новые очки и формируется новый тур.
    
    //* При EndCycle Завершается цикл в таблице leagues_circles и
    //* формируются по последнему туру обновление очков.

    public function cycleLeaguMembrsRating($cycleORtour)
    {        
        if($cycleORtour == "StartCycle")
        {
            $this->cycleWork();
        }
        elseif($cycleORtour == "NextTour")
        {
            $this->updateRating();
            $this->tourWork();

        }elseif($cycleORtour == "EndCycle")
        {
            $this->updateRating();
            $this->db->query("UPDATE `leagues_circles` SET `ended`=?s WHERE `league`=?i AND `cycle`=?i", date('d.m.Y H:i'), $this->idLeague, $this->infocycle[0]["cycle"]);
        
        }else{return 'Что-то в своей жизне ты делаешь не так. :-)';}
    }

    //* Запуск нового цикла
    private function cycleWork()
    {
        //* Проверка первый цикл в лиге или нет
        if(!empty($this->infocycle))
        {
            $this->db->query("INSERT INTO leagues_circles (`cycle`, `league`, `tourcount`, `started`) VALUE (?i, ?i, ?i, ?s)", $this->infocycle[0]["cycle"]+1, $this->idLeague, $this->membersCount-1, date('d.m.Y H:i'));
        }else
        {
            $this->db->query("INSERT INTO leagues_circles (`cycle`, `league`, `tourcount`, `started`) VALUE (?i, ?i, ?i, ?s)", 1, $this->idLeague, $this->membersCount-1, date('d.m.Y H:i'));
        }
    }







    private function tourWork()
    {
        $this->maxTour = $this->db->getAll("SELECT MAX(`league_tour`) as `maxTour` FROM `games` WHERE `league`=?i AND `league_cirle`=?i", $this->idLeague, $this->infocycle[0]["cycle"]);

        if($this->membersCount % 2) //*Не четное число
        {
            $this->arrMembKey = array_rand($this->members, 1);

            $this->db->query("INSERT INTO `games` (`apprentice_first`, `league`, `league_cicle`, `league_tour`) VALUE (?i, ?i, ?i, ?i)", $this->members[$this->arrMembKey]["id"], $this->idLeague, $this->infocycle[0]["cycle"],  $this->maxTour[0]["maxTour"]+1);

            unset($this->members[$this->arrMembKey]);        
        }
        $this->membersCount = count($this->members);
        
        while($this->membersCount > 0)
        {
            $this->arrMembKey = array_rand($this->members, 2);

            $this->db->query("INSERT INTO `games` (`apprentice_first`, `apprentice_second`, `league`, `league_cirle`, `league_tour`) VALUE (?i, ?i, ?i, ?i, ?i)", $this->members[$this->arrMembKey[0]]["id"], $this->members[$this->arrMembKey[1]]["id"], $this->idLeague, $this->infocycle[0]["cycle"],  $this->maxTour[0]["maxTour"]+1);

            unset($this->members[$this->arrMembKey[0]]);
            unset($this->members[$this->arrMembKey[1]]);

            $this->membersCount = count($this->members);
        }
        
    }

    //*Обновление рейтинга (Каждый раз когда закрывается цикл или переход на новый тур)
    private function updateRating()
    {
        $this->winMemTourId = $this->db->getAll("SELECT `apprentice_first`, `apprentice_second`, `winner` FROM `games` WHERE `league`=?i AND `league_cirle`=?i AND `league_tour`=?i", $this->idLeague, $this->infocycle[0]["cycle"], $this->countTour[1][0][0]);

        foreach($this->winMemTourId as $value)
        {
            $this->db->query("UPDATE `leagues_members` SET `rating`=`rating` + 1 WHERE `apprentice`=?i", $value["winner"]);
            if($value["winner"] == $value["apprentice_first"])
            {
                $this->db->query("UPDATE `leagues_members` SET `rating`=IF(`rating`=0, 0, -1) WHERE `apprentice`=?i", $value["apprentice_second"]);
            }else
            {
                $this->db->query("UPDATE `leagues_members` SET `rating`=IF(`rating`=0, 0, -1) WHERE `apprentice`=?i", $value["apprentice_first"]);
            }
        }

        if($this->NumberTourEnd == 0)
        {//* Сработает, если туров не было еще(значит обновлять очки не надо)
        }else
        {
           
        }


    }

    //*Приватка. Получает информацию о последнем туре и за этот тур назначенные партии
    private function infoTour()
    {
        $this->NumberTourEnd = $this->db->getAll("SELECT DISTINCT `league_tour` FROM `games` WHERE `league`=?i AND `league_cirle`=?i ORDER BY `league_tour` DESC LIMIT 1", $this->idLeague, $this->infocycle[0]["cycle"]); //*Возвращает последний записанный тур по текущей лиги (Если из 6 туров прошло 3, то вернет 3 л-логика)

        if(!empty($this->NumberTourEnd))
        {
            $this->NumberTourEnd = $this->NumberTourEnd[0]['league_tour'];
            $this->ToursMvsM = $this->db->getAll("SELECT g.id, g.apprentice_first, g.apprentice_second, g.winner FROM games g WHERE g.winner != 0 AND g.league=?i AND g.league_cirle=?i AND g.league_tour=?i", $this->idLeague, $this->infocycle[0]["cycle"], $this->NumberTourEnd);
        }else
        {
            $this->ToursMvsM = 0;
            $this->NumberTourEnd = 0;
        }
    }
}

?>
